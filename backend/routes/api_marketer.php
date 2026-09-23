<?php

use App\Http\Controllers\Api\Marketer\AdBookingController;
use App\Http\Controllers\Api\Marketer\AdSlotController;
use App\Http\Controllers\Api\Marketer\AuthController;
use App\Http\Controllers\Api\Marketer\CampaignController;
use App\Http\Controllers\Api\Marketer\ContractController;
use App\Http\Controllers\Api\Marketer\DashboardController;
use App\Http\Controllers\Api\Marketer\FinanceController;
use App\Http\Controllers\Api\Marketer\InvitationController;
use App\Http\Controllers\Api\Marketer\ListingController;
use App\Http\Controllers\Api\Marketer\ProfileController;
use App\Http\Controllers\Api\Marketer\ReportController;
use App\Http\Controllers\Api\Marketer\NotificationController;
use App\Http\Controllers\Api\Marketer\ClassifiedListingController;
use App\Http\Controllers\Api\Marketer\ClassifiedInquiryController;
use App\Http\Controllers\Api\Marketer\WantedListingController;
use App\Http\Controllers\Api\Marketer\ExclusiveContractController;
use App\Http\Controllers\Api\Marketer\CouponParticipationController;
use App\Http\Controllers\Api\Marketer\ConversationController;
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
    Route::get('/commission-rules', [\App\Http\Controllers\Api\Marketer\CommissionRuleController::class, 'index']);
    Route::get('/profile',         [ProfileController::class, 'show']);
    Route::post('/profile',        [ProfileController::class, 'update']);

    Route::get('/special-requests',      [\App\Http\Controllers\Api\Marketer\SpecialRequestController::class, 'index']);
    Route::patch('/special-requests/{id}/start', [\App\Http\Controllers\Api\Marketer\SpecialRequestController::class, 'start']);
    Route::get('/special-requests/{id}', [\App\Http\Controllers\Api\Marketer\SpecialRequestController::class, 'show']);

    Route::get('/invitations',                          [InvitationController::class, 'index']);
    Route::post('/invitations/{invitation}/accept',     [InvitationController::class, 'accept']);
    Route::post('/invitations/{invitation}/reject',     [InvitationController::class, 'reject']);

    Route::get('/campaigns/active',                    [CampaignController::class, 'active']);
    Route::get('/campaigns/finished',                  [CampaignController::class, 'finished']);

    Route::get('/reports',                             [ReportController::class, 'index']);

    Route::get('/contract',          [ContractController::class, 'show']);
    Route::post('/contract/accept',  [ContractController::class, 'accept']);

    // enhancement.md P-16 task 2: API parity — listings (CRUD subset), finance/wallet/withdrawals.
    Route::get('/listings',                             [ListingController::class, 'index']);
    Route::post('/listings/{listing}/toggle',           [ListingController::class, 'toggleStatus']);
    Route::post('/listings/{listing}/price',            [ListingController::class, 'updatePrice']);
    Route::get('/listings/{listing}/promo-badges',      [ListingController::class, 'promoBadges']);
    Route::put('/listings/{listing}/promo-badges',      [ListingController::class, 'updatePromoBadges']);
    Route::delete('/listings/{listing}',                [ListingController::class, 'destroy']);

    Route::get('/finance/commissions',                  [FinanceController::class, 'commissions']);
    Route::get('/finance/wallet',                       [FinanceController::class, 'wallet']);
    Route::post('/finance/withdrawals',                 [FinanceController::class, 'requestWithdrawal']);

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

    Route::prefix('notifications')->name('marketer.api.notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllRead'])->name('mark-all-read');
        Route::post('/{id}/read', [NotificationController::class, 'markRead'])->name('mark-read');
    });
    Route::prefix('classified-listings')->name('marketer.api.classified.')->group(function () {
        Route::get('/', [ClassifiedListingController::class, 'index'])->name('index');
        Route::post('/', [ClassifiedListingController::class, 'store'])->name('store');
        Route::get('/{id}', [ClassifiedListingController::class, 'show'])->name('show');
        Route::put('/{id}', [ClassifiedListingController::class, 'update'])->name('update');
        Route::delete('/{id}', [ClassifiedListingController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/toggle', [ClassifiedListingController::class, 'toggleStatus'])->name('toggle');
    });
    Route::prefix('classified-inquiries')->name('marketer.api.inquiries.')->group(function () {
        Route::get('/', [ClassifiedInquiryController::class, 'index'])->name('index');
        Route::get('/{id}', [ClassifiedInquiryController::class, 'show'])->name('show');
        Route::patch('/{id}/close', [ClassifiedInquiryController::class, 'close'])->name('close');
    });
    Route::prefix('wanted-listings')->name('marketer.api.wanted.')->group(function () {
        Route::get('/', [WantedListingController::class, 'index'])->name('index');
        Route::post('/', [WantedListingController::class, 'store'])->name('store');
        Route::delete('/{id}', [WantedListingController::class, 'destroy'])->name('destroy');
    });
    Route::prefix('exclusive-contracts')->name('marketer.api.contracts.')->group(function () {
        Route::get('/', [ExclusiveContractController::class, 'index'])->name('index');
        Route::get('/{id}', [ExclusiveContractController::class, 'show'])->name('show');
    });
    Route::prefix('coupon-participation')->name('marketer.api.coupon.')->group(function () {
        Route::get('/', [CouponParticipationController::class, 'index'])->name('index');
        Route::post('/{invitation}', [CouponParticipationController::class, 'store'])->name('store');
    });
    Route::prefix('conversations')->name('marketer.api.conversations.')->group(function () {
        Route::get('/', [ConversationController::class, 'index'])->name('index');
        Route::post('/', [ConversationController::class, 'store'])->name('store');
        Route::get('/{id}', [ConversationController::class, 'show'])->name('show');
        Route::post('/{id}/messages', [ConversationController::class, 'sendMessage'])->name('message');
        Route::post('/{id}/messages/read', [ConversationController::class, 'markRead'])->name('read');
    });
});
