<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AddCartItemRequest;
use App\Http\Requests\Customer\AddCartItemsRequest;
use App\Http\Requests\Customer\ApplyCouponRequest;
use App\Http\Requests\Customer\UpdateCartItemRequest;
use App\Http\Resources\Customer\BannerResource;
use App\Http\Resources\Customer\CartItemResource;
use App\Http\Resources\Customer\CartResource;
use App\Http\Responses\ApiResponse;
use App\Models\Cart;
use App\Models\CustomerWallet;
use App\Services\BannerService;
use App\Services\Customer\CartService;
use App\Services\Customer\ListingIdentifierService;
use App\Services\SavingsBenefitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ListingIdentifierService $listingIdentifierService,
        private readonly BannerService $bannerService,
        private readonly SavingsBenefitsService $savingsBenefitsService,
    ) {
    }

    private function resolveCart(Request $request): Cart
    {
        $customer = auth('customer')->user();
        $country = $request->attributes->get('country');

        if ($customer) {
            return $this->cartService->getOrCreateCart(
                $customer,
                $country->id,
                $country->currency_code
            );
        }

        $token = $request->attributes->get('guest_cart_token');
        if (!$token) {
            $token = (string) Str::uuid();
            $request->attributes->set('guest_cart_token', $token);
        }

        return $this->cartService->getOrCreateGuestCart(
            $token,
            $country->id,
            $country->currency_code
        );
    }

    private function cartResponse(Cart $cart, array $extra = [], string $message = 'Success', int $code = 200): JsonResponse
    {
        $data = array_merge(['cart' => new CartResource($cart)], $extra);

        if ($cart->session_token) {
            $data['guest_cart_token'] = $cart->session_token;
        }

        return ApiResponse::success($data, $message, $code);
    }

    public function show(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

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

        $country = $request->attributes->get('country');

        return $this->cartResponse($cart, [
            'shipping_groups' => $this->cartService->buildShippingGroups($cart, $country?->id),
            'cart_banner' => $this->resolveCartBanner($request),
            'savings_and_benefits' => $this->savingsBenefitsService->get(
                (int) $cart->estimated_total,
                $cart->country_id,
                $cart->currency,
            ),
            'wallet' => $this->resolveWalletInfo($cart),
        ]);
    }

    /**
     * Toggles whether the customer wants to pay with their wallet balance.
     * When enabled, applies min(wallet balance, estimated_total); when
     * disabled, clears the amount. Persists to carts.wallet_amount_to_use so
     * CheckoutService can read it when the order is placed.
     */
    public function toggleWallet(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        if (!$customer) {
            return ApiResponse::error(__('common.exceptions.cart.wallet_login_required'), [], 401);
        }

        $cart = $this->resolveCart($request);
        $useWallet = $request->boolean('use_wallet');

        if ($useWallet) {
            $wallet = CustomerWallet::where('customer_id', $customer->id)
                ->where('currency_code', $cart->currency)
                ->first();

            $walletAmount = min($wallet?->balance ?? 0, (int) $cart->estimated_total);
        } else {
            $walletAmount = 0;
        }

        $cart->update(['wallet_amount_to_use' => $walletAmount]);

        return $this->cartResponse($cart, [
            'wallet' => $this->resolveWalletInfo($cart),
        ], __('common.exceptions.cart.wallet_usage_updated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveWalletInfo(Cart $cart): array
    {
        $customer = auth('customer')->user();

        if (!$customer) {
            return [
                'balance' => 0,
                'currency_code' => $cart->currency,
                'applicable' => false,
                'max_usable' => 0,
                'remaining_after_wallet' => (int) $cart->estimated_total,
            ];
        }

        $customerCurrency = $customer->country?->currency_code;

        $wallet = CustomerWallet::where('customer_id', $customer->id)->first();
        $walletBalance = $wallet?->balance ?? 0;
        $walletCurrency = $wallet?->currency_code ?? $customerCurrency;

        $walletApplicable = $walletBalance > 0 && $walletCurrency === $cart->currency;

        $maxUsable = $walletApplicable ? min($walletBalance, (int) $cart->estimated_total) : 0;
        $applied = $walletApplicable ? min((int) $cart->wallet_amount_to_use, $maxUsable) : 0;

        return [
            'balance' => $walletBalance,
            'currency_code' => $walletCurrency,
            'applicable' => $walletApplicable,
            'max_usable' => $maxUsable,
            'amount_applied' => $applied,
            'remaining_after_wallet' => (int) $cart->estimated_total - $applied,
        ];
    }

    private function resolveCartBanner(Request $request): ?BannerResource
    {
        $country = $request->attributes->get('country');
        $audience = auth('customer')->check() ? 'logged_in' : 'guest';

        $banner = $this->bannerService->getActivePlacement('cart_banner', $country?->id, $audience);

        return $banner ? new BannerResource($banner) : null;
    }

    public function addItem(AddCartItemRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $countryId = $request->attributes->get('country')->id;

        $isAdmin = $request->input('listing_type') === 'admin';

        try {
            if ($isAdmin) {
                $item = $this->cartService->addAdminItem($cart, $request->admin_listing_id, $request->quantity, $request->shipping_method_id, $countryId);
            } else {
                $item = $this->cartService->addItem($cart, $request->vendor_listing_id, $request->quantity, $request->shipping_method_id, $countryId);
            }
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        $item->load([
            'vendorListing.vendor',
            'vendorListing.productVariant.product.images',
            'vendorListing.primaryShippingMethod',
            'vendorListing.warehouseInventories',
            'adminListing.productVariant.product.images',
            'selectedShippingMethod',
        ]);

        return $this->cartResponse($cart, [
            'item' => new CartItemResource($item),
            'listing_ref' => $isAdmin ? null : $this->listingIdentifierService->buildListingRef($item->vendorListing),
        ], __('common.exceptions.cart.item_added'), 201);
    }

    public function addItems(AddCartItemsRequest $request): JsonResponse
    {
        $cart = $this->resolveCart($request);
        $countryId = $request->attributes->get('country')->id;

        try {
            $this->cartService->addItems($cart, $request->items, $countryId);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return $this->cartResponse($cart, [], __('common.exceptions.cart.items_added'), 201);
    }

    public function updateItem(UpdateCartItemRequest $request, $countryId, string $id): JsonResponse
    {
        $cart = $this->resolveCart($request);

        $countryId = $request->attributes->get('country')->id;

        try {
            $item = $this->cartService->updateItem($cart, $id, $request->quantity, $request->shipping_method_id, $request->has('shipping_method_id'), $countryId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error(__('common.exceptions.cart.item_not_found'), [], 404);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        $item->load([
            'vendorListing.vendor',
            'vendorListing.productVariant.product.images',
            'vendorListing.primaryShippingMethod',
            'vendorListing.warehouseInventories',
            'adminListing.productVariant.product.images',
            'adminListing.warehouseInventories',
            'selectedShippingMethod',
        ]);

        $listing = $item->vendor_listing_id
            ? $item->vendorListing
            : $item->adminListing;

        return ApiResponse::success([
            'cart'        => new CartResource($cart),
            'item'        => new CartItemResource($item),
            'listing_ref' => $listing
                ? $this->listingIdentifierService->buildListingRef($listing)
                : null,
        ], __('common.exceptions.cart.item_updated'));
    }

    public function removeItem(Request $request, $countryId, string $id): JsonResponse
    {
        $cart = $this->resolveCart($request);

        try {
            $this->cartService->removeItem($cart, $id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error(__('common.exceptions.cart.item_not_found'), [], 404);
        }

        return ApiResponse::success(new CartResource($cart), __('common.exceptions.cart.item_removed'));
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

        $this->cartService->clearCart($cart);

        return ApiResponse::success(null, __('common.exceptions.cart.cleared'));
    }

    public function applyCoupon(ApplyCouponRequest $request): JsonResponse
    {
        $customer = auth('customer')->user();
        $cart = $this->resolveCart($request);

        try {
            $coupon = $this->cartService->applyCoupon($cart, $customer, $request->code);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error(__('common.exceptions.cart.coupon_not_found'), [], 404);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success(new CartResource($cart), __('common.exceptions.cart.coupon_applied', ['code' => $coupon->code]));
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

        $this->cartService->removeCoupon($cart);

        return ApiResponse::success(new CartResource($cart), __('common.exceptions.cart.coupon_removed'));
    }

    public function applyPromoCode(ApplyCouponRequest $request): JsonResponse
    {
        $customer = auth('customer')->user();
        $cart = $this->resolveCart($request);
        $code = $request->code;

        try {
            $coupon = $this->cartService->applyCoupon($cart, $customer, $code);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiResponse::error(__('common.exceptions.cart.coupon_not_found'), [], 404);
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        return ApiResponse::success([
            'success' => true,
            'message' => __('common.exceptions.cart.coupon_applied', ['code' => $code]),
            'data' => [
                'discount_amount' => $cart->discount,
                'type' => 'coupon',
            ],
            'cart' => new CartResource($cart),
        ], __('common.exceptions.cart.coupon_applied', ['code' => $code]));
    }

    public function removePromoCode(Request $request): JsonResponse
    {
        $cart = $this->resolveCart($request);

        $this->cartService->removeCoupon($cart);

        return ApiResponse::success(new CartResource($cart), __('common.exceptions.cart.promo_removed'));
    }

    public function mergeCart(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();
        $country = $request->attributes->get('country');
        $token = $request->input('guest_cart_token');

        if (!$token) {
            return ApiResponse::error(__('common.exceptions.cart.guest_token_required'), [], 422);
        }

        $cart = $this->cartService->mergeGuestCart(
            $token,
            $customer,
            $country->id,
            $country->currency_code
        );

        return ApiResponse::success(new CartResource($cart), __('common.exceptions.cart.merged'));
    }
}
