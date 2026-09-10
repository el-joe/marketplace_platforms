<?php

use App\Http\Controllers\Api\Marketer\AdBookingController;
use App\Http\Controllers\Api\Marketer\AdSlotController;
use App\Http\Controllers\Api\Marketer\AuthController;
use App\Http\Controllers\Api\Marketer\CampaignController;
use App\Http\Controllers\Api\Marketer\DashboardController;
use App\Http\Controllers\Api\Marketer\InvitationController;
use App\Http\Controllers\Api\Marketer\ProfileController;
use App\Http\Controllers\Api\Marketer\ReportController;
use Illuminate\Support\Facades\Route;

// ── Auth (no guard) ───────────────────────────────────────────────────────
Route::post('/login',           [AuthController::class, 'login']);
Route::post('/register',        [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

// ── Authenticated ─────────────────────────────────────────────────────────
Route::middleware(['marketer.api.auth', 'marketer.api.active'])->group(function () {
    Route::post('/logout',         [AuthController::class, 'logout']);
    Route::post('/refresh',        [AuthController::class, 'refresh']);
    Route::get('/me',              [AuthController::class, 'me']);

    Route::get('/dashboard',       [DashboardController::class, 'index']);
    Route::get('/profile',         [ProfileController::class, 'show']);
    Route::post('/profile',        [ProfileController::class, 'update']);

    Route::get('/invitations',                          [InvitationController::class, 'index']);
    Route::post('/invitations/{invitation}/accept',     [InvitationController::class, 'accept']);
    Route::post('/invitations/{invitation}/reject',     [InvitationController::class, 'reject']);

    Route::get('/campaigns/active',                    [CampaignController::class, 'active']);
    Route::get('/campaigns/finished',                  [CampaignController::class, 'finished']);

    Route::get('/reports',                             [ReportController::class, 'index']);

    // Paid ad slots (AS-07) — marketer booking of admin-managed placements/page-block slots
    Route::prefix('ad-slots')->name('marketer.api.ad-slots.')->group(function (): void {
        Route::get('/',                    [AdSlotController::class, 'index'])->name('index');
        Route::get('destinations',         [AdSlotController::class, 'destinations'])->name('destinations'); // before {id}
        Route::get('{id}',                 [AdSlotController::class, 'show'])->name('show');
        Route::get('{id}/calendar',        [AdSlotController::class, 'calendar'])->name('calendar');
        Route::post('{id}/quote',          [AdSlotController::class, 'quote'])->name('quote');
    });
    Route::prefix('ad-bookings')->name('marketer.api.ad-bookings.')->group(function (): void {
        Route::get('/',                    [AdBookingController::class, 'index'])->name('index');
        Route::post('/',                   [AdBookingController::class, 'store'])->name('store');
        Route::get('{id}',                 [AdBookingController::class, 'show'])->name('show');
        Route::post('{id}/creative',       [AdBookingController::class, 'uploadCreative'])->name('creative');
        Route::post('{id}/submit',         [AdBookingController::class, 'submit'])->name('submit');
        Route::post('{id}/pay',            [AdBookingController::class, 'pay'])->name('pay');
        Route::post('{id}/cancel',         [AdBookingController::class, 'cancel'])->name('cancel');
        Route::get('{id}/stats',           [AdBookingController::class, 'stats'])->name('stats');
    });
});
