<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Locale switcher — works across all panels and the portal
|--------------------------------------------------------------------------
*/
Route::post('/locale/switch', function (Request $request) {
    $locale = $request->input('locale');
    abort_unless(in_array($locale, config('app.available_locales', ['ar', 'en'])), 422);
    $request->session()->put('locale', $locale);
    $request->session()->put('dir', $locale === 'ar' ? 'rtl' : 'ltr');
    \Carbon\Carbon::setLocale($locale);
    \Illuminate\Support\Facades\App::setLocale($locale);
    return back();
})->name('locale.switch')->middleware('web');


/*
|--------------------------------------------------------------------------
| Admin subdomain routes
|--------------------------------------------------------------------------
*/
$appDomain = env('APP_DOMAIN', 'localhost');
$adminDomain = env('APP_ADMIN_SUBDOMAIN', 'admin') . '.' . $appDomain;
$portalDomain = env('APP_PORTAL_SUBDOMAIN', 'portal') . '.' . $appDomain;
$partnerDomain = env('APP_PARTNER_SUBDOMAIN', 'partner') . '.' . $appDomain;
$deliveryDomain = env('APP_DELIVERY_SUBDOMAIN', 'delivery') . '.' . $appDomain;
$travelDomain = env('APP_TRAVEL_SUBDOMAIN', 'travel-agency') . '.' . $appDomain;
$carrierDomain = env('APP_CARRIER_SUBDOMAIN', 'carrier') . '.' . $appDomain;


Route::domain($adminDomain)->name('admin.')->group(function () {
    require __DIR__ . '/admin.php';
});

/*
|--------------------------------------------------------------------------
| Vendor Portal (public landing + registration)
| portal.noon.loc
|--------------------------------------------------------------------------
*/
Route::domain($portalDomain)->name('portal.')->group(
    base_path('routes/portal.php')
);

/*
|--------------------------------------------------------------------------
| Partner Panel (authenticated vendor area)
| partner.noon.loc
|--------------------------------------------------------------------------
*/
Route::domain($partnerDomain)->name('partner.')->group(
    base_path('routes/partner.php')
);

/*
|--------------------------------------------------------------------------
| Delivery Agent Panel (mobile-first)
| delivery.noon.loc
|--------------------------------------------------------------------------
*/
Route::domain($deliveryDomain)->group(
    base_path('routes/delivery.php')
);

/*
|--------------------------------------------------------------------------
| Travel Agency Portal
| travel-agency.noon.loc
|--------------------------------------------------------------------------
*/
Route::domain($travelDomain)->group(
    base_path('routes/travel.php')
);

/*
|--------------------------------------------------------------------------
| Carrier (Shipping Company Supervisor) Portal
| carrier.noon.loc
|--------------------------------------------------------------------------
*/
Route::domain($carrierDomain)->group(
    base_path('routes/carrier.php')
);

/*
|--------------------------------------------------------------------------
| Storefront — Classifieds Marketplace
| {country}.noon.loc/classifieds
|--------------------------------------------------------------------------
*/
Route::prefix('{country}/classifieds')
    ->middleware(['web'])
    ->name('classifieds.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Storefront\ClassifiedController::class, 'index'])
            ->name('index');
        Route::get('/map-data', [\App\Http\Controllers\Storefront\ClassifiedController::class, 'mapData'])
            ->name('map-data');

        // Auth-required routes (before /{listingNumber} wildcard)
        Route::middleware('auth:customer')->group(function () {
            Route::get('/create', [\App\Http\Controllers\Storefront\ClassifiedController::class, 'create'])
                ->name('create');
            Route::post('/draft', [\App\Http\Controllers\Storefront\ClassifiedController::class, 'storeDraft'])
                ->name('draft');
            Route::post('/{listing}/sign-contract', [\App\Http\Controllers\Storefront\ClassifiedController::class, 'signContract'])
                ->name('sign');
        });

        Route::get('/{listingNumber}', [\App\Http\Controllers\Storefront\ClassifiedController::class, 'show'])
            ->name('show');
        Route::post('/{listing}/inquire', [\App\Http\Controllers\Storefront\ClassifiedController::class, 'inquire'])
            ->name('inquire');
    });

