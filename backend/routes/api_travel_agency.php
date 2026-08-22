<?php

use App\Http\Controllers\Api\TravelAgencyPortal\AuthController;
use App\Http\Controllers\Api\TravelAgencyPortal\BookingController;
use App\Http\Controllers\Api\TravelAgencyPortal\DashboardController;
use App\Http\Controllers\Api\TravelAgencyPortal\FcmDeviceController;
use App\Http\Controllers\Api\TravelAgencyPortal\NotificationController;
use App\Http\Controllers\Api\TravelAgencyPortal\PackageController;
use App\Http\Controllers\Api\TravelAgencyPortal\PackageInquiryController;
use App\Http\Controllers\Api\TravelAgencyPortal\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Travel Agency API Routes — /api/travel-agency/v1/...
| Guard: travel_agencies (JWT via tymon/jwt-auth)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.travel-agency.')->group(function (): void {

    // ── Auth (public) ─────────────────────────────────────────────────────────
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->name('login')
            ->middleware('throttle:10,1');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });

    // ── Authenticated ─────────────────────────────────────────────────────────
    Route::middleware('auth:travel_agencies')->group(function (): void {

        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ── Packages ──────────────────────────────────────────────────────────
        // cities-for-country/submit/media/contract must be declared before the
        // apiResource's {package} routes to avoid route-model-binding ambiguity.
        Route::prefix('packages')->name('packages.')->group(function (): void {
            Route::get('countries', [PackageController::class, 'countries'])->name('countries');
            Route::get('currencies', [PackageController::class, 'currencies'])->name('currencies');
            Route::get('cities-for-country/{travelCountryId}', [PackageController::class, 'citiesForCountry'])->name('cities-for-country');
            Route::post('{package}/submit', [PackageController::class, 'submitForReview'])->name('submit');
            Route::delete('{package}/media/{media}', [PackageController::class, 'destroyMedia'])->name('media.destroy');
            Route::get('{package}/contract', [PackageController::class, 'downloadContract'])->name('contract.download');
        });
        Route::apiResource('packages', PackageController::class);

        // ── Bookings ──────────────────────────────────────────────────────────
        // customer-search must be declared before the apiResource's {booking}
        // show route to avoid it being swallowed by route-model binding.
        Route::get('bookings/customer-search', [BookingController::class, 'customerSearch'])->name('bookings.customer-search');
        Route::patch('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');
        Route::apiResource('bookings', BookingController::class)
            ->only(['index', 'store', 'show'])
            ->parameters(['bookings' => 'booking']);

        // ── Package Inquiries (lead management) ──────────────────────────────
        Route::prefix('inquiries')->name('inquiries.')->group(function (): void {
            Route::get('/', [PackageInquiryController::class, 'index'])->name('index');
            Route::post('{inquiry}/contacted', [PackageInquiryController::class, 'markContacted'])->name('contacted');
            Route::post('{inquiry}/convert', [PackageInquiryController::class, 'convertToBooking'])->name('convert');
            Route::post('{inquiry}/close', [PackageInquiryController::class, 'close'])->name('close');
        });

        // ── Profile ───────────────────────────────────────────────────────────
        Route::prefix('profile')->name('profile.')->group(function (): void {
            Route::get('/', [ProfileController::class, 'show'])->name('show');
            Route::put('/', [ProfileController::class, 'update'])->name('update');
        });

        // ── Notifications ─────────────────────────────────────────────────────
        Route::prefix('notifications')->name('notifications.')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('recent', [NotificationController::class, 'recent'])->name('recent');
            Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::post('mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
            Route::post('{id}/read', [NotificationController::class, 'markRead'])->name('mark-read');
        });

        // ── FCM Device Registration ───────────────────────────────────────────
        Route::prefix('fcm')->name('fcm.')->group(function (): void {
            Route::post('register-device', [FcmDeviceController::class, 'register'])->name('register');
            Route::post('unregister-device', [FcmDeviceController::class, 'unregister'])->name('unregister');
        });
    });
});
