<?php

use App\Http\Controllers\TravelAgencyPortal\AuthController;
use App\Http\Controllers\TravelAgencyPortal\BankAccountController;
use App\Http\Controllers\TravelAgencyPortal\BookingController;
use App\Http\Controllers\TravelAgencyPortal\CampaignController;
use App\Http\Controllers\TravelAgencyPortal\ChangeRequestController;
use App\Http\Controllers\TravelAgencyPortal\DashboardController;
use App\Http\Controllers\TravelAgencyPortal\FinanceController;
use App\Http\Controllers\TravelAgencyPortal\PackageController;
use App\Http\Controllers\TravelAgencyPortal\PackageInquiryController;
use App\Http\Controllers\TravelAgencyPortal\PerformanceController;
use App\Http\Controllers\TravelAgencyPortal\ProfileController;
use App\Http\Controllers\TravelAgencyPortal\ReportController;
use App\Http\Controllers\TravelAgencyPortal\RoleController;
use App\Http\Controllers\TravelAgencyPortal\SupportController;
use App\Http\Controllers\TravelAgencyPortal\TeamController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// ── Locale switcher (travel-agency subdomain) ─────────────────────────────
Route::middleware('web')
    ->post('/locale/switch', function (\Illuminate\Http\Request $request) {
        $locale = $request->input('locale');
        abort_unless(in_array($locale, config('app.available_locales', ['ar', 'en'])), 422);
        $request->session()->put([
            'locale'          => $locale,
            'locale_override' => $locale,
            'dir'             => $locale === 'ar' ? 'rtl' : 'ltr',
        ]);
        \Carbon\Carbon::setLocale($locale);
        \Illuminate\Support\Facades\App::setLocale($locale);
        return back();
    })->name('travel-agency.locale.switch');