/*
|--------------------------------------------------------------------------
| Storefront — Travel Packages
| {country}.noon.loc/travel
|--------------------------------------------------------------------------
*/
Route::prefix('{country}/travel')
    ->middleware(['web'])
    ->name('travel.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Storefront\TravelController::class, 'index'])
            ->name('index');
        Route::get('/booking/{booking}/confirmed', [\App\Http\Controllers\Storefront\TravelController::class, 'bookingConfirmed'])
            ->name('booking.confirmed')
            ->middleware('auth:customer');
        Route::get('/{package}', [\App\Http\Controllers\Storefront\TravelController::class, 'show'])
            ->name('show');
        Route::post('/{package}/inquire', [\App\Http\Controllers\Storefront\PublicTravelInquiryController::class, 'store'])
            ->name('packages.inquire')
            ->middleware('throttle:5,1');
        Route::middleware('auth:customer')->group(function () {
            Route::get('/{package}/book', [\App\Http\Controllers\Storefront\TravelController::class, 'bookForm'])
                ->name('book');
            Route::post('/{package}/book', [\App\Http\Controllers\Storefront\TravelController::class, 'book'])
                ->name('book.submit');
        });
    });

/*
|--------------------------------------------------------------------------
| Storefront — Virtual Try-On
| {country}.noon.loc/try-on/{listing}
| Only available for listings whose category has supports_virtual_tryon = 1
|--------------------------------------------------------------------------
*/
Route::prefix('{country}/try-on')
    ->middleware(['web'])
    ->name('tryon.')
    ->group(function () {
        Route::get('/{listing}', [\App\Http\Controllers\Storefront\TryOnController::class, 'create'])
            ->name('create');
        Route::post('/{listing}', [\App\Http\Controllers\Storefront\TryOnController::class, 'process'])
            ->name('process');
        Route::get('/status/{sessionId}', [\App\Http\Controllers\Storefront\TryOnController::class, 'status'])
            ->name('status');
    });


/*
|--------------------------------------------------------------------------
| Storefront — Radio
| {country}/radio
|--------------------------------------------------------------------------
*/
Route::prefix('{country}/radio')
    ->middleware(['web'])
    ->name('storefront.radio.')
    ->group(function () {
        Route::get('/',              [\App\Http\Controllers\Storefront\RadioController::class, 'index'])->name('index');
        Route::get('/{channel}',     [\App\Http\Controllers\Storefront\RadioController::class, 'player'])->name('player');
    });

// Session tracking (no country prefix — called from JS cross-page)
Route::post('/radio/session', [\App\Http\Controllers\Storefront\RadioController::class, 'trackSession'])
    ->middleware(['web'])
    ->name('storefront.radio.session');

// ─── Delivery Rating (customer rates carrier after delivery) ──────────────
// NOTE: carrier identity is NEVER returned to the customer — only rating stored
Route::post('/orders/{subOrder}/rate-delivery', [\App\Http\Controllers\Storefront\DeliveryRatingController::class, 'store'])
    ->middleware(['web', 'auth:customer'])
    ->name('storefront.orders.rate-delivery');

/*
|--------------------------------------------------------------------------
| Payment Gateway Webhooks (external POST — exempt from CSRF)
|--------------------------------------------------------------------------
*/
// ─── Blog view counter (public, throttled) ────────────────────────────────
Route::post('/blog/{post}/views', [\App\Http\Controllers\Admin\BlogPostController::class, 'incrementViews'])
    ->name('blog.posts.increment-views')
    ->middleware(['web', 'throttle:60,1']);

Route::post('/webhooks/payment/{gatewayCode}', [\App\Http\Controllers\WebhookController::class, 'payment'])
    ->name('webhooks.payment')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// ── Referral shortlink ────────────────────────────────────────────────────────
// Encodes config('app.url') . '/r/' . referral_code in customer QR codes.
// Redirects to the app registration page with the referral code as a query param.
// Mobile apps intercept this URL via universal links / app links.
Route::get('/r/{code}', function (string $code) {
    // Validate the code exists before redirecting to prevent open-redirect abuse.
    $exists = \App\Models\Customer::where('referral_code', strtoupper($code))
        ->where('status', 'active')
        ->exists();

    if (! $exists) {
        // Redirect to home silently — don't leak which codes are valid.
        return redirect('/');
    }

    // Redirect to the portal registration page with ref= query param.
    // The registration page (or mobile app) reads ?ref= and pre-fills the field.
    return redirect(url('/register?ref=' . strtoupper($code)));
})->name('referral.shortlink');
