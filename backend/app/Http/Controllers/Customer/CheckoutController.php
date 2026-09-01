<?php

namespace App\Http\Controllers\Customer;

use App\Enums\AdminListingStatus;
use App\Enums\DeliveryInstruction;
use App\Enums\GlobalSystemType;
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
use App\Models\CustomerWallet;
use App\Exceptions\GiftCardCurrencyMismatchException;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\SubOrder;
use App\Models\VendorListing;
use App\Models\WarehouseInventory;
use App\Models\WarrantyPurchase;
use App\Services\Customer\CartService;
use App\Services\Customer\CheckoutCalculationService;
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
    ) {}

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
            'items.selectedShippingMethod',
            'coupon',
        ]);

        if ($cart->items->isEmpty()) {
            return ApiResponse::error(__('common.exceptions.checkout.cart_empty'), [], 422);
        }

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

        $vendorShipping = $this->resolveVendorShipping($cartItems, $totalShippingFee, $shippingZone, null);

        $codFeeCents = $isCod ? $codExtraFee : 0;

        $warrantyResult = $this->calculationService->resolveWarrantySelections(
            $cartItems,
            $validated['warranty_selections'] ?? [],
            $country,
            $cart->currency,
            $this->warrantyPlanService,
        );
        $warrantyTotalCents = $warrantyResult['total'];

        $couponResponse = null;
        $discountCents = 0;
        if (! empty($validated['coupon_code'])) {
            $coupon = Coupon::where('code', $validated['coupon_code'])->first();
            if (! $coupon) {
                return ApiResponse::error(__('common.exceptions.checkout.invalid_coupon'), [], 422);
            }

            $subtotal = (int) collect($cartItems)->sum(fn ($i) => $i->unit_price * $i->quantity);
            $couponResult = $this->calculationService->applyCoupon($coupon, $customer, $subtotal, $cart->currency, $cartItems);

            if ($couponResult['error']) {
                return ApiResponse::error($couponResult['error'], [], 422);
            }

            $discountCents = $couponResult['discount'];
            $couponResponse = [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'discount' => $discountCents,
            ];
        }

        $summary = $this->calculationService->buildOrderSummary(
            $cartItems,
            $vendorShipping['total'],
            $codFeeCents,
            $discountCents,
            $country,
            0,
            $warrantyTotalCents
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
        ], __('common.exceptions.checkout.preview_ready'));
    }

    public function placeOrder(PlaceOrderRequest $request): JsonResponse
    {
        $customer = auth('customer')->user();
        $country = $request->attributes->get('country');
        $validated = $request->validated();

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
        ]);

        if ($cart->items->isEmpty()) {
            return ApiResponse::error(__('common.exceptions.checkout.cart_empty'), [], 422);
        }

        foreach ($cart->items as $item) {
            $isAdmin = ! is_null($item->admin_listing_id);
            $listing = $isAdmin ? $item->adminListing : $item->vendorListing;

            if (! $listing) {
                return ApiResponse::error(
                    __('common.exceptions.checkout.listing_not_available', ['id' => $item->id]),
                    [], 422
                );
            }

            if ($isAdmin) {
                if ($listing->status !== AdminListingStatus::Active) {
                    return ApiResponse::error(
                        __('common.exceptions.checkout.listing_not_available', ['id' => $listing->id]),
                        [], 422
                    );
                }
            } else {
                if ($listing->status !== VendorListingStatus::Active) {
                    return ApiResponse::error(
                        __('common.exceptions.checkout.listing_not_available', ['id' => $listing->id]),
                        [], 422
                    );
                }
            }

            $available = $listing->warehouseInventories->sum('quantity_available');
            if ($available < $item->quantity) {
                return ApiResponse::error(
                    __('common.exceptions.checkout.insufficient_stock_available', ['available' => $available]),
                    [], 422
                );
            }
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
        $vendorShipping = $this->resolveVendorShipping($cartItems, $totalShippingFee, $shippingZone, null);
        $shippingFeeCents = $vendorShipping['total'];
        $codFeeCents      = $isCod ? $codExtraFee : 0;

        $warrantyResult = $this->calculationService->resolveWarrantySelections(
            $cartItems,
            $validated['warranty_selections'] ?? [],
            $country,
            $cart->currency,
            $this->warrantyPlanService,
        );
        $warrantyTotalCents = $warrantyResult['total'];
        $warrantySelections = $warrantyResult['selections'];

        $coupon = null;
        $discountCents = 0;
        if (! empty($validated['coupon_code'])) {
            $coupon = Coupon::where('code', $validated['coupon_code'])->first();
            if (! $coupon) {
                return ApiResponse::error(__('common.exceptions.checkout.invalid_coupon'), [], 422);
            }

            $subtotal = (int) collect($cartItems)->sum(fn ($i) => $i->unit_price * $i->quantity);
            $couponResult = $this->calculationService->applyCoupon($coupon, $customer, $subtotal, $cart->currency, $cartItems);

            if ($couponResult['error']) {
                return ApiResponse::error($couponResult['error'], [], 422);
            }

            $discountCents = $couponResult['discount'];
        }
        $couponDiscountCents = $discountCents;

        // ── Loyalty redemption ────────────────────────────────────────────────
        $loyaltyDiscount      = 0;
        $loyaltyPointsToUse   = 0.0;
        if (! empty($validated['loyalty_points_to_use'])) {
            $loyaltyPointsToUse = (float) $validated['loyalty_points_to_use'];
            try {
                $loyaltyDiscount = $this->loyaltyService->calculateRedemptionDiscount(
                    $customer,
                    $loyaltyPointsToUse,
                    // Pass a temporary total estimate (subtotal - coupon) for the cap check.
                    // The real cap is re-applied inside debitPointsForOrder after order creation.
                    max(0, (int) collect($cartItems)->sum(fn ($i) => $i->unit_price * $i->quantity) - $couponDiscountCents),
                );
            } catch (\Illuminate\Validation\ValidationException $e) {
                return ApiResponse::error($e->getMessage(), $e->errors(), 422);
            }
            $discountCents += $loyaltyDiscount;
        }

        $summary = $this->calculationService->buildOrderSummary(
            $cartItems,
            $shippingFeeCents,
            $codFeeCents,
            $discountCents,
            $country,
            0,
            $warrantyTotalCents
        );

        $attribution = session('marketer_attribution', []);

        try {
            $result = DB::transaction(function () use (
                $customer, $country, $address, $receiver, $validated, $coupon,
                $cartItems, $summary, $attribution, $vendorShipping,
                $warrantySelections, $cart, $couponDiscountCents,
                $loyaltyDiscount, $loyaltyPointsToUse,
                $gatewayCode, $isCod, $isWallet, $methodConfig
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
                    'payment_method' => $gatewayCode,
                    'payment_status' => 'pending',
                    'shipping_address_snapshot' => $this->buildAddressSnapshot($address, $receiver),
                    'customer_notes' => $validated['customer_notes'] ?? null,
                    'delivery_instruction' => $validated['delivery_instruction'] ?? null,
                    'ip_address' => request()->ip() ?? '0.0.0.0',
                    'user_agent' => request()->userAgent(),
                    'placed_at' => now(),
                    'marketer_id' => $attribution['marketer_id'] ?? null,
                    'marketer_campaign_id' => $attribution['campaign_id'] ?? null,
                ]);

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

                $grouped = collect($cartItems)->groupBy(fn ($item) => $item->vendorListing->vendor_id.'|'.($item->selected_shipping_method_id ?? ''));
                $subOrders = [];
                $idx = 0;

                foreach ($grouped as $groupKey => $items) {
                    $idx++;
                    $vendorId = $items->first()->vendorListing->vendor_id;
                    $subOrderShippingMethodId = $items->first()->selected_shipping_method_id;
                    $subOrderShippingMethod = $resolveShippingMethod($subOrderShippingMethodId);
                    $vendorSubtotal = (int) $items->sum(fn ($i) => $i->unit_price * $i->quantity);
                    $firstListing = $items->first()->vendorListing;

                    $vendorShippingCents = $vendorShippingMap[$vendorId]['shipping'] ?? 0;
                    $vendorAdminSubsidyCents = $vendorShippingMap[$vendorId]['platform_subsidy'] ?? 0;
                    $vendorContributionCents = $vendorShippingMap[$vendorId]['vendor_contribution'] ?? 0;
                    $vendorBillableWeightGrams = $vendorShippingMap[$vendorId]['billable_weight_grams'] ?? null;

                    $vendorTax = (int) round($vendorSubtotal * ((float) $country->vat_rate / 100));

                    $itemCommissions = [];
                    $reservedInventories = [];
                    $totalCommission = 0;
                    foreach ($items as $cartItem) {
                        $commission = $this->calculationService->calculateCommission(
                            $cartItem->vendorListing,
                            $cartItem->quantity,
                            $cartItem->unit_price,
                            $country
                        );
                        $itemCommissions[$cartItem->id] = $commission;
                        $totalCommission += $commission['commission_amount'];

                        $inventory = WarehouseInventory::where('vendor_listing_id', $cartItem->vendor_listing_id)
                            ->lockForUpdate()
                            ->orderBy('id')
                            ->first();

                        if (! $inventory || $inventory->quantity_available < $cartItem->quantity) {
                            throw new \DomainException(
                                __('common.exceptions.checkout.insufficient_stock')
                            );
                        }

                        $inventory->increment('quantity_reserved', $cartItem->quantity);
                        $inventory->refresh();

                        InventoryMovement::create([
                            'warehouse_inventory_id' => $inventory->id,
                            'movement_type' => 'reservation',
                            'quantity_delta' => $cartItem->quantity,
                            'quantity_after' => $inventory->quantity_on_hand,
                            'reference_type' => 'order',
                            'reference_id' => $order->id,
                            'reason' => 'Checkout reservation',
                            'created_by_user_id' => $customer->id,
                        ]);

                        $reservedInventories[$cartItem->id] = $inventory;
                    }

                    $warehouseId = $reservedInventories[$items->first()->id]->warehouse_id;

                    $subOrder = SubOrder::create([
                        'order_id' => $order->id,
                        'sub_order_number' => $order->order_number.'-'.str_pad((string) $idx, 2, '0', STR_PAD_LEFT),
                        'vendor_id' => $vendorId,
                        'warehouse_id' => $warehouseId,
                        'status' => 'placed',
                        'fulfillment_model' => $firstListing->fulfillment_model,
                        'subtotal' => $vendorSubtotal,
                        'shipping' => $vendorShippingCents,
                        'admin_subsidy_amount' => $vendorAdminSubsidyCents,
                        'vendor_contribution_amount' => $vendorContributionCents,
                        'billable_weight_grams' => $vendorBillableWeightGrams,
                        'tax' => $vendorTax,
                        'platform_commission' => $totalCommission,
                        'gateway_fee' => 0,
                        'gateway_fee_rate' => 0,
                        'vendor_payout' => $vendorSubtotal - $totalCommission,
                        'shipping_method_id' => $subOrderShippingMethodId,
                        'estimated_delivery_date' => $subOrderShippingMethod
                            ? $this->calculateEstimatedDeliveryDate($subOrderShippingMethod)
                            : null,
                        'sla_ship_deadline' => now()->addHours(24),
                    ]);

                    foreach ($items as $cartItem) {
                        $listing = $cartItem->vendorListing;
                        $commission = $itemCommissions[$cartItem->id];
                        $lineSubtotal = $cartItem->unit_price * $cartItem->quantity;
                        $lineTax = (int) round($cartItem->unit_price * $cartItem->quantity * ((float) $country->vat_rate / 100));

                        $itemShippingMethod = $resolveShippingMethod($cartItem->selected_shipping_method_id);
                        $itemShippingMethodSnapshot = $itemShippingMethod
                            ? $this->buildShippingMethodSnapshot($itemShippingMethod)
                            : null;

                        $productSnapshot = $this->buildProductSnapshot($listing);
                        $productSnapshot['shipping_method'] = $itemShippingMethodSnapshot;

                        $orderItem = OrderItem::create([
                            'order_id' => $order->id,
                            'sub_order_id' => $subOrder->id,
                            'product_variant_id' => $listing->product_variant_id,
                            'vendor_listing_id' => $listing->id,
                            'product_snapshot' => $productSnapshot,
                            'vendor_id' => $listing->vendor_id,
                            'sku' => $listing->productVariant->sku,
                            'quantity' => $cartItem->quantity,
                            'unit_price' => $cartItem->unit_price,
                            'unit_cost_price' => $listing->cost_price,
                            'line_subtotal' => $lineSubtotal,
                            'line_discount' => 0,
                            'line_tax' => $lineTax,
                            'line_total' => $lineSubtotal + $lineTax,
                            'commission_rate_pct' => $commission['commission_rate_pct'],
                            'commission_fixed' => $commission['commission_fixed'],
                            'commission_category_id' => $commission['commission_category_id'],
                            'commission_amount' => $commission['commission_amount'],
                            'shipping_method_id' => $cartItem->selected_shipping_method_id,
                            'shipping_method_snapshot' => $itemShippingMethodSnapshot,
                            'fulfillment_status' => 'pending',
                            'return_eligible_until' => null,
                        ]);

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

                $walletAmountToUse = (int) ($validated['wallet_amount_used'] ?? $validated['wallet_amount_to_use'] ?? 0);
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
                            'payment_status' => 'captured',
                        ]);
                    }
                }

                if ($coupon) {
                    // Re-validate at placement time — coupon may have expired,
                    // hit its usage limit, or become otherwise invalid since it
                    // was applied to the cart.
                    try {
                        $this->couponService->validate($coupon->code, $cart, $customer);
                    } catch (ValidationException $e) {
                        throw new \DomainException(
                            __('common.exceptions.checkout.coupon_no_longer_valid', ['reason' => $e->validator->errors()->first()])
                        );
                    }

                    $this->couponService->recordUsage($coupon, $order, $customer, $couponDiscountCents);
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

                return ['order' => $order, 'sub_orders' => $subOrders, 'wallet_fully_paid' => $order->payment_status === 'captured'];
            });
        } catch (\DomainException|InsufficientWalletBalanceException|GiftCardCurrencyMismatchException|\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        $subOrders = $result['sub_orders'];
        $order = $result['order'];

        $paymentRedirectUrl = null;
        $bankTransferDetails = null;

        if ($result['wallet_fully_paid']) {
            // Wallet covered the full order total inside the transaction above — no COD
            // collection and no external payment gateway call needed.
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
            try {
                $paymentResult = $this->paymentService->initiatePayment($order, $methodConfig, $validated['idempotency_key']);
                if (! $paymentResult->success) {
                    $order->update(['payment_status' => 'failed', 'status' => 'cancelled']);
                    $this->releaseReservedInventory($order);
                } else {
                    $paymentRedirectUrl = $paymentResult->redirectUrl;
                    if ($gatewayCode === 'bank_transfer') {
                        $bankTransferDetails = $paymentResult->rawResponse;
                    }
                }
            } catch (\Throwable $e) {
                $order->update(['payment_status' => 'failed', 'status' => 'cancelled']);
                $this->releaseReservedInventory($order);
            }
        }

        $this->cartService->clearCart($cart);

        foreach ($subOrders as $subOrder) {
            dispatch(new AutoAssignShippingMethodJob($subOrder->id))->delay(now()->addHours(12));
            SubOrderPlaced::dispatch($subOrder);
            NotifyVendorJob::dispatch($order->id, $subOrder->vendor_id);
        }
        OrderConfirmationEmailJob::dispatch($order->id);
        FraudDetectionJob::dispatch($order->id);

        $order = $order->fresh();
        $order->load('subOrders.items.vendorListing');

        $responseData = (new PlaceOrderResultResource($order))->toArray($request);
        $responseData['payment_redirect_url'] = $paymentRedirectUrl ?: null;
        $responseData['requires_redirect'] = ! empty($paymentRedirectUrl);
        $responseData['bank_transfer_details'] = $bankTransferDetails;

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
        int $baseFeeCents,
        ?ShippingZone $zone = null,
        ?ShippingMethod $method = null,
    ): array {
        $subtotalAll = max(1, (int) collect($cartItems)->sum(fn ($i) => $i->unit_price * $i->quantity));
        $grouped = collect($cartItems)->groupBy(fn ($item) => $item->vendorListing->vendor_id);

        $totalCents = 0;
        $perVendor = [];

        foreach ($grouped as $vendorId => $items) {
            $vendorSubtotal = (int) $items->sum(fn ($i) => $i->unit_price * $i->quantity);
            $firstListing = $items->first()->vendorListing;
            $isFbn = $firstListing->global_system_type === GlobalSystemType::ExpressFbn;
            $isFbp = $firstListing->global_system_type === GlobalSystemType::MerchantFbp;

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
                    $warehouseId = $this->resolveCartItemWarehouseId($cartItem);
                    $surchargeCents += $this->cityShippingSurchargeService->resolveSurcharge($vendorId, $warehouseId);
                }
            } elseif ($isFbn) {
                foreach ($items as $cartItem) {
                    $warehouseId = $this->resolveCartItemWarehouseId($cartItem);
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
    private function resolveCartItemWarehouseId($cartItem): ?string
    {
        return WarehouseInventory::where('vendor_listing_id', $cartItem->vendor_listing_id)
            ->orderBy('id')
            ->value('warehouse_id');
    }

    private function releaseReservedInventory(Order $order): void
    {
        $order->loadMissing('subOrders.items');

        DB::transaction(function () use ($order) {
            foreach ($order->subOrders as $subOrder) {
                foreach ($subOrder->items as $item) {
                    $inventory = WarehouseInventory::where('vendor_listing_id', $item->vendor_listing_id)
                        ->where('warehouse_id', $subOrder->warehouse_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventory) {
                        continue;
                    }

                    $inventory->decrement('quantity_reserved', $item->quantity);
                    $inventory->refresh();

                    InventoryMovement::create([
                        'warehouse_inventory_id' => $inventory->id,
                        'movement_type' => 'reservation_release',
                        'quantity_delta' => -$item->quantity,
                        'quantity_after' => $inventory->quantity_on_hand,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'reason' => 'Payment failed',
                        'created_by_user_id' => $order->customer_id,
                    ]);
                }
            }
        });
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

    private function buildProductSnapshot(VendorListing $listing): array
    {
        $variant = $listing->productVariant;
        $product = $variant->product;
        $thumbnail = $product->images->firstWhere('is_primary', true)?->url ?? $product->images->first()?->url;

        return [
            'listing_id' => $listing->id,
            'listing_ref' => $this->listingIdentifierService->buildListingRef($listing),
            'sku' => $variant->sku,
            'vendor_sku' => $listing->vendor_sku,
            'name_en' => $product->name_en,
            'name_ar' => $product->name_ar,
            'price' => $listing->price,
            'currency' => $listing->currency,
            'condition' => $listing->condition,
            'global_system_type' => $listing->global_system_type?->value,
            'thumbnail_url' => $thumbnail,
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
