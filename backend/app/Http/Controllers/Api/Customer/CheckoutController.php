<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\CheckoutOrderResource;
use App\Http\Resources\Api\Customer\CouponValidationResource;
use App\Http\Responses\ApiResponse;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SubOrder;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WarehouseInventory;
use App\Models\WarrantyPurchase;
use App\Services\CheckoutCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutCalculationService $calculationService,
    ) {}

    public function calculate(Request $request): JsonResponse
    {
        $validated = $this->validateCheckoutInput($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        try {
            $preview = $this->calculationService->calculate($customer, $validated['cart_items'], $validated);
        } catch (ValidationException $e) {
            return ApiResponse::error(__('customer_api.checkout.calculation_failed'), $this->flattenErrors($e), 422);
        }

        return ApiResponse::success($preview, __('customer_api.checkout.preview_calculated'));
    }

    /**
     * GET /checkout/payment-options
     * Returns active payment gateways for this country with fees and display metadata.
     * Called by Flutter on checkout load, before the customer picks a payment method.
     */
    public function paymentOptions(Request $request): JsonResponse
    {
        $country    = $request->attributes->get('country');
        $customer   = auth('customer')->user();
        $orderTotal = (int) $request->query('order_total', 0);

        $gateways = \App\Models\CountryPaymentGateway::where('country_id', $country->id)
            ->where('is_active', true)
            ->with('gateway')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($cpg) use ($orderTotal) {
                $code      = $cpg->gateway?->code;
                $feePct    = (float) $cpg->fee_pct;
                $feeFixed  = (int) $cpg->fee_fixed;
                $available = true;
                $reason    = null;

                if ($cpg->min_order > 0 && $orderTotal > 0 && $orderTotal < $cpg->min_order) {
                    $available = false;
                    $reason    = 'Order below minimum.';
                }
                if ($cpg->max_order && $orderTotal > 0 && $orderTotal > $cpg->max_order) {
                    $available = false;
                    $reason    = 'Order exceeds maximum.';
                }

                return [
                    'id'            => $cpg->id,
                    'gateway_code'  => $code,
                    'type'          => $cpg->gateway?->type,
                    'display_name'  => ['en' => $cpg->display_name_en, 'ar' => $cpg->display_name_ar],
                    'image'         => $cpg->gateway?->image,
                    'is_redirect'   => in_array($code, ['thawani', 'paytabs']),
                    'fee_pct'       => $feePct,
                    'fee_fixed'     => $feeFixed,
                    'gateway_fee'   => $orderTotal > 0 ? (int) round($orderTotal * ($feePct / 100)) + $feeFixed : 0,
                    'is_available'  => $available,
                    'unavailable_reason' => $reason,
                    'environment'   => $cpg->environment,
                ];
            });

        $wallet = \App\Models\CustomerWallet::where('customer_id', $customer->id)->first();

        return ApiResponse::success([
            'payment_options' => $gateways->values(),
            'wallet' => [
                'balance'       => $wallet?->balance ?? 0,
                'currency_code' => $wallet?->currency_code ?? $country->currency_code,
                'applicable'    => $wallet && $wallet->currency_code === $country->currency_code && $wallet->balance > 0,
            ],
        ]);
    }

    /**
     * GET /payment-gateways
     * Public endpoint: returns all active payment gateways for this country.
     * Used on product detail pages, help screens, etc.
     * No auth required — same data regardless of who is asking.
     */
    public function availableGateways(Request $request): JsonResponse
    {
        $country = $request->attributes->get('country');

        $gateways = \App\Models\CountryPaymentGateway::where('country_id', $country->id)
            ->where('is_active', true)
            ->with('gateway')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($cpg) => [
                'id'           => $cpg->id,
                'gateway_code' => $cpg->gateway?->code,
                'type'         => $cpg->gateway?->type,
                'display_name' => [
                    'en' => $cpg->display_name_en,
                    'ar' => $cpg->display_name_ar,
                ],
                'image'        => $cpg->gateway?->image,
                'is_redirect'  => in_array($cpg->gateway?->code, ['thawani', 'paytabs']),
                'supports_cod' => $cpg->gateway?->code === 'cod',
                'fee_pct'      => (float) $cpg->fee_pct,
                'fee_fixed'    => (int) $cpg->fee_fixed,
            ])
            ->values();

        return ApiResponse::success(['gateways' => $gateways]);
    }

    public function validateCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'coupon_code' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('customer_api.validation_failed'), $validator->errors()->toArray(), 422);
        }

        /** @var Customer $customer */
        $customer = auth('customer')->user();
        $country = \App\Models\Country::findOrFail($customer->country_id);

        $coupon = Coupon::where('code', $request->input('coupon_code'))->first();
        if (! $coupon) {
            return ApiResponse::error(__('customer_api.checkout.invalid_coupon_code'), [], 422);
        }

        $now = now();
        if (! $coupon->is_active) {
            return ApiResponse::error(__('customer_api.checkout.coupon_not_active'), [], 422);
        }
        if (($coupon->valid_from && $now->lt($coupon->valid_from)) || ($coupon->valid_until && $now->gt($coupon->valid_until))) {
            return ApiResponse::error(__('customer_api.checkout.coupon_not_valid_now'), [], 422);
        }
        if ($coupon->usage_limit_total !== null && $coupon->times_used >= $coupon->usage_limit_total) {
            return ApiResponse::error(__('customer_api.checkout.coupon_usage_limit_reached'), [], 422);
        }
        if ($coupon->usage_limit_per_customer !== null) {
            $used = CouponUsage::where('coupon_id', $coupon->id)->where('customer_id', $customer->id)->count();
            if ($used >= $coupon->usage_limit_per_customer) {
                return ApiResponse::error(__('customer_api.checkout.coupon_max_uses_reached'), [], 422);
            }
        }
        if ($coupon->currency !== null && $coupon->currency !== $country->currency_code) {
            return ApiResponse::error(__('customer_api.checkout.coupon_currency_mismatch'), [], 422);
        }

        return ApiResponse::success(
            (new CouponValidationResource($coupon))->toArray($request),
            __('customer_api.checkout.coupon_valid'),
        );
    }

    public function place(Request $request): JsonResponse
    {
        // This endpoint is deprecated. Use POST /checkout/place-order instead.
        // The new endpoint uses country_payment_gateway_id from GET /checkout/payment-options.
        return ApiResponse::error(
            'This checkout endpoint is deprecated. Use POST /checkout/place-order with country_payment_gateway_id.',
            ['use_instead' => 'POST /checkout/place-order'],
            410
        );
    }

    /**
     * @return array|JsonResponse
     */
    private function validateCheckoutInput(Request $request, bool $forPlace = false)
    {
        $rules = [
            'cart_items' => ['required', 'array', 'min:1'],
            'cart_items.*.listing_id' => ['required', 'uuid'],
            'cart_items.*.listing_type' => ['required', 'in:vendor,admin'],
            'cart_items.*.quantity' => ['required', 'integer', 'min:1'],
            'address_id' => ['required', 'uuid'],
            'payment_method' => ['required', 'string'],
            'coupon_code' => ['nullable', 'string'],
            'wallet_use' => ['nullable', 'boolean'],
            'gift_card_code' => ['nullable', 'string'],
            'warranty_selections' => ['nullable', 'array'],
        ];

        if ($forPlace) {
            $rules['idempotency_key'] = ['required', 'string'];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return ApiResponse::error(__('customer_api.validation_failed'), $validator->errors()->toArray(), 422);
        }

        return $validator->validated();
    }

    private function flattenErrors(ValidationException $e): array
    {
        return $e->errors();
    }

    private function resolveWarehouseId(array $item): ?string
    {
        if ($item['is_admin']) {
            return null;
        }

        return WarehouseInventory::where('vendor_listing_id', $item['vendor_listing_id'])
            ->orderBy('id')
            ->value('warehouse_id');
    }

    private function buildAddressSnapshot(Address $address): array
    {
        $address->loadMissing('city');

        return [
            'recipient_name' => $address->recipient_name,
            'recipient_phone' => $address->recipient_phone,
            'country_id' => $address->country_id,
            'city_id' => $address->city_id,
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

    private function buildProductSnapshot(array $item): array
    {
        $listing = $item['listing'];
        $variant = $listing->productVariant;
        $product = $variant?->product;

        return [
            'name_en' => $product?->name_en,
            'name_ar' => $product?->name_ar,
            'sku' => $variant?->sku,
            'images' => $product?->images?->pluck('url')->values()->all() ?? [],
        ];
    }

    private function generateOrderNumber(): string
    {
        do {
            $candidate = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $candidate)->exists());

        return $candidate;
    }

    private function clearCustomerCart(Customer $customer): void
    {
        \App\Models\Cart::where('user_id', $customer->id)->get()->each(function ($cart) {
            $cart->items()->delete();
        });
    }
}
