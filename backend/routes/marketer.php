<?php

use App\Http\Controllers\Marketer\AuthController;
use App\Http\Controllers\Marketer\CampaignController;
use App\Http\Controllers\Marketer\ContractController;
use App\Http\Controllers\Marketer\ClassifiedInquiryController;
use App\Http\Controllers\Marketer\ClassifiedListingController;
use App\Http\Controllers\Marketer\ConversationController;
use App\Http\Controllers\Marketer\CouponParticipationController;
use App\Http\Controllers\Marketer\ExclusiveContractController;
use App\Http\Controllers\Marketer\WantedListingController;
use App\Http\Controllers\Marketer\DashboardController;
use App\Http\Controllers\Marketer\FinanceController;
use App\Http\Controllers\Marketer\FlashSaleController;
use App\Http\Controllers\Marketer\InvitationController;
use App\Http\Controllers\Marketer\ListingController;
use App\Http\Controllers\Marketer\OrderController;
use App\Http\Controllers\Marketer\ProfileController;
use App\Http\Controllers\Marketer\PromoteBookingController;
use App\Http\Controllers\Marketer\PromoteController;
use App\Http\Controllers\Marketer\ReportController;
use App\Http\Controllers\Marketer\SampleController;
use App\Http\Controllers\Marketer\SpecialRequestController;
use App\Http\Controllers\Marketer\SupportController;
use App\Http\Controllers\NotificationController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['web', 'auth.marketer']]);

