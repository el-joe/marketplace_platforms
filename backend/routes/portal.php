<?php

use App\Http\Controllers\Portal\AdSupportController;
use App\Http\Controllers\Portal\AdvertiseRequestController;
use App\Http\Controllers\Portal\BlogController;
use App\Http\Controllers\Portal\HelpCenterController;
use App\Http\Controllers\Portal\LandingController;
use App\Http\Controllers\Portal\RegistrationController;
use App\Models\Country;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal Routes — portal.noon.loc
| Public: landing page, registration, FAQ
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/faq', [LandingController::class, 'faq'])->name('faq');
Route::get('/how-it-works', [LandingController::class, 'howItWorks'])->name('how-it-works');
Route::get('/fulfillment', [LandingController::class, 'fulfillment'])->name('fulfillment');
Route::get('/smart-tools', [LandingController::class, 'smartTools'])->name('smart-tools');

// noon ads style seller/advertise/support pages mirror the storefront's country-prefixed
// routes. Laravel optional route parameters must trail the URI, so a leading {country} can't
// simply be marked "?" — instead these are registered once, canonically, under a required
// /{country}/... prefix, and a second time with no prefix. The no-prefix GET variants just
// redirect straight into the canonical URL for the resolved country (session pick, else the
// first active/launched country), so every link still works without an explicit country.
Route::prefix('{country}')->group(function () {
    // noon ads style seller landing — mirrors the storefront's country-prefixed routes
    Route::get('advertise/sellers', [LandingController::class, 'sellers'])->name('sellers');

    // noon ads style brand landing — mirrors advertise.noon.com/{locale}/brands
    Route::get('advertise/brands', [LandingController::class, 'advertiseBrands'])->name('advertise.brands');

    // noon ads style advertiser landing — mirrors advertise.noon.com/{locale}/advertisers
    Route::get('advertise/advertisers', [LandingController::class, 'advertiseAdvertisers'])->name('advertise.advertisers');

    // noon ads style product ads landing — mirrors advertise.noon.com/{locale}/product
    Route::get('advertise/product', [LandingController::class, 'advertiseProduct'])->name('advertise.product');

    // noon ads style display ads landing — mirrors advertise.noon.com/{locale}/display
    Route::get('advertise/display', [LandingController::class, 'advertiseDisplay'])->name('advertise.display');

    // noon ads style contact form — mirrors advertise.noon.com/{locale}/request
    Route::get('advertise/request', [LandingController::class, 'advertiseRequest'])->name('advertise.request');
    Route::post('advertise/request', [AdvertiseRequestController::class, 'store'])->name('advertise.request.store');

    // noon ads style Knowledge Hub — mirrors adsupport.noon.com/en/*
    Route::prefix('adsupport')->name('adsupport.')->group(function () {
        Route::get('/', [AdSupportController::class, 'index'])->name('index');
        Route::get('/collections/{collection}', [AdSupportController::class, 'collection'])->name('collections.show');
        Route::get('/articles/{article}', [AdSupportController::class, 'article'])->name('articles.show');
    });

    // noon Seller Help Center — mirrors helpcenter.noon.partners/{locale}/*
    Route::prefix('helpcenter')->name('helpcenter.')->group(function () {
        Route::get('/', [HelpCenterController::class, 'index'])->name('index');
        Route::get('/search', [HelpCenterController::class, 'search'])->name('search');
        Route::get('/category/{category}', [HelpCenterController::class, 'category'])->name('category.show');
        Route::get('/article/{article}', [HelpCenterController::class, 'article'])->name('article.show');
    });
});

$resolvedCountry = fn () => Country::resolveSiteCode(null);

Route::get('advertise/sellers', fn () => redirect()->route('portal.sellers', $resolvedCountry()));
Route::get('advertise/brands', fn () => redirect()->route('portal.advertise.brands', $resolvedCountry()));
Route::get('advertise/advertisers', fn () => redirect()->route('portal.advertise.advertisers', $resolvedCountry()));
Route::get('advertise/product', fn () => redirect()->route('portal.advertise.product', $resolvedCountry()));
Route::get('advertise/display', fn () => redirect()->route('portal.advertise.display', $resolvedCountry()));
Route::get('advertise/request', fn () => redirect()->route('portal.advertise.request', $resolvedCountry()));
Route::post('advertise/request', [AdvertiseRequestController::class, 'store']);

Route::prefix('adsupport')->group(function () use ($resolvedCountry) {
    Route::get('/', fn () => redirect()->route('portal.adsupport.index', $resolvedCountry()));
    Route::get('/collections/{collection}', fn ($collection) => redirect()->route('portal.adsupport.collections.show', [$resolvedCountry(), $collection]));
    Route::get('/articles/{article}', fn ($article) => redirect()->route('portal.adsupport.articles.show', [$resolvedCountry(), $article]));
});

Route::prefix('helpcenter')->group(function () use ($resolvedCountry) {
    Route::get('/', fn () => redirect()->route('portal.helpcenter.index', $resolvedCountry()));
    Route::get('/search', fn () => redirect()->route('portal.helpcenter.search', array_merge([$resolvedCountry()], request()->query())));
    Route::get('/category/{category}', fn ($category) => redirect()->route('portal.helpcenter.category.show', [$resolvedCountry(), $category]));
    Route::get('/article/{article}', fn ($article) => redirect()->route('portal.helpcenter.article.show', [$resolvedCountry(), $article]));
});

// Multi-step vendor registration
Route::get('/register', [RegistrationController::class, 'show'])->name('register');
Route::get('/register/success', [RegistrationController::class, 'success'])->name('register.success');
Route::post('/register/step/{step}', [RegistrationController::class, 'storeStep'])->where('step', '[1-3]')->name('register.step');
Route::get('/register/check-slug', [RegistrationController::class, 'checkSlug'])->name('register.check-slug');
Route::get('/register/cities', [RegistrationController::class, 'cities'])->name('register.cities');
Route::get('/register/document-requirements', [RegistrationController::class, 'documentRequirements'])->name('register.document-requirements');
Route::post('/register/upload', [RegistrationController::class, 'uploadDocument'])->name('register.upload');
Route::delete('/register/document', [RegistrationController::class, 'removeDocument'])->name('register.document.remove');
Route::post('/register/complete', [RegistrationController::class, 'complete'])->name('register.complete');

// Blog
Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::post('/posts/{post}/increment-views', [BlogController::class, 'incrementViews'])->name('posts.increment-views');
    Route::get('/{slug}', [BlogController::class, 'show'])->name('show');
});

// Language switcher
Route::get('/language/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['ar', 'en'], true), 404);
    session(['locale' => $locale]);
    return redirect()->back()->withFragment('');
})->name('language');

// Country switcher — mirrors the language switcher; stores the pick in session so
// non-{country}-prefixed portal pages (home, faq, etc.) also reflect the selection.
Route::get('/country/{country}', function (string $country) {
    $exists = Country::where('site_code', $country)->where('is_active', true)->exists();
    abort_unless($exists, 404);
    session(['portal_country' => $country]);
    return redirect()->back()->withFragment('');
})->name('country');
