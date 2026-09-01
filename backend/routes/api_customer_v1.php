<?php

use App\Http\Controllers\Customer\HomeController;
use App\Http\Controllers\Customer\NavigationController;
use App\Http\Controllers\Customer\ProductController;
use App\Http\Controllers\Customer\ListingDetailController;
use App\Http\Controllers\Customer\BrowseController;
use App\Http\Controllers\Customer\ListingController;
use App\Http\Controllers\Api\Customer\ListingController as ApiListingController;
use App\Http\Controllers\Customer\CategoryController;
use App\Http\Controllers\Customer\PageController;
use App\Http\Controllers\Customer\VendorPageController;
use App\Http\Controllers\Customer\BrandPageController;
use App\Http\Controllers\Customer\WalletController;
use App\Http\Controllers\Customer\WarrantyClaimController;
use App\Http\Controllers\Customer\WarrantyPurchaseController;
use App\Http\Controllers\Api\Customer\SecurityController;
use App\Http\Controllers\Api\Customer\WalletController as ApiWalletController;
use App\Http\Controllers\Api\Customer\WarrantyController as ApiWarrantyController;
use App\Http\Controllers\Api\Customer\WishlistController as ApiWishlistController;
use App\Http\Controllers\Customer\AddressController;
use App\Http\Controllers\Customer\ReceiverController;
use App\Http\Controllers\Customer\AuthController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CartRecommendationsController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Api\Customer\CheckoutController as ApiCheckoutController;
use App\Http\Controllers\Customer\CouponController;
use App\Http\Controllers\Customer\DisputeController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\RefundController;
use App\Http\Controllers\Customer\ReturnController;
use App\Http\Controllers\Customer\ReviewController;
use App\Http\Controllers\Customer\SearchController;
use App\Http\Controllers\Customer\SupportTicketController;
use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Api\Customer\CustomerGiftCardStoreController;
use App\Http\Controllers\Api\Customer\QrCodeController;
use App\Http\Controllers\Api\Customer\NotificationController;
use App\Http\Controllers\Api\Customer\OrderController as ApiOrderController;
use App\Http\Controllers\Api\Customer\GiftCardController as ApiGiftCardController;
use App\Http\Controllers\Api\Customer\CustomerWalletController;
use App\Http\Controllers\Api\Customer\NewsletterController;
use App\Http\Controllers\Api\Customer\PaymentCallbackController;
use App\Http\Controllers\Api\Customer\PaymentHistoryController;

// ── Home composite (public) ───────────────────────────────────────────

