<?php

use App\Http\Controllers\Marketer\AuthController;
use App\Http\Controllers\Marketer\CampaignController;
use App\Http\Controllers\Marketer\DashboardController;
use App\Http\Controllers\Marketer\InvitationController;
use App\Http\Controllers\Marketer\ProfileController;
use App\Http\Controllers\Marketer\ReportController;
use App\Http\Controllers\Marketer\SampleController;
use Illuminate\Support\Facades\Route;

// ── Locale switcher ───────────────────────────────────────────────────────
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
    })->name('locale.switch');

Route::middleware('web')->group(function () {

    // ── Guest routes ──────────────────────────────────────────────────────
    Route::middleware('guest:marketer')->group(function () {
        Route::get('/login',                [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login',               [AuthController::class, 'login'])->name('login.post');

        Route::get('/register',             [AuthController::class, 'showRegister'])->name('register');
        Route::post('/register',            [AuthController::class, 'register'])->name('register.post');

        Route::get('/forgot-password',      [AuthController::class, 'forgotPassword'])->name('auth.forgot');
        Route::post('/forgot-password',     [AuthController::class, 'sendResetLink'])->name('auth.forgot.send');
        Route::get('/reset-password/{token}', [AuthController::class, 'resetPassword'])->name('auth.reset');
        Route::post('/reset-password',      [AuthController::class, 'updatePassword'])->name('auth.reset.update');
    });

    // ── Authenticated routes ───────────────────────────────────────────────
    Route::middleware('auth.marketer')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Dashboard / statistics
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Profile
        Route::get('/profile',  [ProfileController::class, 'show'])->name('profile');
        Route::put('/profile',  [ProfileController::class, 'update'])->name('profile.update');

        // Campaign invitations
        Route::prefix('invitations')->name('invitations.')->group(function () {
            Route::get('/',                        [InvitationController::class, 'index'])->name('index');
            Route::post('/{invitation}/accept',    [InvitationController::class, 'accept'])->name('accept');
            Route::post('/{invitation}/reject',    [InvitationController::class, 'reject'])->name('reject');
        });

        // Active campaigns (accepted invitations)
        Route::get('/campaigns/active',   [CampaignController::class, 'active'])->name('campaigns.active');

        // Finished campaigns
        Route::get('/campaigns/finished', [CampaignController::class, 'finished'])->name('campaigns.finished');

        // Samples
        Route::get('/samples',            [SampleController::class, 'index'])->name('samples.index');
        Route::post('/samples/{sample}/address', [SampleController::class, 'submitAddress'])->name('samples.address');

        // Reports
        Route::get('/reports',            [ReportController::class, 'index'])->name('reports.index');
    });
});
