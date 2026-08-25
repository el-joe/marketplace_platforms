<?php

use App\Http\Controllers\Partner\Api\AuthController;
use App\Http\Controllers\Partner\Api\DashboardController;
use App\Http\Controllers\Partner\Api\OrderController;
use App\Http\Controllers\Partner\Api\ReturnController;
use App\Http\Controllers\Partner\Api\WarrantyClaimController;
use App\Http\Controllers\Partner\Api\ListingController;
use App\Http\Controllers\Partner\Api\InventoryController;
use App\Http\Controllers\Partner\Api\WarehouseController;
use App\Http\Controllers\Partner\Api\ClassifiedController;
use App\Http\Controllers\Partner\Api\PerformanceController;
use App\Http\Controllers\Partner\Api\FinanceController;
use App\Http\Controllers\Partner\Api\ProfileController;
use App\Http\Controllers\Partner\Api\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Partner App API — /api/partner/v1/...
| Guard : vendor_api (JWT — tymon/jwt-auth, VendorAdmin model)
| Scope : READ-ONLY — GET endpoints only
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void {

    // ── Auth (public) ─────────────────────────────────────────────────────
    Route::prefix('auth')->name('partner.api.auth.')->group(function (): void {
        Route::post('login',         [AuthController::class, 'login'])->name('login')
             ->middleware('throttle:10,1');
        Route::post('refresh-token', [AuthController::class, 'refresh'])->name('refresh');
    });

    // ── Authenticated + Active ────────────────────────────────────────────
    Route::middleware(['vendor.api.auth', 'vendor.api.active'])->group(function (): void {

        // Auth
        Route::prefix('auth')->name('partner.api.auth.')->group(function (): void {
            Route::post('logout',        [AuthController::class, 'logout'])->name('logout');
            Route::get('me',             [AuthController::class, 'me'])->name('me');
            Route::post('device-token',  [AuthController::class, 'registerDeviceToken'])->name('device-token.store');
            Route::delete('device-token',[AuthController::class, 'removeDeviceToken'])->name('device-token.destroy');
        });

        // Dashboard / Home
        Route::get('dashboard', [DashboardController::class, 'index'])->name('partner.api.dashboard');

        // Notifications
        Route::prefix('notifications')->name('partner.api.notifications.')->group(function (): void {
            Route::get('/',            [NotificationController::class, 'index'])->name('index');
            Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::put('read-all',     [NotificationController::class, 'markAllRead'])->name('read-all');
            Route::put('{id}/read',    [NotificationController::class, 'markRead'])->name('read');
        });

        // Orders (read-only)
        Route::prefix('orders')->name('partner.api.orders.')->group(function (): void {
            Route::get('/',                    [OrderController::class, 'index'])->name('index');
            Route::get('{subOrderNumber}',     [OrderController::class, 'show'])->name('show');
        });

        // Returns (read-only)
        Route::prefix('returns')->name('partner.api.returns.')->group(function (): void {
            Route::get('/',              [ReturnController::class, 'index'])->name('index');
            Route::get('{returnNumber}', [ReturnController::class, 'show'])->name('show');
        });

        // Warranty Claims (read-only)
        Route::prefix('warranty-claims')->name('partner.api.warranty.')->group(function (): void {
            Route::get('/',      [WarrantyClaimController::class, 'index'])->name('index');
            Route::get('{id}',   [WarrantyClaimController::class, 'show'])->name('show');
        });

        // Listings (read-only)
        Route::prefix('listings')->name('partner.api.listings.')->group(function (): void {
            Route::get('/',      [ListingController::class, 'index'])->name('index');
            Route::get('{id}',   [ListingController::class, 'show'])->name('show');
        });

        // Inventory (read-only)
        Route::prefix('inventory')->name('partner.api.inventory.')->group(function (): void {
            Route::get('/',                    [InventoryController::class, 'index'])->name('index');
            Route::get('{id}/movements',       [InventoryController::class, 'movements'])->name('movements');
            Route::prefix('transfers')->name('transfers.')->group(function (): void {
                Route::get('/',                [InventoryController::class, 'transferIndex'])->name('index');
                Route::get('{transferNumber}', [InventoryController::class, 'transferShow'])->name('show');
            });
        });

        // Warehouses (read-only)
        Route::prefix('warehouses')->name('partner.api.warehouses.')->group(function (): void {
            Route::get('/', [WarehouseController::class, 'index'])->name('index');
        });

        // My Classifieds (read-only)
        Route::prefix('classifieds')->name('partner.api.classifieds.')->group(function (): void {
            Route::get('/',              [ClassifiedController::class, 'index'])->name('index');
            Route::get('{id}',           [ClassifiedController::class, 'show'])->name('show');
            Route::get('{id}/inquiries', [ClassifiedController::class, 'inquiries'])->name('inquiries');
        });

        // Performance (read-only)
        Route::prefix('performance')->name('partner.api.performance.')->group(function (): void {
            Route::get('/',       [PerformanceController::class, 'index'])->name('index');
            Route::get('reviews', [PerformanceController::class, 'reviews'])->name('reviews');
        });

        // Finance (read-only)
        Route::prefix('finance')->name('partner.api.finance.')->group(function (): void {
            Route::get('summary',           [FinanceController::class, 'summary'])->name('summary');
            Route::get('transactions',      [FinanceController::class, 'transactions'])->name('transactions');
            Route::get('ledger',            [FinanceController::class, 'ledger'])->name('ledger');
            Route::get('commission-rates',  [FinanceController::class, 'commissionRates'])->name('commission-rates');
            Route::get('sales-report',      [FinanceController::class, 'salesReport'])->name('sales-report');
            Route::prefix('payouts')->name('payouts.')->group(function (): void {
                Route::get('/',       [FinanceController::class, 'payouts'])->name('index');
                Route::get('{id}',    [FinanceController::class, 'showPayout'])->name('show');
            });
            Route::get('bank-accounts', [FinanceController::class, 'bankAccounts'])->name('bank-accounts');
        });

        // Profile + Documents (read-only)
        Route::prefix('profile')->name('partner.api.profile.')->group(function (): void {
            Route::get('/',         [ProfileController::class, 'show'])->name('show');
            Route::get('documents', [ProfileController::class, 'documents'])->name('documents');
        });
    });
});