// ── Locale switcher ───────────────────────────────────────────────────────
Route::middleware('web')
    ->post('/locale/switch', function (Request $request) {
        $locale = $request->input('locale');
        abort_unless(in_array($locale, config('app.available_locales', ['ar', 'en'])), 422);
        $request->session()->put([
            'locale' => $locale,
            'locale_override' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
        ]);
        Carbon::setLocale($locale);
        App::setLocale($locale);

        return back();
    })->name('locale.switch');

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('web')->group(function () {

    // ── Guest routes ──────────────────────────────────────────────────────
    Route::middleware('guest:marketer')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.post');

        Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [AuthController::class, 'register'])->name('register.post');

        Route::get('/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot');
        Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('auth.forgot.send');
        Route::get('/reset-password/{token}', [AuthController::class, 'resetPassword'])->name('auth.reset');
        Route::post('/reset-password', [AuthController::class, 'updatePassword'])->name('auth.reset.update');
    });

    // ── Authenticated routes ───────────────────────────────────────────────
    Route::middleware('auth.marketer')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Dashboard / statistics
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Special requests (broker specialization matches)
        Route::prefix('special-requests')->name('special-requests.')->group(function () {
            Route::get('/', [SpecialRequestController::class, 'index'])->name('index');
            Route::patch('{id}/start', [SpecialRequestController::class, 'start'])->name('start');
            Route::get('{id}', [SpecialRequestController::class, 'show'])->name('show');
        });

        // Notifications
        Route::prefix('notifications')->name('notifications.')
            ->controller(NotificationController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/recent', 'recent')->name('recent');
                Route::get('/unread-count', 'unreadCount')->name('unread-count');
                Route::get('/unread', 'unread')->name('unread');
                Route::post('/mark-all-read', 'markAllRead')->name('mark-all-read');
                Route::post('/{id}/read', 'markRead')->name('mark-read');
            });

        // Profile
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/ad-price', [ProfileController::class, 'updateAdPrice'])->name('profile.ad-price.update');

        // Campaign invitations
        Route::prefix('invitations')->name('invitations.')->group(function () {
            Route::get('/', [InvitationController::class, 'index'])->name('index');
            Route::post('/{invitation}/accept', [InvitationController::class, 'accept'])->name('accept');
            Route::post('/{invitation}/reject', [InvitationController::class, 'reject'])->name('reject');
        });

        // Coupon participation invitations (client feature #3.2)
        Route::prefix('coupon-participation')->name('coupon-participation.')->group(function () {
            Route::get('/', [CouponParticipationController::class, 'index'])->name('index');
            Route::post('/{invitation}', [CouponParticipationController::class, 'store'])->name('store');
        });

        // Classified listings (open market ads)
        Route::prefix('classified-listings')->name('classified-listings.')->group(function () {
            Route::get('/', [ClassifiedListingController::class, 'index'])->name('index');
            Route::get('/create', [ClassifiedListingController::class, 'create'])->name('create');
            Route::post('/', [ClassifiedListingController::class, 'store'])->name('store');
            Route::get('/{listing}', [ClassifiedListingController::class, 'show'])->name('show');
            Route::get('/{listing}/edit', [ClassifiedListingController::class, 'edit'])->name('edit');
            Route::put('/{listing}', [ClassifiedListingController::class, 'update'])->name('update');
            Route::delete('/{listing}', [ClassifiedListingController::class, 'destroy'])->name('destroy');
            Route::post('/{listing}/toggle', [ClassifiedListingController::class, 'toggleStatus'])->name('toggle');
        });

        // Received inquiries
        Route::prefix('classified-inquiries')->name('classified-inquiries.')->group(function () {
            Route::get('/', [ClassifiedInquiryController::class, 'index'])->name('index');
            Route::get('/{inquiry}', [ClassifiedInquiryController::class, 'show'])->name('show');
            Route::patch('/{inquiry}/close', [ClassifiedInquiryController::class, 'close'])->name('close');
        });

        Route::prefix('conversations')->name('conversations.')->group(function () {
            Route::get('/', [ConversationController::class, 'index'])->name('index');
            Route::post('/', [ConversationController::class, 'store'])->name('store');
            Route::get('/{conversation}', [ConversationController::class, 'show'])->name('show');
            Route::post('/{conversation}/messages', [ConversationController::class, 'sendMessage'])->name('message');
        });

        // Wanted listings
        Route::prefix('wanted-listings')->name('wanted-listings.')->group(function () {
            Route::get('/', [WantedListingController::class, 'index'])->name('index');
            Route::get('/create', [WantedListingController::class, 'create'])->name('create');
            Route::post('/', [WantedListingController::class, 'store'])->name('store');
            Route::delete('/{wanted}', [WantedListingController::class, 'destroy'])->name('destroy');
        });

        // Exclusive contracts (read-only)
        Route::prefix('exclusive-contracts')->name('exclusive-contracts.')->group(function () {
            Route::get('/', [ExclusiveContractController::class, 'index'])->name('index');
            Route::get('/{contract}', [ExclusiveContractController::class, 'show'])->name('show');
            Route::get('/{contract}/download', [ExclusiveContractController::class, 'download'])->name('download');
        });

        // Active campaigns (accepted invitations)
        Route::get('/campaigns/active', [CampaignController::class, 'active'])->name('campaigns.active');

        // Finished campaigns
        Route::get('/campaigns/finished', [CampaignController::class, 'finished'])->name('campaigns.finished');

        // Samples
        Route::get('/samples', [SampleController::class, 'index'])->name('samples.index');
        Route::post('/samples/{sample}/address', [SampleController::class, 'submitAddress'])->name('samples.address');
        Route::post('/samples/{sample}/received', [SampleController::class, 'confirmReceipt'])->name('samples.received');

        // Orders (read-only — via referral conversions)
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::get('/{orderId}', [OrderController::class, 'show'])->name('show');
        });

        // Reports
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

        // Listings management
        Route::prefix('listings')->name('listings.')->group(function () {
            Route::get('/', [ListingController::class, 'index'])->name('index');
            Route::get('/create', [ListingController::class, 'create'])->name('create');
            Route::post('/', [ListingController::class, 'store'])->name('store');
            Route::get('/search-products', [ListingController::class, 'searchProducts'])->name('search-products');
            Route::post('/{listing}/toggle-status', [ListingController::class, 'toggleStatus'])->name('toggle-status');
            Route::patch('/{listing}/price', [ListingController::class, 'updatePrice'])->name('update-price');
            Route::get('/{listing}/promo-badges', [ListingController::class, 'promoBadges'])->name('promo-badges.edit');
            Route::put('/{listing}/promo-badges', [ListingController::class, 'updatePromoBadges'])->name('promo-badges.update');
            Route::delete('/{listing}', [ListingController::class, 'destroy'])->name('destroy');
        });

        // enhancement.md P-16: onboarding contract acceptance gate.
        Route::get('/contract', [ContractController::class, 'show'])->name('contract.show');
        Route::post('/contract/accept', [ContractController::class, 'accept'])->name('contract.accept');

        // Finance: commissions, wallet, payout (withdrawal) requests
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/commissions', [FinanceController::class, 'commissions'])->name('commissions');
            Route::get('/wallet', [FinanceController::class, 'wallet'])->name('wallet');
            Route::post('/wallet/withdraw', [FinanceController::class, 'requestWithdrawal'])->name('wallet.withdraw');
        });

        // Promote: paid ad slot booking (AS-07)
        Route::prefix('promote')->name('promote.')->group(function () {
            Route::get('/', [PromoteController::class, 'index'])->name('index');
            Route::get('/slots/{slot}', [PromoteController::class, 'show'])->name('show');
            Route::get('/slots/{slot}/calendar', [PromoteController::class, 'calendar'])->name('calendar');
            Route::post('/slots/{slot}/quote', [PromoteController::class, 'quote'])->name('quote');
            Route::get('/destinations', [PromoteController::class, 'destinations'])->name('destinations');
            Route::get('/wallet-balance', [PromoteController::class, 'walletBalance'])->name('wallet-balance');

            Route::prefix('bookings')->name('bookings.')->group(function () {
                Route::get('/', [PromoteBookingController::class, 'index'])->name('index');
                Route::post('/datatable', [PromoteBookingController::class, 'datatable'])->name('datatable');
                Route::post('/', [PromoteBookingController::class, 'store'])->name('store');
                Route::get('/{booking}', [PromoteBookingController::class, 'show'])->name('show');
                Route::post('/{booking}/creative', [PromoteBookingController::class, 'uploadCreative'])->name('creative');
                Route::post('/{booking}/submit', [PromoteBookingController::class, 'submit'])->name('submit');
                Route::post('/{booking}/pay', [PromoteBookingController::class, 'pay'])->name('pay');
                Route::post('/{booking}/cancel', [PromoteBookingController::class, 'cancel'])->name('cancel');
                Route::get('/{booking}/stats', [PromoteBookingController::class, 'stats'])->name('stats');
            });
        });

        // Flash sale invitations
        Route::prefix('flash-sales')->name('flash-sales.')->group(function () {
            Route::get('/', [FlashSaleController::class, 'index'])->name('index');
            Route::post('/{invitation}/accept', [FlashSaleController::class, 'accept'])->name('accept');
            Route::post('/{invitation}/decline', [FlashSaleController::class, 'decline'])->name('decline');
        });

        // Support tickets
        Route::prefix('support')->name('support.')->group(function () {
            Route::get('/', [SupportController::class, 'index'])->name('index');
            Route::get('/create', [SupportController::class, 'create'])->name('create');
            Route::post('/', [SupportController::class, 'store'])->name('store');
            Route::get('/{ticketNumber}', [SupportController::class, 'show'])->name('show');
            Route::post('/{ticketNumber}/reply', [SupportController::class, 'reply'])->name('reply');
            Route::post('/{ticketNumber}/close', [SupportController::class, 'close'])->name('close');
        });
    });
});
