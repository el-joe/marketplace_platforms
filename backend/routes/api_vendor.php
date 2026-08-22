<?php

use App\Http\Controllers\Vendor\AdCampaignController;
use App\Http\Controllers\Vendor\ClassifiedListingController;
use App\Http\Controllers\Vendor\AuthController;
use App\Http\Controllers\Vendor\CouponController;
use App\Http\Controllers\Vendor\DashboardController;
use App\Http\Controllers\Vendor\DisputeController;
use App\Http\Controllers\Vendor\FinanceController;
use App\Http\Controllers\Vendor\FlashSaleController;
use App\Http\Controllers\Vendor\InventoryController;
use App\Http\Controllers\Vendor\ListingController;
use App\Http\Controllers\Vendor\NotificationController;
use App\Http\Controllers\Vendor\OnboardingController;
use App\Http\Controllers\Vendor\OrderController;
use App\Http\Controllers\Vendor\PerformanceController;
use App\Http\Controllers\Vendor\ProfileController;
use App\Http\Controllers\Vendor\ReturnController;
use App\Http\Controllers\Vendor\SupportTicketController;
use App\Http\Controllers\Vendor\TeamController;
use App\Http\Controllers\Vendor\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vendor API Routes — /api/vendor/v1/...
| Guard: vendor (JWT via tymon/jwt-auth)
| Authenticated middleware stack: vendor.auth → vendor.active
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function (): void { 

    // ── Auth (public) ─────────────────────────────────────────────────────────
    Route::prefix('auth')->name('vendor.auth.')->group(function (): void {
        Route::post('login',          [AuthController::class, 'login'])->name('login');
        Route::post('forgot-password',[AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });

    // ── Authenticated ─────────────────────────────────────────────────────────
    Route::middleware(['vendor.auth', 'vendor.active'])->group(function (): void {

        Route::prefix('auth')->name('vendor.auth.')->group(function (): void {
            Route::post('logout',        [AuthController::class, 'logout'])->name('logout');
            Route::post('refresh-token', [AuthController::class, 'refresh'])->name('refresh');
            Route::get('me',             [AuthController::class, 'me'])->name('me');
            Route::post('device-token',  [AuthController::class, 'registerDeviceToken'])->name('device-token.store');
            Route::delete('device-token', [AuthController::class, 'removeDeviceToken'])->name('device-token.destroy');
        });

        // ── Onboarding wizard (accessible before onboarding is complete) ──────
        Route::prefix('onboarding')->name('vendor.onboarding.')->group(function (): void {
            Route::get('status',            [OnboardingController::class, 'status'])->name('status');
            Route::put('store-identity',    [OnboardingController::class, 'storeIdentity'])->name('store-identity');
            Route::put('business-details',  [OnboardingController::class, 'businessDetails'])->name('business-details');
            Route::post('documents',        [OnboardingController::class, 'uploadDocument'])->name('documents');
            Route::post('bank-account',     [OnboardingController::class, 'bankAccount'])->name('bank-account');
            Route::put('shipping-settings', [OnboardingController::class, 'shippingSettings'])->name('shipping-settings');
            Route::post('complete',         [OnboardingController::class, 'complete'])->name('complete');
        });

        // ── Onboarded-only routes ─────────────────────────────────────────────
        Route::middleware('vendor.onboarded')->group(function (): void {

            // Profile
            Route::prefix('profile')->name('vendor.profile.')->group(function (): void {
                Route::get('/',                          [ProfileController::class, 'show'])->name('show');
                Route::put('/',                          [ProfileController::class, 'update'])->name('update');
                Route::put('password',                   [ProfileController::class, 'updatePassword'])->name('password');
                Route::get('documents',                  [ProfileController::class, 'documents'])->name('documents');
                Route::post('documents/{type}/reupload', [ProfileController::class, 'reuploadDocument'])->name('documents.reupload');
            });

            // Team
            Route::prefix('team')->name('vendor.team.')->group(function (): void {
                Route::get('/',                  [TeamController::class, 'index'])->name('index');
                Route::post('/',                 [TeamController::class, 'store'])->name('store');
                Route::put('{id}/toggle-active', [TeamController::class, 'toggleActive'])->name('toggle-active');
                Route::delete('{id}',            [TeamController::class, 'destroy'])->name('destroy');
            });

            // Dashboard
            Route::get('dashboard', DashboardController::class)->name('vendor.dashboard');

            // Notifications
            Route::prefix('notifications')->name('vendor.notifications.')->group(function (): void {
                Route::get('/',              [NotificationController::class, 'index'])->name('index');
                Route::put('read-all',       [NotificationController::class, 'markAllRead'])->name('read-all');
                // Must be registered before {id}/read to avoid 'unread-count' matching as {id}.
                Route::get('unread-count',   [NotificationController::class, 'unreadCount'])->name('unread-count');
                Route::put('{id}/read',      [NotificationController::class, 'markRead'])->name('read');
            });

            // Orders & fulfillment
            Route::prefix('orders')->name('vendor.orders.')->group(function (): void {
                Route::get('/',                                [OrderController::class, 'index'])->name('index');
                Route::get('{subOrderNumber}',                 [OrderController::class, 'show'])->name('show');
                Route::post('{subOrderNumber}/ship',           [OrderController::class, 'ship'])->name('ship');
                Route::post('{subOrderNumber}/cancel',         [OrderController::class, 'cancel'])->name('cancel');
                Route::get('{subOrderNumber}/packing-slip',   [OrderController::class, 'packingSlip'])->name('packing-slip');
            });

            // Disputes (vendor side)
            Route::prefix('disputes')->name('vendor.disputes.')->group(function (): void {
                Route::get('/',                                  [DisputeController::class, 'index'])->name('index');
                Route::get('{disputeNumber}',                    [DisputeController::class, 'show'])->name('show');
                Route::post('{disputeNumber}/messages',          [DisputeController::class, 'addMessage'])->name('messages.store');
                Route::post('{disputeNumber}/evidence',          [DisputeController::class, 'addEvidence'])->name('evidence.store');
            });

            // Returns (vendor side — read-only + comment channel, no approve/reject authority)
            Route::prefix('returns')->name('vendor.returns.')->group(function (): void {
                Route::get('/',                              [ReturnController::class, 'index'])->name('index');
                Route::get('{returnNumber}',                 [ReturnController::class, 'show'])->name('show');
                Route::post('{returnNumber}/messages',       [ReturnController::class, 'addMessage'])->name('messages.store');
            });

            // Listings
            Route::prefix('listings')->name('vendor.listings.')->group(function (): void {
                Route::get('/',            [ListingController::class, 'index'])->name('index');
                Route::get('{id}',         [ListingController::class, 'show'])->name('show');
                Route::post('/',           [ListingController::class, 'store'])->name('store');
                Route::put('{id}/price',   [ListingController::class, 'updatePrice'])->name('price');
                Route::put('{id}/status',  [ListingController::class, 'updateStatus'])->name('status');
                Route::get('{id}/shipping-methods', [ListingController::class, 'availableShippingMethods'])->name('shipping-methods');
                Route::put('{id}/shipping', [ListingController::class, 'updateShipping'])->name('shipping');
                Route::put('{id}/delivery-coverage', [ListingController::class, 'updateDeliveryCoverage'])->name('delivery-coverage');
                Route::delete('{id}',      [ListingController::class, 'destroy'])->name('destroy');
            });

            // Coupons (vendor/product scope, vendor-owned only)
            Route::prefix('coupons')->name('vendor.coupons.')->group(function (): void {
                Route::get('/',                 [CouponController::class, 'index'])->name('index');
                Route::post('/',                [CouponController::class, 'store'])->name('store');
                Route::get('{id}',              [CouponController::class, 'show'])->name('show');
                Route::put('{id}',              [CouponController::class, 'update'])->name('update');
                Route::put('{id}/toggle-active', [CouponController::class, 'toggleActive'])->name('toggle-active');
                Route::delete('{id}',           [CouponController::class, 'destroy'])->name('destroy');
                Route::get('{id}/usage',        [CouponController::class, 'usages'])->name('usage');
            });

            // Warehouses (vendor-owned only)
            Route::prefix('warehouses')->name('vendor.warehouses.')->group(function (): void {
                Route::get('/',     [WarehouseController::class, 'index'])->name('index');
                Route::post('/',    [WarehouseController::class, 'store'])->name('store');
                Route::put('{id}',  [WarehouseController::class, 'update'])->name('update');
                Route::delete('{id}', [WarehouseController::class, 'destroy'])->name('destroy');
            });

            // Inventory & transfers
            Route::prefix('inventory')->name('vendor.inventory.')->group(function (): void {
                Route::get('/',                                          [InventoryController::class, 'index'])->name('index');
                Route::post('{id}/adjust',                               [InventoryController::class, 'adjust'])->name('adjust');
                Route::get('{id}/movements',                             [InventoryController::class, 'movements'])->name('movements');

                Route::prefix('transfers')->name('transfers.')->group(function (): void {
                    Route::get('/',                                      [InventoryController::class, 'transferIndex'])->name('index');
                    Route::post('/',                                     [InventoryController::class, 'transferStore'])->name('store');
                    Route::get('{transferNumber}',                       [InventoryController::class, 'transferShow'])->name('show');
                    Route::post('{transferNumber}/ship',                 [InventoryController::class, 'transferShip'])->name('ship');
                    Route::post('{transferNumber}/cancel',               [InventoryController::class, 'transferCancel'])->name('cancel');
                });
            });

            // Finance & payouts (read-mostly — no writes to ledger_entries or commissions)
            Route::prefix('finance')->name('vendor.finance.')->group(function (): void {
                Route::get('summary',          [FinanceController::class, 'summary'])->name('summary');
                Route::get('transactions',     [FinanceController::class, 'transactions'])->name('transactions');
                Route::get('ledger',           [FinanceController::class, 'ledger'])->name('ledger');
                Route::get('commission-rates', [FinanceController::class, 'commissionRates'])->name('commission-rates');
                Route::get('sales-report',     [FinanceController::class, 'salesReport'])->name('sales-report');

                Route::prefix('payouts')->name('payouts.')->group(function (): void {
                    Route::get('/',              [FinanceController::class, 'payouts'])->name('index');
                    Route::get('{id}',           [FinanceController::class, 'showPayout'])->name('show');
                    Route::get('{id}/invoice',   [FinanceController::class, 'payoutInvoice'])->name('invoice');
                });

                Route::prefix('bank-accounts')->name('bank-accounts.')->group(function (): void {
                    Route::get('/',              [FinanceController::class, 'bankAccounts'])->name('index');
                    Route::post('/',             [FinanceController::class, 'storeBankAccount'])->name('store');
                    Route::put('{id}/set-primary', [FinanceController::class, 'setPrimaryBankAccount'])->name('set-primary');
                    Route::delete('{id}',        [FinanceController::class, 'deleteBankAccount'])->name('destroy');
                });
            });

            // Flash sale participation (vendor-facing)
            Route::prefix('flash-sales')->name('vendor.flash-sales.')->group(function (): void {
                Route::get('/',    [FlashSaleController::class, 'index'])->name('index');
                Route::get('{id}', [FlashSaleController::class, 'show'])->name('show');

                Route::prefix('{id}/submissions')->name('submissions.')->group(function (): void {
                    Route::post('/',                         [FlashSaleController::class, 'storeSubmission'])->name('store');
                    Route::put('{submission_id}',            [FlashSaleController::class, 'updateSubmission'])->name('update');
                    Route::delete('{submission_id}',         [FlashSaleController::class, 'destroySubmission'])->name('destroy');
                });
            });

            // Performance & reviews (read-only)
            Route::prefix('performance')->name('vendor.performance.')->group(function (): void {
                Route::get('/',       [PerformanceController::class, 'index'])->name('index');
                Route::get('reviews', [PerformanceController::class, 'reviews'])->name('reviews');
            });

            // Ads / self-serve advertising
            Route::prefix('ads/campaigns')->name('vendor.ads.')->group(function (): void {
                Route::get('/',                          [AdCampaignController::class, 'index'])->name('index');
                Route::post('/',                         [AdCampaignController::class, 'store'])->name('store');
                Route::get('{id}',                       [AdCampaignController::class, 'show'])->name('show');
                Route::put('{id}',                       [AdCampaignController::class, 'update'])->name('update');
                Route::delete('{id}',                    [AdCampaignController::class, 'destroy'])->name('destroy');
                Route::put('{id}/pause',                 [AdCampaignController::class, 'pause'])->name('pause');
                Route::put('{id}/resume',                [AdCampaignController::class, 'resume'])->name('resume');
                Route::get('{id}/performance',           [AdCampaignController::class, 'performance'])->name('performance');
                Route::get('{id}/quality-score',         [AdCampaignController::class, 'qualityScore'])->name('quality-score');
            });

            // Classified listings (vendor-owned)
            Route::prefix('classifieds')->name('vendor.classifieds.')->group(function (): void {
                Route::get('categories',                        [ClassifiedListingController::class, 'categories'])->name('categories');
                Route::get('/',                                 [ClassifiedListingController::class, 'index'])->name('index');
                Route::post('/',                                [ClassifiedListingController::class, 'store'])->name('store');
                Route::get('{id}',                              [ClassifiedListingController::class, 'show'])->name('show');
                Route::put('{id}',                              [ClassifiedListingController::class, 'update'])->name('update');
                Route::delete('{id}',                           [ClassifiedListingController::class, 'destroy'])->name('destroy');
                Route::get('{id}/contract',                     [ClassifiedListingController::class, 'showContract'])->name('contract.show');
                Route::post('{id}/contract/accept',             [ClassifiedListingController::class, 'acceptContract'])->name('contract.accept');
                Route::put('{id}/pause',                        [ClassifiedListingController::class, 'pause'])->name('pause');
                Route::put('{id}/resume',                       [ClassifiedListingController::class, 'resume'])->name('resume');
                Route::put('{id}/mark-sold',                    [ClassifiedListingController::class, 'markSold'])->name('mark-sold');
                Route::get('{id}/inquiries',                    [ClassifiedListingController::class, 'inquiries'])->name('inquiries.index');
                Route::put('inquiries/{inquiryId}/status',      [ClassifiedListingController::class, 'updateInquiryStatus'])->name('inquiries.status');
            });

            // Support tickets (vendor-facing)
            Route::prefix('support/tickets')->name('vendor.support.tickets.')->group(function (): void {
                Route::get('/',                              [SupportTicketController::class, 'index'])->name('index');
                Route::post('/',                             [SupportTicketController::class, 'store'])->name('store');
                Route::get('/{ticketNumber}',                [SupportTicketController::class, 'show'])->name('show');
                Route::post('/{ticketNumber}/messages',      [SupportTicketController::class, 'addMessage'])->name('messages.store');
                Route::put('/{ticketNumber}/rate',           [SupportTicketController::class, 'rate'])->name('rate');
            });
        });
    });
});
