<?php

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
});