Route::name('travel-agency.')
    ->group(function () {

        Broadcast::routes(['middleware' => ['web', 'auth.travel_agency']]);

        // ── Guest ─────────────────────────────────────────────────────────────
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.post');

        // ── Authenticated ─────────────────────────────────────────────────────
        Route::middleware(['auth.travel_agency'])->group(function () {

            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

            // ── Notifications ───────────────────────────────────────────────────
            Route::prefix('notifications')->name('notifications.')
                ->controller(NotificationController::class)
                ->group(function () {
                    Route::get('/',              'index')->name('index');
                    Route::get('/recent',        'recent')->name('recent');
                    Route::get('/unread-count',  'unreadCount')->name('unread-count');
                    Route::post('/mark-all-read','markAllRead')->name('mark-all-read');
                    Route::post('/{id}/read',    'markRead')->name('mark-read');
                });

            // Dashboard
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

            // Packages
            Route::prefix('packages')->name('packages.')->group(function () {
                Route::get('/', [PackageController::class, 'index'])->name('index')->middleware('travel_agency.can:packages.view');
                Route::get('/export', [PackageController::class, 'export'])->name('export')->middleware('travel_agency.can:packages.view');
                Route::get('/create', [PackageController::class, 'create'])->name('create')->middleware('travel_agency.can:packages.create');
                Route::post('/', [PackageController::class, 'store'])->name('store')->middleware('travel_agency.can:packages.create');
                Route::get('/cities-for-country/{travelCountryId}', [PackageController::class, 'citiesForCountry'])->name('cities-for-country')->middleware('travel_agency.can:packages.view');
                Route::get('/{package}', [PackageController::class, 'show'])->name('show')->middleware('travel_agency.can:packages.view');
                Route::get('/{package}/edit', [PackageController::class, 'edit'])->name('edit')->middleware('travel_agency.can:packages.edit');
                Route::put('/{package}', [PackageController::class, 'update'])->name('update')->middleware('travel_agency.can:packages.edit');
                Route::post('/{package}/submit', [PackageController::class, 'submitForReview'])->name('submit')->middleware('travel_agency.can:packages.publish');
                Route::post('/{package}/withdraw', [PackageController::class, 'withdraw'])->name('withdraw')->middleware('travel_agency.can:packages.unpublish');
                Route::delete('/{package}/media/{media}', [PackageController::class, 'destroyMedia'])->name('media.destroy')->middleware('travel_agency.can:packages.edit');
                Route::get('/{package}/contract', [PackageController::class, 'downloadContract'])->name('contract.download')->middleware('travel_agency.can:packages.view');
            });

            // Bookings
            Route::prefix('bookings')->name('bookings.')->group(function () {
                Route::get('/', [BookingController::class, 'index'])->name('index')->middleware('travel_agency.can:bookings.view');
                Route::get('/export', [BookingController::class, 'export'])->name('export')->middleware('travel_agency.can:bookings.view');
                Route::get('/create', [BookingController::class, 'create'])->name('create')->middleware('travel_agency.can:bookings.create');
                Route::post('/', [BookingController::class, 'store'])->name('store')->middleware('travel_agency.can:bookings.create');
                Route::get('/customer-search', [BookingController::class, 'customerSearch'])->name('customer-search')->middleware('travel_agency.can:bookings.create');
                Route::get('/{booking}', [BookingController::class, 'show'])->name('show')->middleware('travel_agency.can:bookings.view');
                Route::patch('/{booking}/status', [BookingController::class, 'updateStatus'])->name('status')->middleware('travel_agency.can:bookings.manage');
                Route::post('/{booking}/passport', [BookingController::class, 'uploadPassport'])->name('passport.upload')->middleware('travel_agency.can:bookings.manage');
                Route::get('/{booking}/passport/download', [BookingController::class, 'downloadPassport'])->name('passport.download')->middleware('travel_agency.can:bookings.view');
            });

            // Campaign Offers
            Route::prefix('campaigns')->name('campaigns.')->group(function () {
                Route::get('/',                                   [CampaignController::class, 'index'])->name('index')->middleware('travel_agency.can:campaigns.view');
                Route::get('/export',                              [CampaignController::class, 'export'])->name('export')->middleware('travel_agency.can:campaigns.view');
                Route::get('/create',                              [CampaignController::class, 'create'])->name('create')->middleware('travel_agency.can:campaigns.create');
                Route::post('/',                                   [CampaignController::class, 'store'])->name('store')->middleware('travel_agency.can:campaigns.create');
                Route::get('/packages/search',                     [CampaignController::class, 'searchPackages'])->name('packages.search')->middleware('travel_agency.can:campaigns.create');
                Route::get('/{offer}',                             [CampaignController::class, 'show'])->name('show')->middleware('travel_agency.can:campaigns.view');
                Route::post('/{offer}/submit',                     [CampaignController::class, 'submitForReview'])->name('submit')->middleware('travel_agency.can:campaigns.edit');
                Route::post('/{offer}/pause',                      [CampaignController::class, 'pauseOffer'])->name('pause')->middleware('travel_agency.can:campaigns.manage');
                Route::post('/{offer}/resume',                     [CampaignController::class, 'resumeOffer'])->name('resume')->middleware('travel_agency.can:campaigns.manage');
                Route::delete('/{offer}',                          [CampaignController::class, 'destroy'])->name('destroy')->middleware('travel_agency.can:campaigns.manage');
                Route::post('/{offer}/invite',                     [CampaignController::class, 'invite'])->name('invite')->middleware('travel_agency.can:campaigns.manage');
                Route::delete('/invitations/{invitation}/revoke',  [CampaignController::class, 'revokeInvitation'])->name('invitations.revoke')->middleware('travel_agency.can:campaigns.manage');
            });

            // Package Inquiries (lead management)
            Route::prefix('inquiries')->name('inquiries.')->group(function () {
                Route::get('/', [PackageInquiryController::class, 'index'])->name('index')->middleware('travel_agency.can:inquiries.view');
                Route::get('/export', [PackageInquiryController::class, 'export'])->name('export')->middleware('travel_agency.can:inquiries.view');
                Route::post('/{inquiry}/contacted', [PackageInquiryController::class, 'markContacted'])->name('contacted')->middleware('travel_agency.can:inquiries.manage');
                Route::post('/{inquiry}/convert', [PackageInquiryController::class, 'convertToBooking'])->name('convert')->middleware('travel_agency.can:inquiries.manage');
                Route::post('/{inquiry}/close', [PackageInquiryController::class, 'close'])->name('close')->middleware('travel_agency.can:inquiries.close');
            });

            // Profile
            Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

            // Team management
            Route::prefix('team')->name('team.')->controller(TeamController::class)->group(function () {
                Route::get('/', 'index')->name('index')->middleware('travel_agency.can:team.view');
                Route::get('/export', 'export')->name('export')->middleware('travel_agency.can:team.view');
                Route::get('/datatable', 'datatable')->name('datatable')->middleware('travel_agency.can:team.view');
                Route::get('/create', 'create')->name('create')->middleware('travel_agency.can:team.invite');
                Route::post('/', 'store')->name('store')->middleware('travel_agency.can:team.invite');
                Route::get('/{member}/edit', 'edit')->name('edit')->middleware('travel_agency.can:team.manage');
                Route::put('/{member}', 'update')->name('update')->middleware('travel_agency.can:team.manage');
                Route::post('/{member}/toggle-status', 'toggleStatus')->name('toggle-status')->middleware('travel_agency.can:team.manage');
                Route::post('/{member}/force-password-reset', 'forcePasswordReset')->name('force-password-reset')->middleware('travel_agency.can:team.manage');
                Route::delete('/{member}', 'destroy')->name('destroy')->middleware('travel_agency.can:team.manage');
                Route::post('/{member}/restore', 'restore')->name('restore')->middleware('travel_agency.can:team.manage');
            });

            // Bank accounts (feature-flagged, owner-only — see config/features.php)
            Route::prefix('bank-accounts')->name('bank-accounts.')->controller(BankAccountController::class)->group(function () {
                Route::get('/', 'index')->name('index')->middleware('travel_agency.can:bank_accounts.view');
                Route::post('/', 'store')->name('store')->middleware('travel_agency.can:bank_accounts.manage');
                Route::post('/{account}/set-primary', 'setPrimary')->name('set-primary')->middleware('travel_agency.can:bank_accounts.manage');
                Route::delete('/{account}', 'destroy')->name('destroy')->middleware('travel_agency.can:bank_accounts.manage');
            });

            // Change requests (locked-section edits, e.g. bank accounts)
            Route::prefix('change-requests')->name('change-requests.')->controller(ChangeRequestController::class)->group(function () {
                Route::get('/', 'index')->name('index')->middleware('travel_agency.can:bank_accounts.view');
                Route::post('/{changeRequest}/cancel', 'cancel')->name('cancel')->middleware('travel_agency.can:bank_accounts.manage');
            });

            // Reports
            Route::prefix('reports')->name('reports.')->controller(ReportController::class)
                ->middleware('travel_agency.can:reports.view')
                ->group(function () {
                    Route::get('/revenue', 'revenue')->name('revenue');
                    Route::get('/bookings', 'bookings')->name('bookings');
                    Route::get('/packages', 'packages')->name('packages');
                    Route::get('/revenue/export', 'exportRevenue')->name('revenue.export');
                    Route::get('/bookings/export', 'exportBookings')->name('bookings.export');
                });

            // Finance
            Route::prefix('finance')->name('finance.')->controller(FinanceController::class)->group(function () {
                Route::get('/revenue', 'revenue')->name('revenue')->middleware('travel_agency.can:reports.view');
                Route::get('/payouts', 'payouts')->name('payouts')->middleware('travel_agency.can:reports.view');
                Route::get('/wallet', 'wallet')->name('wallet')->middleware('travel_agency.can:bank_accounts.view');
                Route::post('/wallet/withdraw', 'requestWithdrawal')->name('wallet.withdraw')->middleware('travel_agency.can:bank_accounts.view');
                Route::get('/sales-report', 'salesReport')->name('sales-report')->middleware('travel_agency.can:reports.view');
                Route::get('/sales-report/export', 'exportSalesReport')->name('sales-report.export')->middleware('travel_agency.can:reports.export');
            });

            // Support tickets
            Route::prefix('support')->name('support.')->controller(SupportController::class)->group(function () {
                Route::get('/tickets', 'index')->name('index')->middleware('travel_agency.can:support.view');
                Route::get('/tickets/create', 'create')->name('create')->middleware('travel_agency.can:support.create');
                Route::post('/tickets', 'store')->name('store')->middleware('travel_agency.can:support.create');
                Route::get('/tickets/{ticketNumber}', 'show')->name('show')->middleware('travel_agency.can:support.view');
                Route::post('/tickets/{ticketNumber}/reply', 'reply')->name('reply')->middleware('travel_agency.can:support.create');
            });

            // Performance
            Route::prefix('performance')->name('performance.')->controller(PerformanceController::class)->group(function () {
                Route::get('/', 'index')->name('index')->middleware('travel_agency.can:reports.view');
                Route::get('/stats', 'stats')->name('stats')->middleware('travel_agency.can:reports.view');
            });

            // Roles & permissions
            Route::prefix('roles')->name('roles.')->controller(RoleController::class)->group(function () {
                Route::get('/', 'index')->name('index')->middleware('travel_agency.can:roles.view');
                Route::get('/permissions', 'permissions')->name('permissions')->middleware('travel_agency.can:roles.view');
                Route::get('/create', 'create')->name('create')->middleware('travel_agency.can:roles.create');
                Route::post('/', 'store')->name('store')->middleware('travel_agency.can:roles.create');
                Route::get('/{role}/edit', 'edit')->name('edit')->middleware('travel_agency.can:roles.edit');
                Route::put('/{role}', 'update')->name('update')->middleware('travel_agency.can:roles.edit');
                Route::delete('/{role}', 'destroy')->name('destroy')->middleware('travel_agency.can:roles.delete');
            });
        });
    });
