<?php

use App\Http\Controllers\Delivery\AuthController;
use App\Http\Controllers\Delivery\CodSettlementsController;
use App\Http\Controllers\Delivery\AssignmentController;
use App\Http\Controllers\Delivery\DashboardController;
use App\Http\Controllers\Delivery\EarningsController;
use App\Http\Controllers\Delivery\LocationController;
use App\Http\Controllers\Delivery\ProfileController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// ── Locale switcher (delivery subdomain) ──────────────────────────────────
Route::domain('delivery.' . env('APP_DOMAIN', 'localhost'))
    ->middleware('web')
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
    })->name('delivery.locale.switch');

Route::domain('delivery.' . env('APP_DOMAIN', 'localhost'))
    ->name('delivery.')
    ->group(function () {

        Broadcast::routes(['middleware' => ['web', 'auth.delivery']]);

        // ── Guest routes ──────────────────────────────────────────────────────
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

        // ── Authenticated routes ───────────────────────────────────────────────
        Route::middleware(['auth.delivery'])->group(function () {

            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

            // ── Notifications ─────────────────────────────────────────────────
            Route::prefix('notifications')->name('notifications.')
                ->controller(NotificationController::class)
                ->group(function () {
                    Route::get('/recent',        'recent')->name('recent');
                    Route::get('/unread-count',  'unreadCount')->name('unread-count');
                    Route::post('/mark-all-read','markAllRead')->name('mark-all-read');
                    Route::post('/{id}/read',    'markRead')->name('mark-read');
                });

            // Dashboard
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

            // Assignments
            Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.index');
            Route::get('/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
            Route::post('/assignments/{assignment}/accept', [AssignmentController::class, 'accept'])->name('assignments.accept');
            Route::post('/assignments/{assignment}/picked-up', [AssignmentController::class, 'pickedUp'])->name('assignments.picked-up');
            Route::post('/assignments/{assignment}/deliver', [AssignmentController::class, 'deliver'])->name('assignments.deliver');
            Route::post('/assignments/{assignment}/fail', [AssignmentController::class, 'fail'])->name('assignments.fail');

            // Location
            Route::put('/location', [LocationController::class, 'update'])->name('location.update');
            Route::put('/availability', [LocationController::class, 'updateAvailability'])->name('availability.update');

            // Earnings
            Route::get('/earnings', [EarningsController::class, 'index'])->name('earnings.index');
            Route::get('/earnings/summary', [EarningsController::class, 'summary'])->name('earnings.summary');

            // Profile
            Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
            Route::post('/profile/password', [ProfileController::class, 'changePassword'])->name('profile.password');

            // Me
            Route::get('/me/zone', [ProfileController::class, 'zone'])->name('me.zone');

            // Wallet
            Route::get('/wallet', [\App\Http\Controllers\Delivery\WalletController::class, 'index'])->name('wallet.index');
            Route::post('/wallet/withdraw', [\App\Http\Controllers\Delivery\WalletController::class, 'requestWithdrawal'])->name('wallet.withdraw');

            // COD Settlements
            Route::get('/cod-settlements', [CodSettlementsController::class, 'index'])->name('cod-settlements.index');
        });
    });
