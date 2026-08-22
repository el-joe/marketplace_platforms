<?php

use App\Http\Controllers\Api\Customer\CategoryController as ApiCategoryController;
use App\Http\Controllers\Api\Customer\CustomerCouponController;
use App\Http\Controllers\Api\Customer\DeviceTokenController as ApiDeviceTokenController;
use App\Http\Controllers\Api\Customer\ListingController as ApiListingController;
use App\Http\Controllers\Api\Customer\MiscController as ApiMiscController;
use App\Http\Controllers\Api\Customer\OtpController as ApiOtpController;
use App\Http\Controllers\Api\Customer\ProductDetailController;
use App\Http\Controllers\Api\Customer\ReturnRequestController as ApiReturnRequestController;
use App\Http\Controllers\Api\Customer\ReviewController as ApiReviewController;
use App\Http\Controllers\Customer\AppConfigController;
use App\Models\Country;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer API Routes — /api/customer/v1/{country}/...
| Guard: customer (JWT)
| Middleware: detect.country resolves {country} site_code → Country model
|--------------------------------------------------------------------------
*/

Route::prefix('v1/{country}')
    ->middleware('detect.country')
    ->group(function (): void {

        require __DIR__ . '/api_customer_v1.php';
    });

// ── Product by type-id shorthand (country-agnostic) ──────────────────────────
Route::get('v1/products/{typeAndId}', [\App\Http\Controllers\Customer\ListingDetailController::class, 'showByTypeId'])
    ->where('typeAndId', '(v|p)-[0-9a-f-]{36}')
    ->middleware('auth:customer')
    ->name('customer.products.by-type-id.agnostic');

// ── Product detail by variant + listing UUID (public, country-agnostic path —
// country resolved from the authenticated customer or the X-Country-Id header) ──
Route::get('v1/products/{variantId}/{listingId}', [ProductDetailController::class, 'show'])
    ->whereUuid(['variantId', 'listingId'])
    ->name('customer.products.show');

// ── Backward-compat: old slug-only product URLs (must come AFTER the UUID
// route above so they never intercept valid /products/{variantId}/{listingId} calls) ──
Route::get('v1/products/{productSlug}/{variantSlug}', [ProductDetailController::class, 'redirectBySlugAndVariant'])
    ->name('customer.products.redirect-by-slug-variant');

Route::get('v1/products/{productSlug}', [ProductDetailController::class, 'redirectBySlug'])
    ->name('customer.products.redirect-by-slug');

// ── Dual-mode categories (marketplace / nawy_now via X-Listing-Type header) ──
Route::prefix('v1')->middleware('auth:customer')->name('customer.dual-categories.')->group(function (): void {
    Route::get('/categories', [ApiCategoryController::class, 'index'])->name('index');
    Route::get('/categories/{slug}', [ApiCategoryController::class, 'show'])->name('show');
});

// ── App config (public, country-agnostic path — country_id is a query param) ──
Route::prefix('v1/app')->name('customer.app.')->group(function (): void {
    Route::get('config', [AppConfigController::class, 'config'])->name('config');
    Route::get('home', [AppConfigController::class, 'homePage'])->name('home');
});

// ── Return requests (country-agnostic path, scoped to customer_id) ──
Route::prefix('v1/return-requests')->middleware('auth:customer')->name('customer.api.return-requests.')->group(function (): void {
    Route::get('/', [ApiReturnRequestController::class, 'index'])->name('index');
    Route::post('/', [ApiReturnRequestController::class, 'store'])->name('store');
    Route::get('{returnNumber}', [ApiReturnRequestController::class, 'show'])->name('show');
    Route::post('{returnNumber}/messages', [ApiReturnRequestController::class, 'addMessage'])->name('messages.store');
});

// ── Coupon — Applied at Checkout (country-agnostic path, scoped to customer_id) ──
Route::middleware('auth:customer')->prefix('v1/coupons')->name('customer.api.coupons.')->group(function (): void {
    Route::post('/validate', [CustomerCouponController::class, 'validate'])->name('validate');
    Route::delete('/remove', [CustomerCouponController::class, 'remove'])->name('remove');
});

// ── Generic OTP (country-agnostic, public — no auth guard) ──
Route::prefix('v1/otp')->name('customer.api.otp.')->group(function (): void {
    Route::post('send', [ApiOtpController::class, 'send'])->name('send');
    Route::post('verify', [ApiOtpController::class, 'verify'])->name('verify');
});

// ── Flat listings alias (country-agnostic; reuses ApiListingController,
//    same controller backing the country-scoped catalog-listings group) ──
Route::prefix('v1/listings')->middleware('auth:customer')->name('customer.api.listings.')->group(function (): void {
    Route::get('/', [ApiListingController::class, 'index'])->name('index');
    Route::get('{identifier}', [ApiListingController::class, 'show'])->name('show');
    Route::post('{id}/shipping-estimate', [ApiListingController::class, 'shippingEstimate'])->name('shipping-estimate');
});

// ── Reviews (country-agnostic path, scoped to customer_id) ──
Route::prefix('v1/reviews')->middleware('auth:customer')->name('customer.api.reviews.')->group(function (): void {
    Route::post('/', [ApiReviewController::class, 'store'])->name('store');
    Route::get('mine', [ApiReviewController::class, 'mine'])->name('mine');
});

// ── Device tokens (country-agnostic path, scoped to customer_id) ──
Route::post('v1/device-tokens', [ApiDeviceTokenController::class, 'store'])
    ->middleware('auth:customer')
    ->name('customer.api.device-tokens.store');

// ── Misc (country-agnostic path, scoped to customer_id) ──
Route::middleware('auth:customer')->group(function (): void {
    Route::get('v1/countries', [ApiMiscController::class, 'countries'])->name('customer.api.countries.index');
    Route::get('v1/countries/{country}/cities', [ApiMiscController::class, 'cities'])->name('customer.api.countries.cities');
    Route::get('v1/shipping-methods', [ApiMiscController::class, 'shippingMethods'])->name('customer.api.shipping-methods.index');
});
