<?php

namespace App\Http\Controllers\Customer;

use App\Enums\AdminListingStatus;
use App\Enums\DeliveryInstruction;
use App\Enums\GlobalSystemType;
use App\Enums\InventoryMovementType;
use App\Enums\VendorListingStatus;
use App\Events\SubOrderPlaced;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CheckoutPrepareRequest;
use App\Http\Requests\Customer\PlaceOrderRequest;
use App\Http\Requests\Customer\ShippingMethodsRequest;
use App\Http\Resources\Customer\CheckoutAddressResource;
use App\Http\Resources\Customer\CheckoutShippingMethodResource;
use App\Http\Resources\Customer\OrderResource;
use App\Http\Resources\Customer\PlaceOrderResultResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\AutoAssignShippingMethodJob;
use App\Jobs\FraudDetectionJob;
use App\Jobs\NotifyVendorJob;
use App\Jobs\OrderConfirmationEmailJob;
use App\Models\Address;
use App\Models\CountryPaymentGateway;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerReceiver;
use App\Models\MarketerContract;
use App\Models\CustomerWallet;
use App\Exceptions\GiftCardCurrencyMismatchException;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemCustomAttributeValue;
use App\Models\PaymentTransaction;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\SubOrder;
use App\Models\VendorListing;
use App\Services\LastClickAttributionService;
use App\Models\WarehouseInventory;
use App\Models\WarrantyPurchase;
use Illuminate\Support\Facades\Log;
use App\Services\Checkout\CartLineSource;
use App\Services\Checkout\CheckoutPricingEngine;
use App\Services\Checkout\CheckoutRollbackService;
use App\Services\Checkout\CouponEligibilityService;
use App\Services\Checkout\CouponUsageService;
use App\Services\Checkout\CouponNoLongerValidException;
use App\Services\Payments\PaymentMethodMapper;
use App\Services\Customer\CartService;
use App\Services\Customer\CheckoutCalculationService;
use App\Services\Customer\CodValidationService;
use App\Services\Customer\CityShippingSurchargeService;
use App\Services\Customer\ListingIdentifierService;
use App\Services\Customer\WarehouseShippingSurchargeService;
use App\Services\Customer\CheckoutWalletService;
use App\Services\Customer\LoyaltyService;
use App\Services\CouponService;
use App\Services\PaymentService;
use App\Services\ShippingSubsidyService;
use App\Services\WarrantyPlanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutCalculationService $calculationService,
        private readonly ListingIdentifierService $listingIdentifierService,
        private readonly PaymentService $paymentService,
        private readonly CheckoutWalletService $checkoutWalletService,
        private readonly WarrantyPlanService $warrantyPlanService,
        private readonly CityShippingSurchargeService $cityShippingSurchargeService,
        private readonly WarehouseShippingSurchargeService $warehouseShippingSurchargeService,
        private readonly ShippingSubsidyService $shippingSubsidyService,
        private readonly CouponService $couponService,
        private readonly LoyaltyService $loyaltyService,
        private readonly CodValidationService $codValidationService,
        private readonly LastClickAttributionService $attributionService,
        private readonly \App\Services\Ads\PlacementAdService $placementAds,
        private readonly CheckoutPricingEngine $pricingEngine,
        private readonly CouponEligibilityService $couponEligibilityService,
        private readonly CouponUsageService $couponUsageService,
        private readonly \App\Services\LedgerService $ledgerService = new \App\Services\LedgerService(),
        private readonly CheckoutRollbackService $rollbackService = new CheckoutRollbackService(),
        private readonly \App\Services\Inventory\InventoryService $inventoryService = new \App\Services\Inventory\InventoryService(),
    ) {}

    /**
     * Zero the shipping of every seller-party group in scope for a
     * free_shipping coupon (capped by coupon.max_discount), mutating
     * $vendorShipping in place (enhancement.md P-04 task 1). Returns the
     * total amount of shipping actually discounted, which callers persist
     * as the "coupon discount" shown/charged for shipping (funded per
     * coupon.funded_by — same vendor_coupon_cost/platform_coupon_cost split
     * P-03 already computes for the merchandise discount).
     */
    private function applyFreeShippingDiscount(?Coupon $coupon, array $couponResult, array &$vendorShipping): int
    {
        if (! $coupon) {
            return 0;
        }

        $type = $coupon->type instanceof \App\Enums\CouponType ? $coupon->type->value : (string) $coupon->type;
        if ($type !== 'free_shipping') {
            return 0;
        }

        $sellerParties = $couponResult['free_shipping_seller_parties'] ?? [];
        if (empty($sellerParties)) {
            return 0;
        }

        $eligibleShipping = 0;
        foreach ($sellerParties as $sp) {
            $eligibleShipping += (int) ($vendorShipping['per_vendor'][$sp]['shipping'] ?? 0);
        }

        $shippingDiscount = $coupon->max_discount !== null
            ? min($eligibleShipping, (int) $coupon->max_discount)
            : $eligibleShipping;

        if ($shippingDiscount <= 0) {
            return 0;
        }

        $remaining = $shippingDiscount;
        foreach ($sellerParties as $sp) {
            if ($remaining <= 0) {
                break;
            }
            $current = (int) ($vendorShipping['per_vendor'][$sp]['shipping'] ?? 0);
            $reduce = min($current, $remaining);
            if ($reduce > 0) {
                $vendorShipping['per_vendor'][$sp]['shipping'] = $current - $reduce;
                $remaining -= $reduce;
            }
        }

        $vendorShipping['total'] = max(0, (int) $vendorShipping['total'] - $shippingDiscount);

        return $shippingDiscount;
    }

    public function shippingMethods(ShippingMethodsRequest $request): JsonResponse
    {
        $customer = auth('customer')->user();
        $country = $request->attributes->get('country');

        $cart = $this->cartService->getOrCreateCart($customer, $country->id, $country->currency_code);
        $cart->load('items.vendorListing.productVariant');

        $addressId = $request->query('address_id');

        if ($addressId) {
            $address = $customer->addresses()
                ->where('id', $addressId)
                ->first();
        } else {
            $address = $customer->addresses()
                ->where('is_default', 1)
                ->whereIn('address_type', ['shipping', 'both'])
                ->first();
        }

        if (! $address) {
            $methods = ShippingMethod::where('is_active', 1)
                ->orderBy('display_priority')
                ->get();

            return ApiResponse::success([
                'shipping_methods' => $methods->map(fn (ShippingMethod $method) => new CheckoutShippingMethodResource($method, [
                    'fee' => 0,
                    'is_free' => true,
                    'cod_extra_fee' => 0,
                    'cod_available' => false,
                ]))->values(),
                'destination_zone' => null,
                'cod_available_for_address' => false,
            ], __('common.exceptions.checkout.shipping_methods_retrieved'));
        }

        $address->load('city.shippingZone');
        $zoneId = $address->city?->shipping_zone_id;

        $methodsQuery = ShippingMethod::where('is_active', 1);
        if ($zoneId) {
            $methodsQuery->whereHas('shippingRates', fn ($q) => $q->where('destination_zone_id', $zoneId)->where('is_active', 1))
                ->with(['shippingRates' => fn ($q) => $q->where('destination_zone_id', $zoneId)->where('is_active', 1)]);
        }
        $methods = $methodsQuery->orderBy('display_priority')->get();

        $codAvailableForAddress = (bool) ($address->city?->cod_available && $country->cod_available);
        $cartItems = $cart->items->all();

        $shippingMethods = $methods->map(function (ShippingMethod $method) use ($address, $country, $cartItems) {
            $calc = $this->calculationService->calculateShipping($address, $country, $method->id, $cartItems, false);
            $codCalc = $this->calculationService->calculateShipping($address, $country, $method->id, $cartItems, true);

            return new CheckoutShippingMethodResource($method, [
                'fee' => $calc['fee'],
                'is_free' => $calc['is_free'],
                'cod_extra_fee' => $codCalc['cod_extra_fee'],
                'cod_available' => $calc['cod_available'],
            ]);
        })->values();

        return ApiResponse::success([
            'shipping_methods' => $shippingMethods,
            'destination_zone' => $address->city?->shippingZone?->name,
            'cod_available_for_address' => $codAvailableForAddress,
        ], __('common.exceptions.checkout.shipping_methods_retrieved'));
    }

    public function prepare(CheckoutPrepareRequest $request): JsonResponse
    {
        $customer = auth('customer')->user();
        $country = $request->attributes->get('country');
        $validated = $request->validated();

        $cart = $this->cartService->getOrCreateCart($customer, $country->id, $country->currency_code);
        $cart->load([
            'items.vendorListing.vendor',
            'items.vendorListing.productVariant.product.category',
            'items.vendorListing.productVariant.product.images',
            'items.vendorListing.warehouseInventories',
            'items.vendorListing.primaryShippingMethod',
            'items.adminListing.productVariant.product.category',
            'items.adminListing.productVariant.product.images',
            'items.adminListing.warehouseInventories',
            'items.adminListing.primaryShippingMethod',
            'items.marketerListing.invitation.campaign.vendorListing.vendor',
            'items.marketerListing.invitation.campaign.vendorListing.productVariant.product.category',
            'items.marketerListing.invitation.campaign.vendorListing.productVariant.product.images',
            'items.marketerListing.invitation.campaign.vendorListing.warehouseInventories',
            'items.marketerListing.invitation.campaign.vendorListing.primaryShippingMethod',
            'items.selectedShippingMethod',
            'coupon',
        ]);

        if ($cart->items->isEmpty()) {
            return ApiResponse::error(__('common.exceptions.checkout.cart_empty'), [], 422);
        }

        $this->resolveMarketerCartItems($cart->items);

        $address = $customer->addresses()->find($validated['address_id']);
        if (! $address) {
            return ApiResponse::error(__('common.exceptions.checkout.address_not_found'), [], 404);
        }
        $address->load('city.shippingZone');

        $receiver = $this->resolveReceiver($customer, $validated['receiver_id'] ?? null);

        // Resolve gateway to determine type (cod, wallet, redirect, etc.)
        $selectedGateway = CountryPaymentGateway::where('id', $validated['country_payment_gateway_id'] ?? null)
            ->where('country_id', $country->id)
            ->with('gateway')
            ->first();

        $gatewayCode = $selectedGateway?->gateway?->code;
        $isCod       = $gatewayCode === 'cod';
        if ($isCod && ! $this->codAvailable($address, $country)) {
            return ApiResponse::error(__('common.exceptions.checkout.cod_unavailable'), [], 422);
        }

        $cartItems = $cart->items->all();

        if ($isCod) {
            $codErrors = $this->codValidationService->validate($cartItems);
            if (!empty($codErrors)) {
                return ApiResponse::error($codErrors[0], ['errors' => $codErrors], 422);
            }
        }

        $totalItemsQty = collect($cartItems)->sum('quantity');

        // Build shipping per cart-item group using each item's selected_shipping_method_id
        $shippingMethodIds = collect($cartItems)
            ->pluck('selected_shipping_method_id')
            ->filter()
            ->unique()
            ->values();

        $shippingMethods = ShippingMethod::whereIn('id', $shippingMethodIds)->get()->keyBy('id');

        $groupedForShipping = collect($cartItems)->groupBy('selected_shipping_method_id');

        $totalShippingFee = 0;
        $codExtraFee = 0;
        $shippingGroups = [];

        foreach ($groupedForShipping as $methodId => $groupItems) {
            $method = $shippingMethods[$methodId] ?? null;
            $calc = $method
                ? $this->calculationService->calculateShipping($address, $country, $methodId, $groupItems->all(), $isCod)
                : ['fee' => 0, 'cod_extra_fee' => 0, 'is_free' => true, 'cod_available' => false];

            $totalShippingFee += $calc['fee'];
            $codExtraFee += $calc['cod_extra_fee'];
            $shippingGroups[$methodId] = array_merge($calc, ['method' => $method]);
        }

        $shippingZone = $address->city?->shippingZone;

        // Same CartLineSource normalization as placeOrder() below, so
        // prepare()'s shipping preview groups admin/marketer lines the same
        // way the transaction will (enhancement.md P-02).
        $cartLineSources = [];
        foreach ($cartItems as $item) {
            $source = CartLineSource::resolve($item);
            if ($source) {
                $cartLineSources[$item->id] = $source;
            }
        }

        $vendorShipping = $this->resolveVendorShipping($cartItems, $cartLineSources, $totalShippingFee, $shippingZone, null);

        $codFeeCents = $isCod ? $codExtraFee : 0;

        $warrantyResult = $this->pricingEngine->resolveWarrantySelections(
            $cartItems,
            $this->buildWarrantySelectionsInput($cartItems, $validated['warranty_selections'] ?? []),
            $country,
            $cart->currency,
        );

        $couponResponse = null;
        $discountCents = 0;
        $discountAllocations = [];
        $coupon = null;
        if (! empty($validated['coupon_code'])) {
            $coupon = Coupon::where('code', $validated['coupon_code'])->first();
            if (! $coupon) {
                return ApiResponse::error(__('common.exceptions.checkout.invalid_coupon'), [], 422);
            }
        } elseif ($cart->coupon) {
            $coupon = $cart->coupon;
        }

        $subtotal = (int) collect($cartItems)->sum(fn ($i) => $i->unit_price * $i->quantity);
        $hasAffiliatePromo = (bool) $cart->affiliate_promo_code_id;
        $freeShippingDiscountCents = 0;

        if ($coupon) {
            $couponResult = $this->couponEligibilityService->evaluate(
                $coupon, $customer, $subtotal, $cart->currency, $cartItems, $country->id, $hasAffiliatePromo
            );

            if ($couponResult['error']) {
                return ApiResponse::error($couponResult['error'], [], 422);
            }

            $discountCents = $couponResult['discount'];
            $discountAllocations = $couponResult['allocations'];
            $freeShippingDiscountCents = $this->applyFreeShippingDiscount($coupon, $couponResult, $vendorShipping);
            $couponResponse = [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'discount' => $discountCents,
                'shipping_discount' => $freeShippingDiscountCents,
            ];
        }

        // is_stackable=false blocks the affiliate promo code from stacking
        // on top of the coupon (enhancement.md P-04 — this was previously
        // stacked unconditionally).
        if ($cart->affiliate_promo_code_id && (! $coupon || $coupon->is_stackable)) {
            $affiliatePromoCode = \App\Models\AffiliatePromoCode::find($cart->affiliate_promo_code_id);
            if ($affiliatePromoCode) {
                $promoResult = $this->calculationService->applyAffiliatePromoCode($affiliatePromoCode, $subtotal, $cart->currency);

                if (! $promoResult['error'] && $promoResult['discount'] > 0) {
                    $discountCents += $promoResult['discount'];
                    $weights = collect($cartItems)->mapWithKeys(fn ($i) => [$i->id => $i->unit_price * $i->quantity])->all();
                    foreach ($this->pricingEngine->allocateProRata($promoResult['discount'], $weights) as $k => $v) {
                        $discountAllocations[$k] = ($discountAllocations[$k] ?? 0) + $v;
                    }
                }
            }
        }

        $pricedCart = $this->pricingEngine->priceCart(
            $cartItems,
            $country,
            $vendorShipping['total'],
            $codFeeCents,
            $discountCents,
            $discountAllocations,
            0,
            [],
            0,
            $warrantyResult['selections'],
            collect($vendorShipping['per_vendor'])->map(fn ($v) => $v['shipping'])->all(),
        );

        $summary = $pricedCart->toArray();

        \Illuminate\Support\Facades\Cache::put(
            "checkout_prepare_signature:{$customer->id}",
            $pricedCart->signature(),
            now()->addMinutes(30),
        );

        $availableGateways = CountryPaymentGateway::where('country_id', $country->id)
            ->where('is_active', true)
            ->with('gateway')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($cpg) => [
                'id'            => $cpg->id,
                'gateway_code'  => $cpg->gateway?->code,
                'display_name'  => ['en' => $cpg->display_name_en, 'ar' => $cpg->display_name_ar],
                'type'          => $cpg->gateway?->type,
                'is_redirect'   => in_array($cpg->gateway?->code, ['thawani', 'paytabs']),
                'fee_pct'       => (float) $cpg->fee_pct,
                'fee_fixed'     => (int) $cpg->fee_fixed,
                'is_configured' => $cpg->is_configured,
                'environment'   => $cpg->environment,
                'image_url'     => $cpg->gateway?->image
                    ? \Illuminate\Support\Facades\Storage::url($cpg->gateway->image)
                    : null,
            ])->values()->all();

        $shipmentGroupsForItems = $this->cartService->buildShippingGroups($cart, $country->id);

        $wallet = CustomerWallet::where('customer_id', $customer->id)->first();
        $orderCurrency = $cart->currency ?? $customer->country?->currency_code;
        $walletBalance = $wallet->balance ?? 0;
        $walletCurrency = $wallet->currency_code ?? $orderCurrency;
        $walletApplicable = $wallet !== null && $wallet->currency_code === $orderCurrency && $wallet->balance > 0;

        $walletInfo = [
            'balance' => $wallet?->balance ?? 0,
            'currency_code' => $wallet?->currency_code ?? $orderCurrency,
            'applicable' => $walletApplicable,
        ];

        $vendorDeliveryResponse = collect($vendorShipping['per_vendor'])->map(fn ($v, $vendorId) => [
            'vendor_id' => $vendorId,
            'delivery_fee' => $v['shipping'],
            'surcharge_applied' => $v['surcharge'] > 0,
            'platform_subsidy' => $v['platform_subsidy'],
            'delivery_message' => $this->deliveryMessage($v),
        ])->values();

        $sessionId = $request->header('X-Session-Id') ?? $request->cookie('session_id') ?? ($request->hasSession() ? $request->session()->getId() : null);
        $checkoutBanner = $this->placementAds->resolve('checkout_banner', $country, 'logged_in', $sessionId);

        $attributedMarketerId = session('marketer_attribution.marketer_id');
        $marketerContractGate = null;
        if ($attributedMarketerId) {
            $contract = MarketerContract::where('marketer_id', $attributedMarketerId)->first();
            $marketerContractGate = [
                'marketer_id' => $attributedMarketerId,
                'is_required' => $contract?->is_required && $contract?->current_version > 0,
            ];
        }

        return ApiResponse::success([
            'total_items_qty' => $totalItemsQty,
            'order_summary' => $summary,
            'shipping' => [
                'total_fee'     => $vendorShipping['total'],
                'is_free'       => $vendorShipping['total'] === 0,
                'groups'        => collect($shippingGroups)->map(fn ($g, $id) => [
                    'shipping_method_id'            => $id,
                    'method_name'                   => $g['method']?->name,
                    'fee'                           => $g['fee'],
                    'is_free'                       => $g['is_free'],
                    'estimated_delivery_days_min'   => $g['method']?->min_delivery_days,
                    'estimated_delivery_days_max'   => $g['method']?->max_delivery_days,
                ])->values(),
                'delivery_fee' => $vendorShipping['total'],
                'is_free_delivery' => $vendorShipping['total'] === 0,
                'vendor_delivery' => $vendorDeliveryResponse,
            ],
            'address' => new CheckoutAddressResource($address, $country),
            'receiver' => $receiver
                ? ['id' => $receiver->id, 'name' => $receiver->name, 'phone' => $receiver->phone, 'is_default' => $receiver->is_default]
                : null,
            'receivers' => CustomerReceiver::where('customer_id', $customer->id)
                ->orderByDesc('is_default')
                ->get(['id', 'name', 'phone', 'is_default']),
            'gateway_code' => $gatewayCode,
            'gateway_type' => $selectedGateway?->gateway?->type,
            'available_payment_gateways' => $availableGateways,
            'coupon' => $couponResponse,
            'wallet_balance' => $walletBalance,
            'wallet_currency' => $walletCurrency,
            'wallet_applicable' => $walletApplicable,
            'wallet' => $walletInfo,
            'loyalty' => $this->loyaltyService->previewInfo($customer, $orderCurrency),
            'delivery_instructions' => collect(DeliveryInstruction::cases())->map(fn (DeliveryInstruction $case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ])->values(),
            'shipment_groups' => $shipmentGroupsForItems,
            'marketer_contract_gate' => $marketerContractGate,
            'checkout_banner' => $checkoutBanner,
        ], __('common.exceptions.checkout.preview_ready'));
    }

    public function placeOrder(PlaceOrderRequest $request): JsonResponse
    {
        $customer = auth('customer')->user();
        $country = $request->attributes->get('country');
        $validated = $request->validated();

        // enhancement.md P-05 task 3: idempotency_keys is the single source
        // of truth for "have we already processed this exact place-order
        // request" — unlike the old PaymentTransaction-only check, this
        // also catches wallet-only orders (which create no gateway
        // transaction) being retried, returning the first response instead
        // of double-processing (double wallet debit, duplicate order).
        $idempotencyKey = $validated['idempotency_key'];
        $requestHash = hash('sha256', json_encode($validated));

        $existingIdempotency = \App\Models\IdempotencyKey::where('key', $idempotencyKey)->first();
        if ($existingIdempotency) {
            if ($existingIdempotency->request_hash !== $requestHash) {
                return ApiResponse::error(
                    __('common.exceptions.checkout.idempotency_key_reused', [], 'This idempotency key was already used for a different request.'),
                    [], 409
                );
            }

            if ($existingIdempotency->response_status !== null) {
                return ApiResponse::success(
                    $existingIdempotency->response_body,
                    __('common.exceptions.checkout.order_placed'),
                    $existingIdempotency->response_status
                )->setStatusCode($existingIdempotency->response_status);
            }
        }

        $existingTransaction = PaymentTransaction::where('idempotency_key', $validated['idempotency_key'])->first();
        if ($existingTransaction && in_array($existingTransaction->status->value, ['pending', 'succeeded'], true)) {
            $order = Order::where('id', $existingTransaction->order_id)->first();
            if ($order) {
                return ApiResponse::error(__('common.exceptions.checkout.order_already_placed'), ['order_number' => $order->order_number], 409);
            }
        }

        $cart = $this->cartService->getOrCreateCart($customer, $country->id, $country->currency_code);
        $cart->load([
            'items.vendorListing.vendor',
            'items.vendorListing.productVariant.product.category',
            'items.vendorListing.productVariant.product.brand',
            'items.vendorListing.productVariant.product.images',
            'items.vendorListing.warehouseInventories',
            // Admin listing (platform stock)
            'items.adminListing.productVariant.product.category',
            'items.adminListing.productVariant.product.brand',
            'items.adminListing.productVariant.product.images',
            'items.adminListing.warehouseInventories',
            'items.marketerListing.invitation.campaign.vendorListing.vendor',
            'items.marketerListing.invitation.campaign.vendorListing.productVariant.product.category',
            'items.marketerListing.invitation.campaign.vendorListing.productVariant.product.brand',
            'items.marketerListing.invitation.campaign.vendorListing.productVariant.product.images',
            'items.marketerListing.invitation.campaign.vendorListing.warehouseInventories',
            // Campaigns sourced from an admin (platform) listing (P-02).
            'items.marketerListing.invitation.campaign.adminListing.productVariant.product.category',
            'items.marketerListing.invitation.campaign.adminListing.productVariant.product.brand',
            'items.marketerListing.invitation.campaign.adminListing.productVariant.product.images',
            'items.marketerListing.invitation.campaign.adminListing.warehouseInventories',
            // Independent marketer listings (no invitation/campaign at all).
            'items.marketerListing.productVariant',
            'items.customAttributeValues.productCustomAttribute',
        ]);

        if ($cart->items->isEmpty()) {
            return ApiResponse::error(__('common.exceptions.checkout.cart_empty'), [], 422);
        }

        // Resolve every cart item (vendor, admin, campaign-marketer or
        // independent-marketer listing) to one normalized CartLineSource —
        // enhancement.md P-02. Built once and reused through pre-check,
        // shipping, and the placement transaction below.
        $cartLineSources = [];
        foreach ($cart->items as $item) {
            $source = CartLineSource::resolve($item);

            if (! $source) {
                return ApiResponse::error(
                    __('common.exceptions.checkout.listing_not_available', ['id' => $item->id]),
                    [], 422
                );
            }

            $fulfilmentListing = $source->fulfilmentListing;
            $isAdmin = $fulfilmentListing instanceof \App\Models\AdminListing;

            if ($isAdmin) {
                if ($fulfilmentListing->status !== AdminListingStatus::Active) {
                    return ApiResponse::error(
                        __('common.exceptions.checkout.listing_not_available', ['id' => $fulfilmentListing->id]),
                        [], 422
                    );
                }
            } else {
                if ($fulfilmentListing->status !== VendorListingStatus::Active) {
                    return ApiResponse::error(
                        __('common.exceptions.checkout.listing_not_available', ['id' => $fulfilmentListing->id]),
                        [], 422
                    );
                }
            }

            $available = $source->availableQuantity();
            if ($available < $item->quantity) {
                return ApiResponse::error(
                    __('common.exceptions.checkout.insufficient_stock_available', ['available' => $available]),
                    [], 422
                );
            }

            $cartLineSources[$item->id] = $source;
        }

        $address = $customer->addresses()->find($validated['address_id']);
        if (! $address) {
            return ApiResponse::error(__('common.exceptions.checkout.address_not_found'), [], 404);
        }
        $address->load('city.shippingZone');

        $receiver = $this->resolveReceiver($customer, $validated['receiver_id'] ?? null);

        // Single gateway resolution — all payment logic derives from this
        $methodConfig = CountryPaymentGateway::where('id', $validated['country_payment_gateway_id'])
            ->where('country_id', $country->id)
            ->where('is_active', true)
            ->with('gateway')
            ->first();

        if (! $methodConfig) {
            return ApiResponse::error('Selected payment gateway is not available.', [], 422);
        }

        $gatewayCode = $methodConfig->gateway?->code;
        $gatewayType = $methodConfig->gateway?->type;
        $isCod       = $gatewayCode === 'cod';
        $isWallet    = $gatewayCode === 'wallet';
        if ($isCod && ! $this->codAvailable($address, $country)) {
            return ApiResponse::error(__('common.exceptions.checkout.cod_unavailable'), [], 422);
        }

        $cartItems = $cart->items->all();

        if ($isCod) {
            $codErrors = $this->codValidationService->validate($cartItems);
            if (!empty($codErrors)) {
                return ApiResponse::error($codErrors[0], ['errors' => $codErrors], 422);
            }
        }

        // Derive shipping per group from each item's selected_shipping_method_id
        $shippingMethodIds = collect($cartItems)
            ->pluck('selected_shipping_method_id')
            ->filter()
            ->unique()
            ->values();

        $shippingMethods = ShippingMethod::whereIn('id', $shippingMethodIds)->get()->keyBy('id');

        $groupedForShipping = collect($cartItems)->groupBy('selected_shipping_method_id');
        $totalShippingFee   = 0;
        $codExtraFee        = 0;

        foreach ($groupedForShipping as $methodId => $groupItems) {
            $method = $shippingMethods[$methodId] ?? null;
            if ($method) {
                $calc = $this->calculationService->calculateShipping(
                    $address, $country, $methodId, $groupItems->all(), $isCod
                );
                $totalShippingFee += $calc['fee'];
                $codExtraFee      += $calc['cod_extra_fee'];
            }
        }

        $shippingZone   = $address->city?->shippingZone;
        $vendorShipping = $this->resolveVendorShipping($cartItems, $cartLineSources, $totalShippingFee, $shippingZone, null);
        $shippingFeeCents = $vendorShipping['total'];
        $codFeeCents      = $isCod ? $codExtraFee : 0;

        $warrantyResult = $this->pricingEngine->resolveWarrantySelections(
            $cartItems,
            $this->buildWarrantySelectionsInput($cartItems, $validated['warranty_selections'] ?? []),
            $country,
            $cart->currency,
        );

        $subtotal = (int) collect($cartItems)->sum(fn ($i) => $i->unit_price * $i->quantity);
        $hasAffiliatePromo = (bool) $cart->affiliate_promo_code_id;

        $coupon = null;
        $discountCents = 0;
        $discountAllocations = [];
        $freeShippingDiscountCents = 0;
        if (! empty($validated['coupon_code'])) {
            $coupon = Coupon::where('code', $validated['coupon_code'])->first();
            if (! $coupon) {
                return ApiResponse::error(__('common.exceptions.checkout.invalid_coupon'), [], 422);
            }

            $couponResult = $this->couponEligibilityService->evaluate(
                $coupon, $customer, $subtotal, $cart->currency, $cartItems, $country->id, $hasAffiliatePromo
            );

            if ($couponResult['error']) {
                return ApiResponse::error($couponResult['error'], [], 422);
            }

            $discountCents = $couponResult['discount'];
            $discountAllocations = $couponResult['allocations'];
            $freeShippingDiscountCents = $this->applyFreeShippingDiscount($coupon, $couponResult, $vendorShipping);
            $shippingFeeCents = $vendorShipping['total'];
        }
        $couponDiscountCents = $discountCents;

        // ── Affiliate/marketer promo code ───────────────────────────────────────
        // is_stackable=false blocks the affiliate promo from stacking on top
        // of the coupon (enhancement.md P-04 — previously stacked unconditionally).
        if ($cart->affiliate_promo_code_id && (! $coupon || $coupon->is_stackable)) {
            $affiliatePromoCode = \App\Models\AffiliatePromoCode::find($cart->affiliate_promo_code_id);
            if ($affiliatePromoCode) {
                $promoResult = $this->calculationService->applyAffiliatePromoCode($affiliatePromoCode, $subtotal, $cart->currency);

                if (! $promoResult['error'] && $promoResult['discount'] > 0) {
                    $discountCents += $promoResult['discount'];
                    $weights = collect($cartItems)->mapWithKeys(fn ($i) => [$i->id => $i->unit_price * $i->quantity])->all();
                    foreach ($this->pricingEngine->allocateProRata($promoResult['discount'], $weights) as $k => $v) {
                        $discountAllocations[$k] = ($discountAllocations[$k] ?? 0) + $v;
                    }
                }
            }
        }

        $shippingByGroup = collect($vendorShipping['per_vendor'])->map(fn ($v) => $v['shipping'])->all();

        // Priced without loyalty — this is what `prepare` could have shown
        // the customer, so it's what we compare for price-drift detection.
        $comparablePricedCart = $this->pricingEngine->priceCart(
            $cartItems, $country, $shippingFeeCents, $codFeeCents,
            $discountCents, $discountAllocations, 0, [], 0,
            $warrantyResult['selections'], $shippingByGroup,
        );

        $preparedSignature = \Illuminate\Support\Facades\Cache::pull("checkout_prepare_signature:{$customer->id}");
        if ($preparedSignature !== null && $preparedSignature !== $comparablePricedCart->signature()) {
            return ApiResponse::error(
                __('common.exceptions.checkout.price_changed', [], 'Prices have changed since you last viewed this order.'),
                [
                    'error_code' => 'price_changed',
                    'prepared' => $preparedSignature,
                    'current' => $comparablePricedCart->signature(),
                ],
                409
            );
        }

        // ── Loyalty redemption ────────────────────────────────────────────────
        $loyaltyDiscount      = 0;
        $loyaltyPointsToUse   = 0.0;
        $loyaltyAllocations   = [];
        if (! empty($validated['loyalty_points_to_use'])) {
            $loyaltyPointsToUse = (float) $validated['loyalty_points_to_use'];
            try {
                $loyaltyDiscount = $this->loyaltyService->calculateRedemptionDiscount(
                    $customer,
                    $loyaltyPointsToUse,
                    // Pass a temporary total estimate (subtotal - coupon) for the cap check.
                    // The real cap is re-applied inside debitPointsForOrder after order creation.
                    max(0, $subtotal - $discountCents),
                );
            } catch (\Illuminate\Validation\ValidationException $e) {
                return ApiResponse::error($e->getMessage(), $e->errors(), 422);
            }

            if ($loyaltyDiscount > 0) {
                $weights = collect($cartItems)->mapWithKeys(fn ($i) => [$i->id => $i->unit_price * $i->quantity])->all();
                $loyaltyAllocations = $this->pricingEngine->allocateProRata($loyaltyDiscount, $weights);
            }
        }

        $pricedCart = $this->pricingEngine->priceCart(
            $cartItems, $country, $shippingFeeCents, $codFeeCents,
            $discountCents, $discountAllocations, $loyaltyDiscount, $loyaltyAllocations, 0,
            $warrantyResult['selections'], $shippingByGroup,
        );

        $summary = $pricedCart->toArray();
        $warrantySelections = $warrantyResult['selections'];

        // Index the engine's per-line/per-sub-order output for O(1) lookup
        // while persisting — place-order writes the engine's numbers as-is,
        // with no recalculation (P-01 task 4).
        $pricedLinesByItemId = [];
        foreach ($pricedCart->lines as $pricedLine) {
            $pricedLinesByItemId[$pricedLine->id] = $pricedLine;
        }
        $pricedSubOrdersByVendorId = $pricedCart->subOrders;

        // ── P-03: vendor/platform/marketer/shipping money split ────────────
        // Computed once here (outside the transaction, alongside everything
        // else place-order prices) and persisted as-is below, the same
        // pattern P-01 established for tax/discount.
        $chargedShippingByGroup = collect($vendorShipping['per_vendor'])->map(fn ($v) => (int) $v['shipping'])->all();
        $vendorContributionByGroup = collect($vendorShipping['per_vendor'])->map(fn ($v) => (int) $v['vendor_contribution'])->all();
        $adminSubsidyByGroup = collect($vendorShipping['per_vendor'])->map(fn ($v) => (int) $v['platform_subsidy'])->all();
        // raw_fee is only resolved for FBP lines that went through
        // ShippingSubsidyService (see resolveVendorShipping) — for FBN /
        // admin-listing groups there is no independent carrier quote in
        // this codebase yet, so computeMoneySplit() falls back to the
        // charged shipping fee for those groups (enhancement.md P-03 task 3
        // note: a real carrier-rate integration at checkout is out of scope
        // here).
        $carrierRawFeeByGroup = collect($vendorShipping['per_vendor'])
            ->filter(fn ($v) => ($v['raw_fee'] ?? 0) > 0)
            ->map(fn ($v) => (int) $v['raw_fee'])
            ->all();

        // Same wallet-amount resolution place-order uses later (line ~1032)
        // to decide how much of the order is actually settled through the
        // card gateway (D4: gateway fee is only charged on that portion).
        $walletAmountForGatewayCents = (int) ($validated['wallet_amount_used'] ?? $validated['wallet_amount_to_use'] ?? ($isWallet ? $pricedCart->total : 0));
        $amountDueGatewayCents = ($isCod || $isWallet) ? 0 : max(0, $pricedCart->total - $walletAmountForGatewayCents);

        $moneySplit = $this->pricingEngine->computeMoneySplit(
            items: $cartItems,
            country: $country,
            coupon: $coupon,
            couponAllocations: $discountAllocations,
            vendorContributionByGroup: $vendorContributionByGroup,
            adminSubsidyByGroup: $adminSubsidyByGroup,
            carrierRawFeeByGroup: $carrierRawFeeByGroup,
            chargedShippingByGroup: $chargedShippingByGroup,
            gatewayFeePct: $isCod ? 0.0 : (float) $methodConfig->fee_pct,
            gatewayFeeFixed: $isCod ? 0 : (int) $methodConfig->fee_fixed,
            amountDueGatewayCents: $amountDueGatewayCents,
            isCod: $isCod,
            codFeeCents: $codFeeCents,
            warrantyTotalCents: $pricedCart->warrantyTotal,
        );
        $moneySplitByGroup = $moneySplit['sub_orders'];

        if ($isWallet) {
            $wallet = CustomerWallet::where('customer_id', $customer->id)->first();

            if (! $wallet || $wallet->currency_code !== $summary['currency']) {
                return ApiResponse::error(__('common.exceptions.checkout.wallet_currency_mismatch'), [], 422);
            }

            if ($wallet->balance < $summary['total']) {
                return ApiResponse::error(
                    __('common.exceptions.checkout.insufficient_wallet_balance'),
                    ['balance' => $wallet->balance, 'required' => $summary['total']],
                    422
                );
            }
        }

        $idempotencyRecord = $existingIdempotency ?? \App\Models\IdempotencyKey::create([
            'key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'operation_type' => 'place_order',
            'expires_at' => now()->addDay(),
        ]);

        try {
            $result = DB::transaction(function () use (
                $customer, $country, $address, $receiver, $validated, $coupon,
                $cartItems, $summary, $vendorShipping, $moneySplitByGroup,
                $warrantySelections, $cart, $couponDiscountCents,
                $loyaltyDiscount, $loyaltyPointsToUse,
                $gatewayCode, $isCod, $isWallet, $methodConfig,
                $pricedLinesByItemId, $pricedSubOrdersByVendorId, $cartLineSources,
                $hasAffiliatePromo
            ) {
                $vendorShippingMap = $vendorShipping['per_vendor'];
                $order = Order::create([
                    'order_number' => $this->generateOrderNumber(),
                    'customer_id' => $customer->id,
                    'country_id' => $country->id,
                    'status' => 'placed',
                    'currency' => $country->currency_code,
                    'subtotal' => $summary['subtotal'],
                    'discount' => $summary['discount'],
                    'shipping' => $summary['shipping'],
                    'tax' => $summary['tax'],
                    'cod_fee' => $summary['cod_fee'],
                    'warranty_total' => $summary['warranty_total'],
                    'total' => $summary['total'],
                    'loyalty_discount' => $loyaltyDiscount,
                    'loyalty_points_used' => $loyaltyPointsToUse,
                    'coupon_id' => $coupon?->id,
                    'coupon_code_used' => $coupon?->code,
                    // enhancement.md P-05 task 2: orders.payment_method must
                    // stay within its enum('card','wallet','cod','bnpl',
                    // 'bank_transfer') — writing the raw gateway code here
                    // (e.g. 'thawani', 'paytabs') failed the insert under
                    // strict-mode MySQL for every card-gateway order. The
                    // actual gateway is preserved in payment_gateway_code.
                    'payment_method' => PaymentMethodMapper::toOrderPaymentMethod($gatewayCode, $methodConfig->gateway?->type),
                    'payment_gateway_code' => $gatewayCode,
                    'payment_status' => 'pending',
                    'shipping_address_snapshot' => $this->buildAddressSnapshot($address, $receiver),
                    'customer_notes' => $validated['customer_notes'] ?? null,
                    'delivery_instruction' => $validated['delivery_instruction'] ?? null,
                    'ip_address' => request()->ip() ?? '0.0.0.0',
                    'user_agent' => request()->userAgent(),
                    'placed_at' => now(),
                    // enhancement.md P-12: 'marketer_id'/'marketer_campaign_id'
                    // do not exist on `orders` and were silently dropped by
                    // Order::create() — removed. Attribution is now resolved
                    // per order item (order_items.marketer_listing_id /
                    // marketer_campaign_invitation_id) below, after this
                    // transaction, by LastClickAttributionService.
                    'marketer_contract_acceptance_id' => $validated['contract_acceptance_id'] ?? null,
                ]);

                if (! empty($validated['contract_acceptance_id'])) {
                    \App\Models\MarketerContractAcceptance::where('id', $validated['contract_acceptance_id'])
                        ->where('customer_id', $customer->id)
                        ->whereNull('order_id')
                        ->update(['order_id' => $order->id]);
                }

                $shippingMethodCache = [];
                $resolveShippingMethod = function (?string $shippingMethodId) use (&$shippingMethodCache) {
                    if (! $shippingMethodId) {
                        return null;
                    }

                    if (! array_key_exists($shippingMethodId, $shippingMethodCache)) {
                        $shippingMethodCache[$shippingMethodId] = ShippingMethod::find($shippingMethodId);
                    }

                    return $shippingMethodCache[$shippingMethodId];
                };

                // Group by seller_party + shipping_method (+ warehouse, via
                // the reserved inventory below) rather than
                // `$item->vendorListing->vendor_id`, so admin-listing and
                // marketer-listing lines group correctly instead of
                // crashing on a null vendorListing (enhancement.md P-02).
                // Grouped by seller_party + shipping_method only (not also
                // warehouse): CheckoutPricingEngine's PricedSubOrder — whose
                // tax/subtotal this transaction persists as-is per P-01 — is
                // itself only keyed by vendor/platform, so splitting a
                // sub-order further by warehouse here would duplicate that
                // single priced-sub-order tax figure across multiple rows.
                $grouped = collect($cartItems)->groupBy(
                    fn ($item) => $cartLineSources[$item->id]->groupKey($item->selected_shipping_method_id)
                );
                $subOrders = [];
                $idx = 0;

                foreach ($grouped as $groupKey => $items) {
                    $idx++;
                    $firstSource = $cartLineSources[$items->first()->id];
                    $sellerParty = $firstSource->sellerParty;
                    $isPlatformSubOrder = $firstSource->isAdminSeller();
                    $vendorId = $isPlatformSubOrder ? null : $sellerParty;
                    $subOrderShippingMethodId = $items->first()->selected_shipping_method_id;
                    $subOrderShippingMethod = $resolveShippingMethod($subOrderShippingMethodId);
                    $vendorSubtotal = (int) $items->sum(fn ($i) => $i->unit_price * $i->quantity);
                    $fulfillmentModel = $firstSource->fulfillmentModel;

                    $vendorShippingCents = $vendorShippingMap[$sellerParty]['shipping'] ?? 0;
                    $vendorAdminSubsidyCents = $vendorShippingMap[$sellerParty]['platform_subsidy'] ?? 0;
                    $vendorContributionCents = $vendorShippingMap[$sellerParty]['vendor_contribution'] ?? 0;
                    $vendorBillableWeightGrams = $vendorShippingMap[$sellerParty]['billable_weight_grams'] ?? null;

                    // Sub-order tax is the engine's number, not recomputed here — it
                    // is by construction the sum of this group's line taxes
                    // (including warranty tax), so Σ sub_orders.tax == orders.tax
                    // always holds (enhancement.md P-01 / D2). The engine groups
                    // platform (admin-listing) lines under the 'platform' key.
                    $pricedSubOrder = $pricedSubOrdersByVendorId[$isPlatformSubOrder ? 'platform' : $sellerParty] ?? null;
                    $vendorTax = $pricedSubOrder?->tax ?? 0;

                    // enhancement.md P-03: the vendor/platform/marketer money
                    // split (commission, gateway fee, coupon funding,
                    // marketer commission, vendor payout) is computed once
                    // by CheckoutPricingEngine::computeMoneySplit() above and
                    // persisted here as-is, the same "no recalculation"
                    // pattern P-01 established for tax. commission_rate_pct/
                    // commission_fixed on order_items remain a display-only
                    // snapshot from the legacy per-line resolver (fed by
                    // CheckoutCalculationService::calculateCommission);
                    // commission_amount/vendor_coupon_cost/marketer_commission/
                    // platform_commission_after_discount below are the
                    // engine's numbers, the actual money.
                    $groupSplit = $moneySplitByGroup[$isPlatformSubOrder ? 'platform' : $sellerParty] ?? null;
                    $totalCommission = $groupSplit['platform_commission'] ?? 0;
                    $totalCommissionAfterDiscount = $groupSplit['platform_commission_after_discount'] ?? 0;
                    $vendorCouponCostForSubOrder = $groupSplit['vendor_coupon_cost'] ?? 0;
                    $platformCouponCostForSubOrder = $groupSplit['platform_coupon_cost'] ?? 0;
                    $marketerCommissionForSubOrder = $groupSplit['marketer_commission'] ?? 0;
                    $marketerCommissionOwnerForSubOrder = $groupSplit['marketer_commission_owner'] ?? null;
                    $warrantyRevenueForSubOrder = 0; // set below once tax/warranty totals for this group are known
                    $carrierShippingCostForSubOrder = $groupSplit['carrier_shipping_cost'] ?? $vendorShippingCents;
                    $shippingGapForSubOrder = $groupSplit['shipping_gap'] ?? 0;
                    $vendorPayoutForSubOrder = $groupSplit['vendor_payout'] ?? 0;
                    $gatewayFeeForSubOrder = $groupSplit['gateway_fee'] ?? 0;

                    $itemCommissions = [];
                    $reservedInventories = [];
                    foreach ($items as $cartItem) {
                        $itemSource = $cartLineSources[$cartItem->id];
                        $fulfilmentListing = $itemSource->fulfilmentListing;

                        if ($fulfilmentListing instanceof VendorListing) {
                            $commission = $this->calculationService->calculateCommission(
                                $fulfilmentListing,
                                $cartItem->quantity,
                                $cartItem->unit_price,
                                $country
                            );
                        } else {
                            // Platform (admin-listing) sub-orders don't pay
                            // platform commission to themselves.
                            $commission = [
                                'commission_rate_pct' => 0.0,
                                'commission_fixed' => 0,
                                'commission_amount' => 0,
                                'commission_category_id' => null,
                                'vendor_payout_share' => $cartItem->unit_price * $cartItem->quantity,
                            ];
                        }
                        $lineSplit = $groupSplit['lines'][$cartItem->id] ?? null;
                        $commission['commission_amount'] = $lineSplit['raw_commission'] ?? $commission['commission_amount'];
                        $commission['vendor_coupon_cost'] = $lineSplit['vendor_coupon_cost'] ?? 0;
                        $commission['marketer_commission'] = $lineSplit['marketer_commission'] ?? 0;
                        $commission['platform_commission_after_discount'] = $lineSplit['platform_commission_after_discount'] ?? 0;
                        $itemCommissions[$cartItem->id] = $commission;

                        // enhancement.md P-13 task 1/3: reserve through the
                        // single InventoryService instead of locking only
                        // the listing's first warehouse row. reserve()
                        // locks every candidate row for this listing, picks
                        // by most-available-first, and splits across rows
                        // when one row cannot cover the whole quantity —
                        // works for vendor AND admin listings.
                        $allocations = $this->inventoryService->reserve(
                            $fulfilmentListing,
                            (int) $cartItem->quantity,
                            'order',
                            $order->id,
                            actorType: 'customer',
                            actorId: $customer->id,
                            reason: 'Checkout reservation',
                        );

                        $reservedInventories[$cartItem->id] = $allocations;
                    }

                    $firstAllocation = $reservedInventories[$items->first()->id][0];
                    $warehouseId = WarehouseInventory::where('id', $firstAllocation['warehouse_inventory_id'])->value('warehouse_id');

                    // Gateway fee rate, stored for audit/recalculation
                    // transparency (unchanged meaning) — the actual fee
                    // amount charged to this sub-order is
                    // $gatewayFeeForSubOrder, the engine's pro-rata split of
                    // one order-level fee (D4), not a per-sub-order
                    // recomputation.
                    $gatewayFeeRatePct = $isCod ? 0.0 : (float) $methodConfig->fee_pct;
                    $warrantyRevenueForSubOrder = $pricedSubOrder?->warrantyTotal ?? 0;

                    $subOrder = SubOrder::create([
                        'order_id' => $order->id,
                        'sub_order_number' => $order->order_number.'-'.str_pad((string) $idx, 2, '0', STR_PAD_LEFT),
                        'vendor_id' => $vendorId,
                        // enhancement.md P-02 task 4: platform (admin-listing)
                        // sub-orders carry seller_type='platform' and a null
                        // vendor_id instead of being mis-attributed to a vendor.
                        'seller_type' => $isPlatformSubOrder ? 'platform' : 'vendor',
                        'warehouse_id' => $warehouseId,
                        'status' => 'placed',
                        'fulfillment_model' => $fulfillmentModel,
                        'subtotal' => $vendorSubtotal,
                        'shipping' => $vendorShippingCents,
                        // enhancement.md P-03 task 3: populated from the
                        // shipping resolver's raw (pre-subsidy) carrier fee
                        // when available (FBP via ShippingSubsidyService),
                        // else falls back to the charged shipping fee — see
                        // the note where $carrierRawFeeByGroup is built.
                        'carrier_shipping_cost' => $carrierShippingCostForSubOrder,
                        'shipping_gap' => $shippingGapForSubOrder,
                        'admin_subsidy_amount' => $vendorAdminSubsidyCents,
                        'vendor_contribution_amount' => $vendorContributionCents,
                        'billable_weight_grams' => $vendorBillableWeightGrams,
                        'tax' => $vendorTax,
                        'platform_commission' => $totalCommissionAfterDiscount,
                        'vendor_coupon_cost' => $vendorCouponCostForSubOrder,
                        'platform_coupon_cost' => $platformCouponCostForSubOrder,
                        'marketer_commission' => $marketerCommissionForSubOrder,
                        'marketer_commission_owner' => $marketerCommissionOwnerForSubOrder,
                        'warranty_revenue' => $warrantyRevenueForSubOrder,
                        'gateway_fee' => $gatewayFeeForSubOrder,
                        'gateway_fee_rate' => $gatewayFeeRatePct,
                        'vendor_payout' => $vendorPayoutForSubOrder,
                        'shipping_method_id' => $subOrderShippingMethodId,
                        'estimated_delivery_date' => $subOrderShippingMethod
                            ? $this->calculateEstimatedDeliveryDate($subOrderShippingMethod)
                            : null,
                        'sla_ship_deadline' => now()->addHours(24),
                    ]);

                    foreach ($items as $cartItem) {
                        $itemSource = $cartLineSources[$cartItem->id];
                        $listing = $itemSource->fulfilmentListing;
                        $commission = $itemCommissions[$cartItem->id];
                        $pricedLine = $pricedLinesByItemId[$cartItem->id] ?? null;
                        $lineSubtotal = $pricedLine?->lineSubtotal ?? ($cartItem->unit_price * $cartItem->quantity);
                        $lineDiscount = $pricedLine?->lineDiscount ?? 0;
                        // D2: tax = round((line_subtotal - line_discount) * vat%),
                        // computed once by CheckoutPricingEngine and persisted as-is
                        // (no recalculation here — enhancement.md P-01 task 4).
                        $lineTax = $pricedLine?->lineTax ?? 0;
                        $lineTotal = $lineSubtotal - $lineDiscount + $lineTax;

                        $itemShippingMethod = $resolveShippingMethod($cartItem->selected_shipping_method_id);
                        $itemShippingMethodSnapshot = $itemShippingMethod
                            ? $this->buildShippingMethodSnapshot($itemShippingMethod)
                            : null;

                        $productSnapshot = $this->buildProductSnapshot($listing);
                        $productSnapshot['shipping_method'] = $itemShippingMethodSnapshot;

                        if ($itemSource->marketerListingIdForOrderItem !== null) {
                            $productSnapshot['listing_type'] = 'marketer';
                            $productSnapshot['marketer_listing_id'] = $itemSource->marketerListingIdForOrderItem;
                            $productSnapshot['referral_code'] = $cartItem->marketerListing?->referral_code;
                        }

                        $orderItem = OrderItem::create([
                            'order_id' => $order->id,
                            'sub_order_id' => $subOrder->id,
                            'product_variant_id' => $listing->product_variant_id,
                            // Correctly writes vendor_listing_id / admin_listing_id /
                            // marketer_listing_id from the resolved CartLineSource
                            // instead of always assuming a vendor listing
                            // (enhancement.md P-02 tasks 1 & 3).
                            'vendor_listing_id' => $itemSource->vendorListingIdForOrderItem,
                            'admin_listing_id' => $itemSource->adminListingIdForOrderItem,
                            'marketer_listing_id' => $itemSource->marketerListingIdForOrderItem,
                            'product_snapshot' => $productSnapshot,
                            'vendor_id' => $isPlatformSubOrder ? null : $listing->vendor_id,
                            'sku' => $listing->productVariant->sku,
                            'quantity' => $cartItem->quantity,
                            'unit_price' => $cartItem->unit_price,
                            'unit_cost_price' => $listing->cost_price,
                            'line_subtotal' => $lineSubtotal,
                            'line_discount' => $lineDiscount,
                            'line_tax' => $lineTax,
                            'line_total' => $lineTotal,
                            'commission_rate_pct' => $commission['commission_rate_pct'],
                            'commission_fixed' => $commission['commission_fixed'],
                            'commission_category_id' => $commission['commission_category_id'],
                            'commission_amount' => $commission['commission_amount'],
                            'vendor_coupon_cost' => $commission['vendor_coupon_cost'] ?? 0,
                            'marketer_commission' => $commission['marketer_commission'] ?? 0,
                            'platform_commission_after_discount' => $commission['platform_commission_after_discount'] ?? 0,
                            'shipping_method_id' => $cartItem->selected_shipping_method_id,
                            'shipping_method_snapshot' => $itemShippingMethodSnapshot,
                            'fulfillment_status' => 'pending',
                            'return_eligible_until' => null,
                        ]);

                        // enhancement.md P-13 task 2: persist exactly which
                        // warehouse_inventory row(s) were reserved for this
                        // order_item, so release/commit/return never have
                        // to re-derive the row from listing + warehouse_id.
                        foreach ($reservedInventories[$cartItem->id] as $allocation) {
                            \App\Models\OrderItemAllocation::create([
                                'order_item_id' => $orderItem->id,
                                'warehouse_inventory_id' => $allocation['warehouse_inventory_id'],
                                'quantity' => $allocation['quantity'],
                                'status' => 'reserved',
                            ]);
                        }

                        // Snapshot any customer-entered custom attribute values from the
                        // cart item onto the order item. Snapshotting label/unit here
                        // means later edits/deletion of the ProductCustomAttribute
                        // definition never change historical order display. This works
                        // for both vendor-listing and admin-listing cart items since the
                        // relation is keyed on cart_item_id, not listing type.
                        foreach ($cartItem->customAttributeValues as $cartAttrValue) {
                            $definition = $cartAttrValue->productCustomAttribute;

                            OrderItemCustomAttributeValue::create([
                                'order_item_id' => $orderItem->id,
                                'product_custom_attribute_id' => $cartAttrValue->product_custom_attribute_id,
                                'label' => $definition?->label ?? '',
                                'unit' => $definition?->unit,
                                'value' => $cartAttrValue->value,
                            ]);
                        }

                        if (isset($warrantySelections[$cartItem->id])) {
                            $plan = $warrantySelections[$cartItem->id]['plan'];
                            $resolvedWarrantyPrice = $warrantySelections[$cartItem->id]['price'];

                            $warrantyPurchase = WarrantyPurchase::create([
                                'customer_id' => $customer->id,
                                'order_id' => $order->id,
                                'order_item_id' => $orderItem->id,
                                'warranty_plan_id' => $plan->id,
                                'plan_snapshot' => [
                                    'name_en' => $plan->name_en,
                                    'name_ar' => $plan->name_ar,
                                    'duration_months' => $plan->duration_months,
                                    'features_en' => $plan->features_en,
                                    'features_ar' => $plan->features_ar,
                                    'price' => $plan->price,
                                    'price_type' => $plan->price_type,
                                    'price_pct' => $plan->price_pct,
                                    'currency' => $plan->currency,
                                    'resolved_price' => $resolvedWarrantyPrice,
                                ],
                                'price_paid' => $resolvedWarrantyPrice,
                                'currency' => $order->currency,
                                'status' => 'pending',
                                'coverage_starts_at' => null,
                                'coverage_ends_at' => null,
                            ]);

                            $orderItem->update(['warranty_purchase_id' => $warrantyPurchase->id]);
                        }
                    }

                    $subOrders[] = $subOrder;
                }

                $walletAmountToUse = (int) ($validated['wallet_amount_used'] ?? $validated['wallet_amount_to_use'] ?? ($isWallet ? $order->total : 0));
                if ($walletAmountToUse > 0) {
                    $wallet = CustomerWallet::where('customer_id', $customer->id)->first();
                    if (! $wallet || $wallet->currency_code !== $order->currency) {
                        throw new GiftCardCurrencyMismatchException(
                            __('common.exceptions.checkout.wallet_currency_mismatch')
                        );
                    }

                    if ($walletAmountToUse > $order->total) {
                        throw new \InvalidArgumentException(__('common.exceptions.checkout.wallet_exceeds_total'));
                    }

                    // enhancement.md P-05 task 7: COD does not support a
                    // partial wallet top-up — cash is collected at delivery
                    // for the full remainder, or the customer pays the
                    // whole order from the wallet up front. This check runs
                    // AFTER order/sub-order/inventory rows already exist
                    // within this DB::transaction(), but that's safe: the
                    // whole transaction (including those inserts and the
                    // stock reservation increments) rolls back when this
                    // throws, so nothing is left half-committed.
                    if ($isCod && $walletAmountToUse < $order->total) {
                        throw new \InvalidArgumentException(
                            __('common.exceptions.checkout.cod_wallet_rule')
                        );
                    }

                    $this->checkoutWalletService->applyWalletToOrder($customer, $order, $walletAmountToUse);
                    $order->refresh();

                    $remainingToPay = $order->total - $walletAmountToUse;
                    if ($remainingToPay === 0) {
                        $order->update([
                            'payment_method' => 'wallet',
                            'payment_gateway_code' => 'wallet',
                            'payment_status' => 'captured',
                        ]);
                        // enhancement.md P-05 task 3: a wallet-only order
                        // previously created no payment_transactions row,
                        // so a retried place-order request (same
                        // idempotency_key) could not be detected by the
                        // old PaymentTransaction-only dedupe check and
                        // would debit the wallet a second time. Recording
                        // one here — in addition to the idempotency_keys
                        // row above — keeps both dedupe paths consistent.
                        PaymentTransaction::create([
                            'id' => (string) Str::uuid(),
                            'order_id' => $order->id,
                            'customer_id' => $customer->id,
                            'type' => 'sale',
                            'gateway' => 'wallet',
                            'gateway_transaction_id' => 'WALLET-'.$order->order_number,
                            'idempotency_key' => $validated['idempotency_key'],
                            'amount' => $walletAmountToUse,
                            'currency' => $order->currency,
                            'gateway_amount' => $walletAmountToUse,
                            'gateway_currency' => $order->currency,
                            'exchange_rate' => 1,
                            'status' => 'succeeded',
                            'processed_at' => now(),
                        ]);
                        // enhancement.md P-03 task 5: ledger at capture.
                        $this->ledgerService->postOrderCapture($order, $walletAmountToUse);
                    }
                }

                if ($coupon) {
                    // Re-validate at placement time — coupon may have expired,
                    // hit its usage limit, or become otherwise invalid since it
                    // was applied to the cart (enhancement.md P-04 task 2).
                    $reCheck = $this->couponEligibilityService->evaluate(
                        $coupon, $customer, $summary['subtotal'], $cart->currency, $cartItems, $country->id, $hasAffiliatePromo
                    );
                    if ($reCheck['error']) {
                        throw new \DomainException(
                            __('common.exceptions.checkout.coupon_no_longer_valid', ['reason' => $reCheck['error']])
                        );
                    }

                    // Locks the coupon row (SELECT ... FOR UPDATE) and
                    // atomically re-checks + increments usage_limit_total,
                    // so two concurrent placements against a coupon with
                    // usage_limit_total=1 cannot both succeed.
                    try {
                        $this->couponUsageService->reserve($coupon->id, $customer, $order, $couponDiscountCents);
                    } catch (CouponNoLongerValidException $e) {
                        throw new \DomainException(
                            __('common.exceptions.checkout.coupon_no_longer_valid', ['reason' => $e->getMessage()])
                        );
                    }

                    // Wallet already fully captured the order above (before
                    // any gateway call) — consume the reservation now rather
                    // than leaving it 'reserved' until a webhook that will
                    // never arrive for this payment method.
                    if ($order->payment_status->value === 'captured') {
                        $this->couponUsageService->consumeForOrder($order);
                    }
                }

                // ── Debit loyalty points ──────────────────────────────────────
                if ($loyaltyPointsToUse > 0 && $loyaltyDiscount > 0) {
                    $this->loyaltyService->debitPointsForOrder(
                        $customer,
                        $order,
                        $loyaltyPointsToUse,
                        $loyaltyDiscount,
                    );
                }

                return [
                    'order' => $order,
                    'sub_orders' => $subOrders,
                    'wallet_fully_paid' => $order->payment_status->value === 'captured',
                    'wallet_amount_used' => $walletAmountToUse,
                ];
            });
        } catch (\DomainException|InsufficientWalletBalanceException|GiftCardCurrencyMismatchException|\InvalidArgumentException $e) {
            $idempotencyRecord->update(['response_status' => 422, 'response_body' => ['message' => $e->getMessage()]]);

            return ApiResponse::error($e->getMessage(), [], 422);
        }

        $subOrders = $result['sub_orders'];
        $order = $result['order'];

        $sessionId = $request->header('X-Session-Id')
            ?? $request->cookie('session_id')
            ?? session()->getId();
        $this->attributionService->resolveAndRecordConversion($order, $sessionId);

        $paymentRedirectUrl = null;
        $bankTransferDetails = null;

        if ($result['wallet_fully_paid']) {
            // Wallet covered the full order total inside the transaction above — no COD
            // collection and no external payment gateway call needed.
        } elseif ($isWallet) {
            // Customer selected the wallet gateway but the transaction above didn't fully
            // capture it (e.g. balance changed between the pre-check and the row lock).
            // Wallet is handled internally and isn't in PaymentGatewayFactory's map, so it
            // must never fall through to initiatePayment() below.
            Log::error('Wallet payment did not fully cover order total at settlement time', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_id' => $customer->id,
            ]);
            $order->update(['payment_status' => 'failed', 'status' => 'cancelled']);
            $this->rollbackService->rollback($order, 'Wallet did not fully cover order at settlement');
        } elseif ($isCod) {
            // Cash hasn't changed hands yet — this transaction (and order.payment_status,
            // already 'pending' from creation above) only becomes 'succeeded'/'captured' once
            // the delivery agent actually collects payment (see AssignmentController::confirmDelivery).
            PaymentTransaction::create([
                'id' => (string) Str::uuid(),
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'type' => 'sale',
                'gateway' => 'cod',
                'gateway_transaction_id' => 'COD-'.$order->order_number,
                'idempotency_key' => $validated['idempotency_key'],
                'amount' => $order->total,
                'currency' => $order->currency,
                'status' => 'pending',
                'processed_at' => null,
            ]);
        } else {
            // enhancement.md P-05 task 2 (split wallet + card): charge the
            // gateway only for what the wallet didn't already cover —
            // charging $order->total here double-charged the customer for
            // the wallet portion applyWalletToOrder() already debited above.
            $gatewayAmountCents = max(0, $order->total - (int) $result['wallet_amount_used']);
            try {
                $paymentResult = $this->paymentService->initiatePayment($order, $methodConfig, $validated['idempotency_key'], $gatewayAmountCents);
                if (! $paymentResult->success) {
                    Log::error('Payment gateway declined order', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'gateway_code' => $gatewayCode,
                        'error' => $paymentResult->errorMessage ?? null,
                    ]);
                    $order->update(['payment_status' => 'failed', 'status' => 'cancelled']);
                    $this->rollbackService->rollback($order, 'Payment gateway declined order');
                } else {
                    $paymentRedirectUrl = $paymentResult->redirectUrl;
                    if ($gatewayCode === 'bank_transfer') {
                        $bankTransferDetails = $paymentResult->rawResponse;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Payment initiation threw an exception', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'gateway_code' => $gatewayCode,
                    'exception' => $e->getMessage(),
                ]);
                $order->update(['payment_status' => 'failed', 'status' => 'cancelled']);
                $this->rollbackService->rollback($order, 'Payment initiation threw an exception');
            }
        }

        // enhancement.md P-05 task 4: only clear the cart once the order is
        // placed AND the payment either captured or is legitimately still
        // pending (COD collection, bank-transfer awaiting admin
        // confirmation, or a redirect gateway flow the customer hasn't
        // finished yet) — never when we just rolled the order back to
        // 'cancelled' above, so a declined/erroring payment doesn't also
        // cost the customer their cart.
        $orderStatusValue = $order->status instanceof \App\Enums\OrderStatus ? $order->status->value : $order->status;
        if ($orderStatusValue !== 'cancelled') {
            $this->cartService->clearCart($cart);
        }

        foreach ($subOrders as $subOrder) {
            dispatch(new AutoAssignShippingMethodJob($subOrder->id))->delay(now()->addHours(12));
            SubOrderPlaced::dispatch($subOrder);
            // Platform (admin-listing) sub-orders have no vendor to notify
            // (enhancement.md P-02 task 4).
            if ($subOrder->vendor_id !== null) {
                NotifyVendorJob::dispatch($order->id, $subOrder->vendor_id);
            }
        }
        OrderConfirmationEmailJob::dispatch($order->id);
        FraudDetectionJob::dispatch($order->id);

        $order = $order->fresh();
        $order->load('subOrders.items.vendorListing');

        $responseData = (new PlaceOrderResultResource($order))->toArray($request);
        $responseData['payment_redirect_url'] = $paymentRedirectUrl ?: null;
        $responseData['requires_redirect'] = ! empty($paymentRedirectUrl);
        $responseData['bank_transfer_details'] = $bankTransferDetails;

        $idempotencyRecord->update([
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'response_status' => 201,
            'response_body' => $responseData,
        ]);

        return ApiResponse::success($responseData, __('common.exceptions.checkout.order_placed'), 201);
    }

    public function confirmation(Request $request,$country, string $orderNumber): JsonResponse
    {
        $customer = auth('customer')->user();

        $order = Order::with(['subOrders.items.vendorListing', 'subOrders.vendor'])
            ->where('order_number', $orderNumber)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        return ApiResponse::success(new OrderResource($order));
    }

    private function codAvailable(Address $address, $country): bool
    {
        $address->loadMissing('city');

        return (bool) ($address->city?->cod_available && $country->cod_available);
    }

    /**
     * Marketer-listing cart items (marketer_listing_id set, vendor_listing_id
     * and admin_listing_id null — see CartService::addMarketerItem /
     * MarketerListing docblock) carry no inventory or vendor of their own:
     * fulfillment rides on the underlying campaign's source listing. Resolve
     * that source VendorListing and cache it onto the CartItem's
     * `vendorListing` relation so the rest of checkout (stock checks,
     * shipping, commission, coupon scoping, snapshots) can keep treating the
     * item exactly like a normal vendor-listing line, without a separate
     * code path.
     *
     * Campaigns sourced from an AdminListing (platform stock) have no vendor
     * to attribute a sub-order to and are left unresolved here; those items
     * still fail with the pre-existing "listing not available" checkout
     * error rather than being silently mis-attributed.
     *
     * @param  iterable<\App\Models\CartItem>  $cartItems
     */
    private function resolveMarketerCartItems(iterable $cartItems): void
    {
        foreach ($cartItems as $item) {
            if ($item->marketer_listing_id === null || $item->vendorListing !== null) {
                continue;
            }

            $campaign = $item->marketerListing?->invitation?->campaign;
            $sourceListing = $campaign?->vendor_listing_id ? $campaign->vendorListing : null;

            if ($sourceListing) {
                $item->setRelation('vendorListing', $sourceListing);
            }
        }
    }

    /**
     * enhancement.md P-09 task 1: `cart_items.warranty_plan_id` is the single
     * source of truth for warranty selection. Any `warranty_selections`
     * passed on the checkout request is an override on top of the cart's
     * defaults, and — because it is an override, not a parallel input — it
     * also writes back onto the cart item so the two never diverge again.
     *
     * @param  iterable<\App\Models\CartItem>  $cartItems
     * @param  array<int|string, mixed>  $overrideSelections
     * @return array<string, array{warranty_plan_id: ?string}>
     */
    private function buildWarrantySelectionsInput(iterable $cartItems, array $overrideSelections): array
    {
        $itemsById = collect($cartItems)->keyBy('id');

        $selections = [];
        foreach ($itemsById as $item) {
            if ($item->warranty_plan_id) {
                $selections[$item->id] = ['warranty_plan_id' => $item->warranty_plan_id];
            }
        }

        foreach ($overrideSelections as $key => $selection) {
            $planId = is_array($selection) ? ($selection['warranty_plan_id'] ?? null) : $selection;
            $cartItemId = is_array($selection) && isset($selection['listing_id']) ? null : (string) $key;

            if ($cartItemId !== null) {
                /** @var \App\Models\CartItem|null $cartItem */
                $cartItem = $itemsById->get($cartItemId);
                $cartItem?->update(['warranty_plan_id' => $planId]);
            }

            if ($planId === null) {
                if ($cartItemId !== null) {
                    unset($selections[$cartItemId]);
                }
                continue;
            }

            $selections[$key] = $selection;
        }

        return $selections;
    }

    /**
     * Resolve per-vendor shipping (FBN vendors ship free). FBP vendor fees
     * go through ShippingSubsidyService for billable-weight-based fees and
     * platform/vendor subsidy splitting; a flat warehouse surcharge is added
     * on top for each cart line whose fulfilling warehouse has one configured.
     *
     * @param  array<\App\Models\CartItem>  $cartItems
     * @return array{total: int, per_vendor: array<string, array{shipping: int, surcharge: int, raw_fee: int, platform_subsidy: int, vendor_contribution: int, billable_weight_grams: int, is_free_by_platform: bool, is_free_by_vendor: bool}>}
     */
    private function resolveVendorShipping(
        array $cartItems,
        array $cartLineSources,
        int $baseFeeCents,
        ?ShippingZone $zone = null,
        ?ShippingMethod $method = null,
    ): array {
        $subtotalAll = max(1, (int) collect($cartItems)->sum(fn ($i) => $i->unit_price * $i->quantity));
        // Group by seller_party (vendor id, or 'platform' for admin-listing /
        // platform-fulfilled marketer lines) rather than the raw
        // vendorListing relation, so admin and marketer lines are grouped
        // correctly instead of crashing on a null vendorListing (P-02).
        $grouped = collect($cartItems)->groupBy(fn ($item) => $cartLineSources[$item->id]->sellerParty);

        $totalCents = 0;
        $perVendor = [];

        foreach ($grouped as $vendorId => $items) {
            $vendorSubtotal = (int) $items->sum(fn ($i) => $i->unit_price * $i->quantity);
            $firstSource = $cartLineSources[$items->first()->id];
            $isPlatform = $firstSource->isAdminSeller();
            $firstListing = $firstSource->fulfilmentListing;
            $isFbn = $isPlatform || ($firstListing instanceof \App\Models\VendorListing && $firstListing->global_system_type === GlobalSystemType::ExpressFbn);
            $isFbp = ! $isPlatform && $firstListing instanceof \App\Models\VendorListing && $firstListing->global_system_type === GlobalSystemType::MerchantFbp;

            $subsidyBreakdown = null;
            $vendorBaseShippingCents = 0;

            if ($isFbp && $zone && $method) {
                $subsidyBreakdown = $this->shippingSubsidyService->resolve($items, $zone, $method);
                $vendorBaseShippingCents = $subsidyBreakdown['customer_pays'];
            } elseif ($isFbp) {
                // No resolvable zone/method (should not happen once an address is set) - fall back
                // to the previous proportional split so shipping is never silently dropped.
                $vendorBaseShippingCents = (int) round($baseFeeCents * ($vendorSubtotal / $subtotalAll));
            }

            $surchargeCents = 0;
            if ($isFbp) {
                foreach ($items as $cartItem) {
                    $warehouseId = $this->resolveCartItemWarehouseId($cartLineSources[$cartItem->id]);
                    $surchargeCents += $this->cityShippingSurchargeService->resolveSurcharge($vendorId, $warehouseId);
                }
            } elseif ($isFbn) {
                // Configurable base fee — defaults to 0 (free, platform bears the cost)
                // until a business decision sets FBN_BASE_SHIPPING_FEE.
                $vendorBaseShippingCents = (int) config('checkout.fbn_base_shipping_fee', 0);

                foreach ($items as $cartItem) {
                    $warehouseId = $this->resolveCartItemWarehouseId($cartLineSources[$cartItem->id]);
                    $surchargeCents += $this->warehouseShippingSurchargeService->resolveSurcharge($warehouseId);
                }
            }

            $vendorShippingCents = $vendorBaseShippingCents + $surchargeCents;
            $totalCents += $vendorShippingCents;

            $perVendor[$vendorId] = [
                'shipping' => $vendorShippingCents,
                'surcharge' => $surchargeCents,
                'raw_fee' => $subsidyBreakdown['raw_fee'] ?? 0,
                'platform_subsidy' => $subsidyBreakdown['platform_subsidy'] ?? 0,
                'vendor_contribution' => $subsidyBreakdown['vendor_contribution'] ?? 0,
                'billable_weight_grams' => $subsidyBreakdown['billable_weight_grams'] ?? 0,
                'is_free_by_platform' => $subsidyBreakdown['is_free_by_platform'] ?? false,
                'is_free_by_vendor' => $subsidyBreakdown['is_free_by_vendor'] ?? false,
            ];
        }

        return ['total' => $totalCents, 'per_vendor' => $perVendor];
    }

    /**
     * Customer-facing delivery fee message for a single vendor's shipping breakdown
     * (as returned by resolveVendorShipping's per_vendor entries).
     */
    private function deliveryMessage(array $vendorShipping): string
    {
        if ($vendorShipping['shipping'] > 0) {
            return __('common.exceptions.checkout.delivery_platform_covered', [
                'amount' => $vendorShipping['platform_subsidy'],
            ]);
        }

        if ($vendorShipping['is_free_by_vendor']) {
            return __('common.exceptions.checkout.delivery_free_by_seller');
        }

        if ($vendorShipping['is_free_by_platform'] || $vendorShipping['platform_subsidy'] > 0) {
            return __('common.exceptions.checkout.delivery_free_by_platform');
        }

        return __('common.exceptions.checkout.delivery_free');
    }

    /**
     * Deterministically resolve which warehouse would fulfill a cart item,
     * mirroring the (unlocked) selection used during actual reservation in
     * placeOrder(), so shipping previews match the warehouse that ends up
     * reserved.
     */
    private function resolveCartItemWarehouseId(CartLineSource $source): ?string
    {
        $listing = $source->fulfilmentListing;

        $column = $listing instanceof \App\Models\AdminListing ? 'admin_listing_id' : 'vendor_listing_id';

        // Mirrors InventoryService::reserve()'s most-available-first
        // selection (enhancement.md P-13), not "first row by id".
        return WarehouseInventory::where($column, $listing->id)
            ->orderByRaw('(quantity_on_hand - quantity_reserved) DESC')
            ->value('warehouse_id');
    }

    private function generateOrderNumber(): string
    {
        do {
            $candidate = 'NOON-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $candidate)->exists());

        return $candidate;
    }

    private function resolveReceiver(Customer $customer, ?string $receiverId): ?CustomerReceiver
    {
        $receiver = $receiverId
            ? CustomerReceiver::where('customer_id', $customer->id)->find($receiverId)
            : null;

        return $receiver ?? CustomerReceiver::where('customer_id', $customer->id)
            ->where('is_default', true)
            ->first();
    }

    private function buildAddressSnapshot(Address $address, ?CustomerReceiver $receiver = null): array
    {
        $address->loadMissing('city');

        return [
            'recipient_name' => $receiver?->name ?? $address->recipient_name,
            'recipient_phone' => $receiver?->phone ?? $address->recipient_phone,
            'country_id' => $address->country_id,
            'city_id' => $address->city_id,
            'city' => [
                'en' => $address->city?->name_en,
                'ar' => $address->city?->name_ar,
            ],
            'area' => $address->area,
            'street_address' => $address->street_address,
            'building' => $address->building,
            'floor' => $address->floor,
            'apartment' => $address->apartment,
            'postal_code' => $address->postal_code,
            'landmark' => $address->landmark,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
        ];
    }

    /**
     * Accepts any listing type that ends up as the fulfilling listing at
     * checkout — vendor, admin (platform), or a marketer listing's
     * underlying source listing (enhancement.md P-02 task 5). AdminListing
     * has no `vendor_sku` or `global_system_type` column, so those fields
     * degrade to null rather than erroring.
     */
    private function buildProductSnapshot(VendorListing|\App\Models\AdminListing $listing): array
    {
        $variant = $listing->productVariant;
        $product = $variant->product;

        $images = app(\App\Services\Media\ListingImageResolver::class)->gallery($variant->id);
        $thumbnail = $images[0]->url ?? null;

        return [
            'listing_id' => $listing->id,
            'listing_ref' => $this->listingIdentifierService->buildListingRef($listing),
            'sku' => $variant->sku,
            'vendor_sku' => $listing->vendor_sku ?? null,
            'name_en' => $product->name_en,
            'name_ar' => $product->name_ar,
            'price' => $listing->price,
            'currency' => $listing->currency,
            'condition' => $listing->condition,
            'global_system_type' => $listing instanceof VendorListing ? $listing->global_system_type?->value : 'express_fbn',
            'thumbnail_url' => $thumbnail,
            'primary_image_url' => $thumbnail,
            'image' => $thumbnail ? ['url' => $thumbnail, 'alt' => $images[0]->alt] : null,
            'images' => array_map(fn ($i) => $i->toArray(), $images),
            'variant_id' => $variant->id,
            'brand_name' => $product->brand?->name_en,
            'category_name' => $product->category?->name_en,
        ];
    }

    private function buildShippingMethodSnapshot(ShippingMethod $method): array
    {
        return [
            'id' => $method->id,
            'name' => $method->name,
            'code' => $method->code,
            'badge_label_en' => $method->badge_label_en,
            'badge_label_ar' => $method->badge_label_ar,
            'badge_color_hex' => $method->badge_color_hex,
            'delivery_label_en' => $method->delivery_label_en,
            'delivery_label_ar' => $method->delivery_label_ar,
            'min_delivery_days' => $method->min_delivery_days,
            'max_delivery_days' => $method->max_delivery_days,
        ];
    }

    private function calculateEstimatedDeliveryDate(ShippingMethod $method): ?string
    {
        if (! $method->min_delivery_days) {
            return null;
        }

        $handlingDays = (int) ceil(($method->handling_time_hours ?? 0) / 24);
        $date = now()->startOfDay()->addDays($handlingDays);

        if ($method->order_cutoff_time) {
            $cutoff = Carbon::createFromTimeString($method->order_cutoff_time);
            if (now()->format('H:i:s') > $cutoff->format('H:i:s')) {
                $date = $date->addDay();
            }
        }

        return $date->addDays($method->min_delivery_days)->toDateString();
    }
}