use Illuminate\Support\Facades\Route;

        Route::get('home', [HomeController::class, 'index'])->name('customer.home.index');

        // ── Unified navigation tree (public) ─────────────────────────────────
        Route::get('nav', [NavigationController::class, 'index'])->name('customer.nav.index');

        // ── Product catalog (public) ──────────────────────────────────────────
        Route::prefix('products')->name('customer.products.')->group(function (): void {
            // ── Product detail shorthand: /products/v-{uuid} or /products/p-{uuid} ──────
            // v-{uuid} = VendorListing   →  delegates to ListingDetailController
            // p-{uuid} = AdminListing    →  delegates to ListingDetailController
            // This MUST be declared before other /products/* routes to avoid UUID being
            // captured as a product slug.
            Route::get('/{typeAndId}', [\App\Http\Controllers\Customer\ListingDetailController::class, 'showByTypeId'])
                ->where('typeAndId', '(v|p)-[0-9a-f-]{36}')
                ->name('by-type-id');

            Route::get('/', [ProductController::class, 'index'])->name('index');
        });

        // ── Listing detail (public) ───────────────────────────────────────────
        // GET /l/{identifier} — identifier IN (listing UUID, variant SKU, listing_ref, product slug)
        Route::get('l/{identifier}', [ListingDetailController::class, 'show'])
            ->name('customer.listing.show');

        // ── Unified browse (public) ───────────────────────────────────────────
        // GET /browse/{type}/{id}  — type IN (product, classified, travel); id = category UUID
        Route::get('browse/{type}/{id}', [BrowseController::class, 'show'])->name('customer.browse.show');

        // GET /travel — all active travel packages, unfiltered (same as browse/travel/all)
        Route::get('travel', [BrowseController::class, 'travelIndex'])->name('customer.travel.index');

        // ── Payment gateways (public) ──────────────────────────────────────────
        // GET /payment-gateways — all active gateways for this country, for info screens
        Route::get('payment-gateways', [ApiCheckoutController::class, 'availableGateways'])
            ->name('customer.payment-gateways.index');

        // ── Unified listing detail (public) ───────────────────────────────────
        // GET  /listings/{type}/{slug} — type IN (product, classified, travel)
        //   product:    slug = product slug
        //   classified: slug = listing_number (e.g. CL-XXXX)
        //   travel:     slug = package UUID
        Route::get('listings/{type}/{slug}', [ListingController::class, 'show'])
            ->name('customer.listings.show');

        // ── Similar classified listings (public — same category, excludes current) ──
        Route::get(
            'listings/classified/{slug}/similar',
            [ListingController::class, 'similarClassified']
        )->name('customer.listings.classified.similar');

        // ── Classified inquiry (authenticated) ────────────────────────────────
        // POST /listings/classified/{slug}/inquiries
        Route::post(
            'listings/classified/{slug}/inquiries',
            [ListingController::class, 'createInquiry']
        )->middleware('auth:customer')->name('customer.listings.classified.inquiries.store');

        // ── Travel booking (authenticated) ────────────────────────────────────
        // POST /listings/travel/{slug}/bookings
        Route::post(
            'listings/travel/{slug}/bookings',
            [ListingController::class, 'createBooking']
        )->middleware('auth:customer')->name('customer.listings.travel.bookings.store');

        // POST /listings/travel/{slug}/inquiries
        Route::post(
            'listings/travel/{slug}/inquiries',
            [\App\Http\Controllers\Customer\Api\TravelPackageInquiryController::class, 'store']
        )->name('customer.listings.travel.inquiries.store');

        // POST /listings/travel/{slug}/bookings/{booking_number}/contract
        Route::post(
            'listings/travel/{slug}/bookings/{booking_number}/contract',
            [ListingController::class, 'signContract']
        )->middleware('auth:customer')->name('customer.listings.travel.bookings.contract');

        // ── Unified listing type shortcuts ────────────────────────────────────────────
        // travel/{id}    → TravelPackage detail
        // classified/{id} → ClassifiedListing detail
        // These mirror the existing /listings/{type}/{slug} pattern but accept UUIDs directly.
        Route::get('travel/{id}', [\App\Http\Controllers\Customer\ListingController::class, 'show'])
            ->whereUuid('id')
            ->defaults('type', 'travel')
            ->name('customer.travel.show');

        Route::get('classified/{id}', [\App\Http\Controllers\Customer\ListingController::class, 'show'])
            ->whereUuid('id')
            ->defaults('type', 'classified')
            ->name('customer.classified.show');

        // ── Dual-mode catalog listings (vendor marketplace / nawy_now admin) ────
        // Mode selected via X-Listing-Type header (see ListingModeResolver).
        Route::prefix('catalog-listings')->name('customer.catalog-listings.')->group(function (): void {
            Route::get('/', [ApiListingController::class, 'index'])->name('index');
            Route::get('{identifier}', [ApiListingController::class, 'show'])->name('show');
            Route::post('{id}/shipping-estimate', [ApiListingController::class, 'shippingEstimate'])->name('shipping-estimate');
        });

        // Legacy alias: GET /categories/{slug} → browse/product/{id}
        Route::get(
            'categories/{slug}',
            function (\Illuminate\Http\Request $request, $country, string $slug) {
            $country = $request->attributes->get('country');

            $category = \App\Models\Category::where('slug', $slug)
                ->where('is_active', true)
                ->firstOrFail();

            return redirect()->route('customer.browse.show', [
                'country' => $country->site_code,
                'type' => 'product',
                'id' => $category->id,
            ], 301);
        }
        )->name('customer.categories.show.legacy');

        // ── Categories (public) ───────────────────────────────────────────────
        Route::prefix('categories')->name('customer.categories.')->group(function (): void {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
        });

        // ── Page Renderer (public) ────────────────────────────────────────────
        Route::get('pages/{type}', [PageController::class, 'show'])->name('customer.pages.show');

        // ── Page block click tracking (public — guests can click) ─────────────
        Route::post('blocks/{id}/click', [PageController::class, 'click'])
            ->middleware('throttle:60,1')
            ->name('customer.blocks.click');

        // ── Vendor storefront page (public) ───────────────────────────────────
        Route::get('vendors/{vendor_id}', [VendorPageController::class, 'show'])
            ->name('customer.vendors.show');

        // ── Brand page (public) ───────────────────────────────────────────────
        Route::get('brands/{id}', [BrandPageController::class, 'show'])
            ->name('customer.brands.show');

        // ── Coupon detail — public "Learn more" CTA (public) ──────────────────
        Route::get('coupons/{code}', [CouponController::class, 'show'])
            ->name('customer.coupons.show');

        // ── Search (public) ───────────────────────────────────────────────────
        Route::prefix('search')->name('customer.search.')->group(function (): void {
            Route::get('/', [SearchController::class, 'search'])->name('search');
            Route::get('suggestions', [SearchController::class, 'suggestions'])->name('suggestions');
        });

        // ── Newsletter (public — guest + authenticated) ───────────────────────
        Route::prefix('newsletter')->name('customer.api.newsletter.')->group(function (): void {
            Route::post('subscribe', [NewsletterController::class, 'subscribe'])
                ->middleware('throttle:10,1')
                ->name('subscribe');
            Route::get('unsubscribe', [NewsletterController::class, 'unsubscribe'])
                ->name('unsubscribe');
        });

        // ── Public auth endpoints ─────────────────────────────────────────────
        Route::prefix('auth')->name('customer.auth.')->group(function (): void {
            Route::post('register', [AuthController::class, 'register'])
                ->middleware('throttle:10,1')
                ->name('register');

            Route::post('login', [AuthController::class, 'login'])
                ->middleware('throttle:10,1')
                ->name('login');

            Route::post('refresh-token', [AuthController::class, 'refreshToken'])
                ->middleware('throttle:10,1')
                ->name('refresh');

            Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
                ->middleware('throttle:5,1')
                ->name('forgot-password');

            Route::post('reset-password', [AuthController::class, 'resetPassword'])
                ->middleware('throttle:5,1')
                ->name('reset-password');

            // Email verification — token from email link, no auth guard needed
            Route::post('verify-email', [AuthController::class, 'verifyEmail'])
                ->name('verify-email');
        });

        // ── Gift Card Storefront (browse & purchase gift cards) ──
        Route::prefix('gift-card-store')->name('customer.api.gift-card-store.')->group(function (): void {
            // Browse available denominations (public, no auth required)
            Route::get('available', [CustomerGiftCardStoreController::class, 'available'])->name('available');

            Route::middleware('auth:customer')->group(function (): void {
                Route::post('purchase', [CustomerGiftCardStoreController::class, 'purchase'])->name('purchase');
                Route::get('my-purchases', [CustomerGiftCardStoreController::class, 'myPurchases'])->name('my-purchases');
                Route::post('resend/{purchase}', [CustomerGiftCardStoreController::class, 'resend'])->name('resend');
            });
        });

        // Cart — guest + auth (session resolved via X-Cart-Token header)
        Route::prefix('cart')->name('customer.cart.')->middleware(['guest.cart.token', 'auth.optional'])->group(function (): void {
            Route::get('/', [CartController::class, 'show'])->name('show');
            Route::get('recommendations', [CartRecommendationsController::class, 'index'])->name('recommendations');
            Route::post('items', [CartController::class, 'addItem'])->name('items.add');
            Route::post('items/bulk', [CartController::class, 'addItems'])->name('items.add-bulk');
            Route::put('items/{id}', [CartController::class, 'updateItem'])->name('items.update');
            Route::patch('items/{id}/warranty', [CartController::class, 'updateItemWarranty'])->name('items.warranty.update');
            Route::delete('items/{id}', [CartController::class, 'removeItem'])->name('items.remove');
            Route::delete('/', [CartController::class, 'clear'])->name('clear');
            Route::post('coupon', [CartController::class, 'applyCoupon'])->name('coupon.apply');
            Route::delete('coupon', [CartController::class, 'removeCoupon'])->name('coupon.remove');
            Route::post('promo-code', [CartController::class, 'applyPromoCode'])->name('promo-code.apply');
            Route::delete('promo-code', [CartController::class, 'removePromoCode'])->name('promo-code.remove');
            Route::post('wallet', [CartController::class, 'toggleWallet'])->name('wallet.toggle');
        });

        // ── Authenticated endpoints ───────────────────────────────────────────
        Route::middleware('auth:customer')->group(function (): void {

            // Auth
            Route::prefix('auth')->name('customer.auth.')->group(function (): void {
                Route::post('logout', [AuthController::class, 'logout'])->name('logout');
                Route::get('me', [AuthController::class, 'me'])->name('me');
                Route::post('resend-verification', [AuthController::class, 'resendVerification'])
                    ->middleware('throttle:3,1')
                    ->name('resend-verification');
            });

            // Payment transaction history (read-only)
            Route::get('payment-history', [PaymentHistoryController::class, 'index'])
                ->name('customer.payment-history.index');

            // Profile
            Route::prefix('profile')->name('customer.profile.')->group(function (): void {
                Route::get('/', [ProfileController::class, 'show'])->name('show');
                Route::put('/', [ProfileController::class, 'update'])->name('update');
                Route::put('password', [ProfileController::class, 'updatePassword'])->name('password');
                Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');

                Route::get('qr-code', [QrCodeController::class, 'show'])->name('qr-code.show');
                Route::post('qr-code/regenerate', [QrCodeController::class, 'regenerate'])->name('qr-code.regenerate');
            });

            // Security settings
            Route::prefix('security')->name('customer.security.')->group(function (): void {
                Route::post('change-password', [SecurityController::class, 'changePassword'])->name('change-password');

                Route::post('verify-email/send-otp', [SecurityController::class, 'sendEmailVerificationOtp'])
                    ->middleware('throttle:5,1')
                    ->name('verify-email.send-otp');
                Route::post('verify-email/verify', [SecurityController::class, 'verifyEmailOtp'])
                    ->middleware('throttle:10,1')
                    ->name('verify-email.verify');

                Route::post('verify-phone/send-otp', [SecurityController::class, 'sendPhoneVerificationOtp'])
                    ->middleware('throttle:5,1')
                    ->name('verify-phone.send-otp');
                Route::post('verify-phone/verify', [SecurityController::class, 'verifyPhoneOtp'])
                    ->middleware('throttle:10,1')
                    ->name('verify-phone.verify');

                Route::get('active-sessions', [SecurityController::class, 'activeSessions'])->name('active-sessions');
                Route::delete('sessions/all', [SecurityController::class, 'revokeAllDevices'])->name('sessions.revoke-all');
                Route::delete('sessions/{device_token_id}', [SecurityController::class, 'revokeDevice'])->name('sessions.revoke');
            });

            // Wishlist (grouped — replaces the old flat single-list endpoints)
            Route::prefix('wishlist')->name('customer.wishlist.')->group(function (): void {
                // Groups
                Route::get('groups', [ApiWishlistController::class, 'indexGroups'])->name('groups.index');
                Route::post('groups', [ApiWishlistController::class, 'createGroup'])->name('groups.store');
                Route::put('groups/{groupId}', [ApiWishlistController::class, 'updateGroup'])->name('groups.update');
                Route::delete('groups/{groupId}', [ApiWishlistController::class, 'deleteGroup'])->name('groups.destroy');
                Route::get('groups/{groupId}', [ApiWishlistController::class, 'showGroup'])->name('groups.show');

                // Items — /items/move MUST be declared before /items/{itemId}
                // so Laravel does not interpret 'move' as an itemId
                Route::post('items', [ApiWishlistController::class, 'addItem'])->name('items.store');
                Route::post('items/move', [ApiWishlistController::class, 'moveItems'])->name('items.move');
                Route::delete('items/{itemId}', [ApiWishlistController::class, 'removeItem'])->name('items.destroy');

                // Utility
                Route::get('check', [ApiWishlistController::class, 'checkListing'])->name('check');
            });

            // Addresses
            Route::prefix('addresses')->name('customer.addresses.')->group(function (): void {
                Route::get('/', [AddressController::class, 'index'])->name('index');
                Route::post('/', [AddressController::class, 'store'])->name('store');
                Route::put('{address}', [AddressController::class, 'update'])->name('update');
                Route::delete('{address}', [AddressController::class, 'destroy'])->name('destroy');
                Route::put('{address}/set-default', [AddressController::class, 'setDefault'])->name('set-default');
            });

            // Receivers
            Route::prefix('receivers')->name('customer.receivers.')->group(function (): void {
                Route::get('/', [ReceiverController::class, 'index'])->name('index');
                Route::post('/', [ReceiverController::class, 'store'])->name('store');
                Route::put('{receiver}', [ReceiverController::class, 'update'])->name('update');
                Route::delete('{receiver}', [ReceiverController::class, 'destroy'])->name('destroy');
                Route::put('{receiver}/set-default', [ReceiverController::class, 'setDefault'])->name('set-default');
            });

            // Cart merge (auth only)
            Route::post('cart/merge', [CartController::class, 'mergeCart'])->name('customer.cart.merge');

            // Wallet
            Route::prefix('wallet')->name('customer.wallet.')->group(function (): void {
                Route::get('/', [WalletController::class, 'show'])->name('show');
                Route::get('transactions', [WalletController::class, 'transactions'])->name('transactions');
                Route::post('withdrawal', [WalletController::class, 'requestWithdrawal'])->name('withdrawal');
            });

            // Checkout
            Route::prefix('checkout')->name('customer.checkout.')->group(function (): void {
                Route::get('payment-options', [ApiCheckoutController::class, 'paymentOptions'])
                    ->name('payment-options');
                Route::get('shipping-methods', [CheckoutController::class, 'shippingMethods'])->name('shipping-methods');
                Route::post('prepare', [CheckoutController::class, 'prepare'])->name('prepare');
                Route::post('place-order', [CheckoutController::class, 'placeOrder'])
                    ->middleware('throttle:5,1')
                    ->name('place-order');
                Route::get('{order_number}/confirmation', [CheckoutController::class, 'confirmation'])->name('confirmation');

                Route::post('calculate', [ApiCheckoutController::class, 'calculate'])->name('calculate');
                Route::post('coupon/validate', [ApiCheckoutController::class, 'validateCoupon'])->name('coupon.validate');
                Route::post('place', [ApiCheckoutController::class, 'place'])
                    ->middleware('throttle:5,1')
                    ->name('place');
            });

            // Payment gateway redirect-back callbacks (no auth — gateway redirects browser here)
            Route::get('checkout/payment/success/{orderNumber}', [PaymentCallbackController::class, 'success'])
                ->name('checkout.success')
                ->withoutMiddleware(['auth:customer']);

            Route::get('checkout/payment/cancel/{orderNumber}', [PaymentCallbackController::class, 'cancel'])
                ->name('checkout.cancel')
                ->withoutMiddleware(['auth:customer']);

            // Orders
            Route::prefix('orders')->name('customer.orders.')->group(function (): void {
                Route::get('/', [OrderController::class, 'index'])->name('index');
                Route::get('{order_number}', [OrderController::class, 'show'])->name('show');
                Route::post('{order_number}/cancel', [OrderController::class, 'cancel'])->name('cancel');
                Route::post('{order_number}/returns', [ReturnController::class, 'store'])->name('returns.store');
                Route::post('{order_number}/disputes', [DisputeController::class, 'store'])->name('disputes.store');
                Route::post('{order_number}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
            });

            // Sub-order tracking
            Route::post('sub-orders/{id}/track', [OrderController::class, 'trackSubOrder'])
                ->name('customer.sub-orders.track');

            // Returns
            Route::prefix('returns')->name('customer.returns.')->group(function (): void {
                Route::get('/', [ReturnController::class, 'index'])->name('index');
                Route::get('{return_number}', [ReturnController::class, 'show'])->name('show');
            });

            // Disputes
            Route::prefix('disputes')->name('customer.disputes.')->group(function (): void {
                Route::get('/', [DisputeController::class, 'index'])->name('index');
                Route::get('{dispute_number}', [DisputeController::class, 'show'])->name('show');
                Route::post('{dispute_number}/messages', [DisputeController::class, 'addMessage'])->name('messages.store');
            });

            // Reviews
            Route::prefix('reviews')->name('customer.reviews.')->group(function (): void {
                Route::put('{id}', [ReviewController::class, 'update'])->name('update');
                Route::post('{id}/helpful', [ReviewController::class, 'helpful'])->name('helpful');
            });

            // Refunds
            Route::prefix('refunds')->name('customer.refunds.')->group(function (): void {
                Route::get('/', [RefundController::class, 'index'])->name('index');
                Route::get('{id}', [RefundController::class, 'show'])->name('show');
            });

            // Warranty claims
            Route::prefix('warranty-claims')->name('customer.warranty-claims.')->group(function (): void {
                Route::get('/', [WarrantyClaimController::class, 'index'])->name('index');
                Route::post('/', [WarrantyClaimController::class, 'store'])->name('store');
                Route::get('{id}', [WarrantyClaimController::class, 'show'])->name('show');
                Route::post('{id}/messages', [WarrantyClaimController::class, 'addMessage'])->name('messages.store');
            });

            // Warranty purchases
            Route::prefix('warranties')->name('customer.warranties.')->group(function (): void {
                Route::get('/', [WarrantyPurchaseController::class, 'index'])->name('index');
                Route::get('{id}', [WarrantyPurchaseController::class, 'show'])->name('show');
            });

            // Support tickets
            Route::prefix('support/tickets')->name('customer.support.tickets.')->group(function (): void {
                Route::get('/', [SupportTicketController::class, 'index'])->name('index');
                Route::post('/', [SupportTicketController::class, 'store'])->name('store');
                Route::get('{ticket_number}', [SupportTicketController::class, 'show'])->name('show');
                Route::post('{ticket_number}/messages', [SupportTicketController::class, 'addMessage'])->name('messages.store');
                Route::put('{ticket_number}/rate', [SupportTicketController::class, 'rate'])->name('rate');
            });

            // Notifications
            Route::prefix('notifications')->name('customer.notifications.')->group(function (): void {
                Route::get('/', [NotificationController::class, 'index'])->name('index');
                Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
                Route::patch('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
                Route::patch('{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
                Route::post('device-token', [NotificationController::class, 'registerDevice'])->name('device-token.store');
                Route::delete('device-token', [NotificationController::class, 'removeDevice'])->name('device-token.destroy');
                Route::get('preferences', [NotificationController::class, 'preferences'])->name('preferences.show');
                Route::put('preferences', [NotificationController::class, 'updatePreferences'])->name('preferences.update');
            });

            // Account dashboard
            Route::get('account/dashboard', [AccountController::class, 'dashboard'])
                ->name('customer.account.dashboard');

            // My classified listings (customer as seller)
            Route::prefix('account/classified-listings')->name('customer.account.classified-listings.')->group(function (): void {
                Route::get('/', [AccountController::class, 'listingsIndex'])->name('index');
                Route::post('/', [AccountController::class, 'listingsStore'])->name('store');
                Route::get('{listing_number}', [AccountController::class, 'listingsShow'])->name('show');
                Route::put('{listing_number}', [AccountController::class, 'listingsUpdate'])->name('update');
                Route::delete('{listing_number}', [AccountController::class, 'listingsDestroy'])->name('destroy');
                Route::get('{listing_number}/inquiries', [AccountController::class, 'listingInquiries'])->name('inquiries');
            });

            // My travel bookings
            Route::prefix('account/travel-bookings')->name('customer.account.travel-bookings.')->group(function (): void {
                Route::get('/', [AccountController::class, 'travelBookingsIndex'])->name('index');
                Route::get('{id}', [AccountController::class, 'travelBookingsShow'])->name('show');
                Route::post('{id}/cancel', [AccountController::class, 'travelBookingsCancel'])->name('cancel');
                Route::post('{id}/passport', [AccountController::class, 'travelBookingsUploadPassport'])->name('passport.upload');
            });

            // My classified inquiries (customer as buyer)
            Route::prefix('account/inquiries')->name('customer.account.inquiries.')->group(function (): void {
                Route::get('/', [AccountController::class, 'inquiriesIndex'])->name('index');
                Route::get('{id}', [AccountController::class, 'inquiriesShow'])->name('show');
            });

            // Orders (Api\Customer)
            Route::prefix('api-orders')->name('customer.api.orders.')->group(function (): void {
                Route::get('/', [ApiOrderController::class, 'index'])->name('index');
                Route::get('{orderNumber}', [ApiOrderController::class, 'show'])->name('show');
                Route::get('{orderNumber}/sub-orders/{subOrderNumber}', [ApiOrderController::class, 'showSubOrder'])->name('sub-orders.show');
                Route::get('{orderNumber}/tracking', [ApiOrderController::class, 'tracking'])->name('tracking');
                Route::post('{orderNumber}/cancel', [ApiOrderController::class, 'cancel'])->name('cancel');
                Route::get('{orderNumber}/invoice', [ApiOrderController::class, 'invoice'])->name('invoice');
            });

            // Wallet (Api\Customer)
            Route::prefix('api-wallet')->name('customer.api.wallet.')->group(function (): void {
                Route::get('/', [ApiWalletController::class, 'index'])->name('index');
                Route::get('transactions', [ApiWalletController::class, 'transactions'])->name('transactions');
                Route::post('withdrawal-request', [ApiWalletController::class, 'withdrawalRequest'])->name('withdrawal-request');
            });

            // Gift cards
            Route::prefix('gift-cards')->name('customer.api.gift-cards.')->group(function (): void {
                Route::post('validate', [ApiGiftCardController::class, 'validate'])->name('validate');
                Route::get('mine', [ApiGiftCardController::class, 'mine'])->name('mine');
            });

            // Gift-card wallet (CustomerWallet/GiftCardService-backed; distinct from
            // the owner_type/owner_id Wallet system above)
            Route::prefix('gift-card-wallet')->name('customer.api.gift-card-wallet.')->group(function (): void {
                Route::get('/', [CustomerWalletController::class, 'index'])->name('index');
                Route::post('redeem-gift-card', [CustomerWalletController::class, 'redeemGiftCard'])->name('redeem-gift-card');
                Route::get('transactions', [CustomerWalletController::class, 'transactions'])->name('transactions');
                Route::post('/redeem/voucher', [CustomerWalletController::class, 'redeemVoucher'])->name('redeem.voucher');
                Route::post('/gift-card/balance', [CustomerWalletController::class, 'giftCardBalance'])->name('gift_card.balance');
            });

            // Warranty
            Route::prefix('warranty')->name('customer.api.warranty.')->group(function (): void {
                Route::get('plans/{orderItemId}', [ApiWarrantyController::class, 'plans'])->name('plans');
                Route::get('purchases', [ApiWarrantyController::class, 'purchases'])->name('purchases');
                Route::get('claims', [ApiWarrantyController::class, 'claimsIndex'])->name('claims.index');
                Route::post('claims', [ApiWarrantyController::class, 'claimsStore'])->name('claims.store');
                Route::get('claims/{claimNumber}', [ApiWarrantyController::class, 'claimsShow'])->name('claims.show');
                Route::post('claims/{claimNumber}/messages', [ApiWarrantyController::class, 'claimsAddMessage'])->name('claims.messages.store');
            });
        });

        // Public review listing (outside auth guard)
        Route::get('products/{slug}/reviews', [ReviewController::class, 'indexByProduct'])
            ->name('customer.products.reviews');
