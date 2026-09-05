<?php

use App\Http\Controllers\Partner\AuthController as PartnerAuthController;
use App\Http\Controllers\Partner\BankAccountController;
use App\Http\Controllers\Partner\CouponController;
use App\Http\Controllers\Partner\DashboardController;
use App\Http\Controllers\Partner\InventoryController;
use App\Http\Controllers\Partner\ListingController;
use App\Http\Controllers\Partner\OrderController;
use App\Http\Controllers\Partner\FlashSaleController;
use App\Http\Controllers\Partner\PayoutController;
use App\Http\Controllers\Partner\PerformanceController;
use App\Http\Controllers\Partner\ProductCertificationController;
use App\Http\Controllers\Partner\ProfileController;
use App\Http\Controllers\Partner\DisputeController;
use App\Http\Controllers\Partner\SupportController;
use App\Http\Controllers\Partner\SubscriptionController as PartnerSubscriptionController;
use App\Http\Controllers\Partner\FulfillmentController;
use App\Http\Controllers\Partner\RoleController;
use App\Http\Controllers\Partner\TeamController;
use App\Http\Controllers\Partner\VendorChangeRequestController;
use App\Http\Controllers\Partner\WarehouseController;
use App\Http\Controllers\Partner\AdsController;
use App\Http\Controllers\Partner\ClassifiedListingController;
use App\Http\Controllers\Partner\MarketerCampaignController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Partner Panel Routes — partner.noon.loc
| Authenticated vendor area
|--------------------------------------------------------------------------
*/

// ── Suspended page (accessible without full auth) ────────────────────────
Route::get('/suspended', fn() => view('partner.suspended'))->name('suspended');

Route::redirect('/', '/dashboard')->name('home');

// ── Auth (guest only) ────────────────────────────────────────────────────
Route::middleware('guest:vendor')->group(function () {
    Route::get('/login', [PartnerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [PartnerAuthController::class, 'login'])->name('login.submit');

    Route::get('/forgot-password', [PartnerAuthController::class, 'forgotPassword'])->name('auth.forgot');
    Route::post('/forgot-password', [PartnerAuthController::class, 'sendResetLink'])->name('auth.forgot.send');

    Route::get('/reset-password/{token}', [PartnerAuthController::class, 'resetPassword'])->name('auth.reset');
    Route::post('/reset-password', [PartnerAuthController::class, 'updatePassword'])->name('auth.reset.update');
});

Route::post('/logout', [PartnerAuthController::class, 'logout'])
    ->middleware('vendor.auth')
    ->name('logout');

// ── Locale switch ─────────────────────────────────────────────────────────
Route::post('/locale/switch', function (\Illuminate\Http\Request $request) {
    $locale = $request->input('locale');
    abort_unless(in_array($locale, config('app.available_locales', ['ar', 'en'])), 422);
    session([
        'locale'          => $locale,
        'locale_override' => $locale,   // prevents SetVendorLocale from overriding
        'dir'             => $locale === 'ar' ? 'rtl' : 'ltr',
    ]);
    \Carbon\Carbon::setLocale($locale);
    \Illuminate\Support\Facades\App::setLocale($locale);
    return back();
})->name('locale.switch')->middleware('web');

// ── Broadcasting auth (Reverb channel authorization for vendor guard) ────────────
Broadcast::routes(['middleware' => ['web', 'vendor.auth']]);

// ── Protected panel ──────────────────────────────────────────────────────
Route::middleware(['vendor.auth', 'vendor.active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Notifications ───────────────────────────────────────────────────────────
    Route::prefix('notifications')->name('notifications.')
        ->controller(NotificationController::class)
        ->group(function () {
            Route::get('/',              'index')->name('index');
            Route::get('/recent',        'recent')->name('recent');
            Route::get('/unread-count',  'unreadCount')->name('unread-count');
            Route::get('/unread',        'unread')->name('unread');
            Route::post('/mark-all-read','markAllRead')->name('mark-all-read');
            Route::post('/{id}/read',    'markRead')->name('mark-read');
        });

    // ── Orders module ────────────────────────────────────────────────────────
    Route::prefix('orders')->name('orders.')->controller(OrderController::class)->group(function () {
        Route::get('/', 'index')->name('index')->middleware('vendor.can:orders.view');
        Route::get('/datatable', 'datatable')->name('datatable')->middleware('vendor.can:orders.view');
        Route::get('/{subOrderNumber}', 'show')->name('show')->middleware('vendor.can:orders.view');
        Route::post('/{subOrderNumber}/confirm', 'confirm')->name('confirm')->middleware('vendor.can:orders.process');
        Route::post('/{subOrderNumber}/ship', 'ship')->name('ship')->middleware('vendor.can:orders.process');
        Route::get('/{subOrderNumber}/ship-preview', 'shipPreview')->name('ship-preview')->middleware('vendor.can:orders.process');
        Route::post('/{subOrderNumber}/out-for-delivery', 'markOutForDelivery')->name('out-for-delivery')->middleware('vendor.can:orders.process');
        Route::post('/{subOrderNumber}/deliver', 'markDelivered')->name('deliver')->middleware('vendor.can:orders.process');
        Route::post('/{subOrderNumber}/cancel', 'cancel')->name('cancel')->middleware('vendor.can:orders.cancel');
    });

    // ── Listings module ──────────────────────────────────────────────────────
    Route::prefix('listings')->name('listings.')->controller(ListingController::class)->group(function () {
        Route::get('/', 'index')->name('index')->middleware('vendor.can:listings.view');
        Route::get('/datatable', 'datatable')->name('datatable')->middleware('vendor.can:listings.view');
        Route::get('/create', 'create')->name('create')->middleware('vendor.can:listings.create');
        Route::post('/', 'store')->name('store')->middleware('vendor.can:listings.create');
        Route::get('/product-search', 'productSearch')->name('product-search')->middleware('vendor.can:listings.view');
        Route::get('/products/{product}/variants/{variant}/slug-preview', 'slugPreview')->name('slug-preview')->middleware('vendor.can:listings.view');
        Route::get('/variants/{variant}/url-info', 'variantUrlInfo')->name('variants.url-info')->middleware('vendor.can:listings.view');
        Route::get('/warehouses-by-country', 'warehousesByCountry')->name('warehouses-by-country')->middleware('vendor.can:listings.view');
        Route::get('/available-shipping-methods', 'availableShippingMethods')->name('available-shipping-methods')->middleware('vendor.can:listings.view');
        Route::get('/search-marketers', 'searchMarketers')->name('search-marketers')->middleware('vendor.can:listings.view');
        Route::get('/category-samples', 'categorySamples')->name('category-samples')->middleware('vendor.can:listings.view');
        Route::get('/campaign-pricing', 'campaignPricing')->name('campaign-pricing')->middleware('vendor.can:listings.view');
        Route::get('/marketer-fee', 'getInfluencerFee')->name('marketer-fee')->middleware('vendor.can:listings.view');
        Route::get('/{listing}/edit', 'edit')->name('edit')->middleware('vendor.can:listings.edit');
        Route::put('/{listing}', 'update')->name('update')->middleware('vendor.can:listings.edit');
        Route::post('/{listing}/resubmit', 'resubmit')->name('resubmit')->middleware('vendor.can:listings.create');
        Route::get('/{listing}', 'show')->name('show')->middleware('vendor.can:listings.view');
        Route::post('/{listing}/update-price', 'updatePrice')->name('update-price')->middleware('vendor.can:listings.pricing.edit');
        Route::post('/{listing}/update-shipping', 'updateShipping')->name('update-shipping')->middleware('vendor.can:listings.edit');
        Route::post('/{listing}/toggle-status', 'toggleStatus')->name('toggle-status')->middleware('vendor.can:listings.publish');
        Route::post('/{listing}/adjust-stock', 'adjustStock')->name('adjust-stock')->middleware('vendor.can:listings.stock.edit');
        Route::post('/{listing}/toggle-covers-delivery', 'toggleCoversDelivery')->name('toggle-covers-delivery')->middleware('vendor.can:listings.edit');
        Route::post('/{listing}/update-dimensions', 'updateDimensions')->name('update-dimensions')->middleware('vendor.can:listings.edit');
        Route::get('/{listing}/shipping-preview', 'shippingPreview')->name('shipping-preview')->middleware('vendor.can:listings.view');
        Route::post('/{listing}/clear-cache', 'clearCache')
            ->name('clear-cache')
            ->middleware('vendor.can:listings.view');
    });

    // ── Inventory module ─────────────────────────────────────────────────────
    Route::prefix('inventory')->name('inventory.')->controller(InventoryController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/datatable', 'datatable')->name('datatable');
        Route::get('/low-stock', 'lowStock')->name('low-stock');
        Route::get('/out-of-stock', 'outOfStock')->name('out-of-stock');
        Route::get('/{listing}/movements', 'movements')->name('movements');
    });

    // ── Payouts module ───────────────────────────────────────────────────────
    Route::prefix('payouts')->name('payouts.')->controller(PayoutController::class)->group(function () {
        Route::get('/', 'index')->name('index')->middleware('vendor.can:finance.payouts.view');
        Route::get('/earnings-summary', 'earningsSummary')->name('earnings-summary')->middleware('vendor.can:finance.payouts.view');
        Route::get('/{payoutNumber}', 'show')->name('show')->middleware('vendor.can:finance.payouts.view');
    });

    // ── Coupons module ────────────────────────────────────────────────────────
    Route::prefix('coupons')->name('coupons.')->controller(CouponController::class)->group(function () {
        Route::get('/',                    'index')->name('index');
        Route::get('/datatable',           'datatable')->name('datatable');
        Route::get('/create',              'create')->name('create');
        Route::get('/product-search',      'productSearch')->name('product-search');
        Route::post('/',                   'store')->name('store');
        Route::get('/{id}/analytics',      'analytics')->name('analytics');
        Route::get('/{id}',                'show')->name('show');
        Route::get('/{id}/edit',           'edit')->name('edit');
        Route::put('/{id}',                'update')->name('update');
        Route::post('/{id}/toggle-status', 'toggleStatus')->name('toggle-status');
        Route::delete('/{id}',             'destroy')->name('destroy');
    });

    // ── Product Certifications ───────────────────────────────────────────────
    Route::prefix('product-certifications')->name('product-certifications.')->controller(ProductCertificationController::class)->group(function () {
        Route::get('/',                'index')->name('index');
        Route::post('/',               'store')->name('store');
        Route::post('/{id}/replace',   'replace')->name('replace');
    });

    // ── Bank Accounts module ─────────────────────────────────────────────────
    Route::prefix('bank-accounts')->name('bank-accounts.')->controller(BankAccountController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/{account}/set-primary', 'setPrimary')->name('set-primary');
        Route::delete('/{account}', 'destroy')->name('destroy');
    });
    // ── Flash Sales module ────────────────────────────────────────────────────
    Route::prefix('flash-sales')->name('flash-sales.')->controller(FlashSaleController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        // Static sub-routes BEFORE /{flashSaleId} wildcard
        Route::get('/{flashSaleId}/eligible-listings', 'eligibleListings')->name('eligible-listings');
        Route::post('/{flashSaleId}/submit', 'submit')->name('submit');
        Route::get('/{flashSaleId}/live-stats', 'liveStats')->name('live-stats');
        Route::get('/{flashSaleId}', 'show')->name('show');
    });

    // ── Performance module ───────────────────────────────────────────────────
    Route::prefix('performance')->name('performance.')->controller(PerformanceController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/stats', 'stats')->name('stats');
    });

    // ── City shipping surcharges ──────────────────────────────────────────────
    Route::prefix('city-surcharges')->name('city-surcharges.')
        ->controller(\App\Http\Controllers\Partner\CityShippingSurchargeController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::put('/{surcharge}', 'update')->name('update');
            Route::post('/{surcharge}/toggle-active', 'toggleActive')->name('toggle-active');
        });

    // ── Exceptional shipping zone alerts ─────────────────────────────────────
    Route::prefix('exceptional-zone-alerts')->name('exceptional-zone-alerts.')
        ->controller(\App\Http\Controllers\Partner\ExceptionalZoneAlertController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/cities-for-warehouse', 'citiesForWarehouse')->name('cities-for-warehouse');
            Route::post('/', 'store')->name('store');
            Route::post('/{alert}/cancel', 'cancel')->name('cancel');
        });

    // ── Profile & store settings ─────────────────────────────────────────────
    Route::prefix('profile')->name('profile.')->controller(ProfileController::class)->group(function () {
        Route::get('/', 'index')->name('index')->middleware('vendor.can:settings.view');
        Route::post('/update-store', 'updateStore')->name('update-store')->middleware('vendor.can:settings.edit');
        Route::post('/update-password', 'updatePassword')->name('update-password')->middleware('vendor.can:settings.edit');
    });

    // ── Documents ────────────────────────────────────────────────────────────
    Route::prefix('documents')->name('documents.')->controller(ProfileController::class)->group(function () {
        Route::get('/', 'documentsIndex')->name('index')->middleware('vendor.can:settings.view');
        Route::post('/upload', 'uploadDocument')->name('upload')->middleware('vendor.can:documents.upload');
    });

    // ── Change requests (locked-section edits) ─────────────────────────────────
    Route::prefix('change-requests')->name('change-requests.')->controller(VendorChangeRequestController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/{changeRequest}/cancel', 'cancel')->name('cancel');
    });

    // ── Team management ──────────────────────────────────────────────────────
    Route::prefix('team')->name('team.')->controller(TeamController::class)->group(function () {
        Route::get('/', 'index')->name('index')->middleware('vendor.can:team.view');
        Route::post('/', 'store')->name('store')->middleware('vendor.can:team.invite');
        Route::put('/{member}/role', 'updateRole')->name('update-role')->middleware('vendor.can:team.manage');
        Route::post('/{member}/toggle-active', 'toggleActive')->name('toggle-active')->middleware('vendor.can:team.manage');
        Route::delete('/{member}', 'destroy')->name('destroy')->middleware('vendor.can:team.manage');
    });

    // ── Roles & permissions ──────────────────────────────────────────────────
    Route::prefix('roles')->name('roles.')->controller(RoleController::class)->group(function () {
        Route::get('/', 'index')->name('index')->middleware('vendor.can:roles.view');
        Route::get('/permissions', 'permissions')->name('permissions')->middleware('vendor.can:roles.view');
        Route::get('/create', 'create')->name('create')->middleware('vendor.can:roles.create');
        Route::post('/', 'store')->name('store')->middleware('vendor.can:roles.create');
        Route::get('/{role}/edit', 'edit')->name('edit')->middleware('vendor.can:roles.edit');
        Route::put('/{role}', 'update')->name('update')->middleware('vendor.can:roles.edit');
        Route::delete('/{role}', 'destroy')->name('destroy')->middleware('vendor.can:roles.delete');
    });

    // ── Support tickets ──────────────────────────────────────────────────────
    Route::prefix('support/tickets')->name('support.tickets.')->controller(SupportController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{ticketNumber}', 'show')->name('show');
        Route::post('/{ticketNumber}/reply', 'reply')->name('reply');
    });

    // ── Disputes ─────────────────────────────────────────────────────────────
    Route::prefix('disputes')->name('disputes.')->controller(DisputeController::class)->group(function () {
        Route::get('/', 'index')->name('index')->middleware('vendor.can:disputes.view');
        Route::get('/{disputeNumber}', 'show')->name('show')->middleware('vendor.can:disputes.view');
        Route::post('/{disputeNumber}/reply', 'reply')->name('reply')->middleware('vendor.can:disputes.respond');
        Route::post('/{disputeNumber}/evidence', 'uploadEvidence')->name('evidence')->middleware('vendor.can:disputes.respond');
    });

    // ── Fulfillment (FBN / FBP / Marketplace) ───────────────────────────────
    Route::prefix('fulfillment')->name('fulfillment.')->controller(FulfillmentController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/fbn-requests', 'fbnRequests')->name('fbn.requests');
        Route::post('/fbn/submit', 'submitInboundRequest')->name('fbn.submit');
        Route::post('/fbn/{request}/cancel', 'cancelInboundRequest')->name('fbn.cancel');
        Route::patch('/fbn/{request}/tracking', 'updateInboundTracking')->name('fbn.tracking');
        Route::get('/fbp-inventory', 'fbpInventory')->name('fbp.inventory');
        Route::get('/storage-fees', 'storageFees')->name('storage-fees');
    });

    // ── Subscription ──────────────────────────────────────────────────────────
    Route::prefix('subscription')->name('subscription.')->controller(PartnerSubscriptionController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/subscribe', 'subscribe')->name('subscribe');
        Route::post('/cancel', 'cancel')->name('cancel');
    });

    // ── Wallet ────────────────────────────────────────────────────────────────
    Route::prefix('wallet')->name('wallet.')->controller(\App\Http\Controllers\Partner\WalletController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/withdraw', 'requestWithdrawal')->name('withdraw');
    });

    // ── Tools ─────────────────────────────────────────────────────────────
    Route::prefix('tools')->name('tools.')->controller(\App\Http\Controllers\Partner\ToolsController::class)->group(function () {
        Route::get('/weight-calculator', 'weightCalculator')->name('weight-calculator');
        Route::post('/weight-calculator/calculate', 'calculate')->name('weight-calculator.calculate');
    });

    // ── AI Tools ──────────────────────────────────────────────────────────
    Route::prefix('ai')->name('ai.')->controller(\App\Http\Controllers\Partner\AiToolsController::class)->group(function () {
        Route::get('/credits', 'credits')->name('credits');
        Route::get('/{listing}', 'index')->name('index');

        // Image enhancement
        Route::post('/enhance/{listing}/{image}', 'enhanceImage')->name('enhance');
        Route::get('/enhance/status/{jobId}', 'checkEnhancementStatus')->name('enhance.status');
        Route::post('/enhance/apply/{jobId}', 'applyEnhancement')->name('enhance.apply');

        // Video generation
        Route::post('/video/generate', 'generateVideo')->name('video.generate');
        Route::get('/video/status/{jobId}', 'checkVideoStatus')->name('video.status');
    });

    // ─── Carrier Claims ───────────────────────────────────────────────────────
    Route::prefix('claims')->name('claims.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Partner\ClaimController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Partner\ClaimController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Partner\ClaimController::class, 'store'])->name('store');
        Route::get('/{claim}', [\App\Http\Controllers\Partner\ClaimController::class, 'show'])->name('show');
    });

    // ─── Warranty Claims ──────────────────────────────────────────────────────
    Route::prefix('warranty-claims')->name('warranty-claims.')
        ->controller(\App\Http\Controllers\Partner\WarrantyClaimController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/{claim}', 'show')->name('show');
            Route::post('/{claim}/respond', 'respond')->name('respond');
        });

    // ─── Warehouses (My Warehouse module) ────────────────────────────────────────
    Route::prefix('warehouses')->name('warehouses.')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->name('index');
        Route::get('/create', [WarehouseController::class, 'create'])->name('create');
        Route::post('/', [WarehouseController::class, 'store'])->name('store');

        // transfers prefix must come before /{warehouse} wildcard to avoid shadowing
        Route::prefix('transfers')->name('transfers.')->group(function () {
            Route::get('/', [WarehouseController::class, 'transfersIndex'])->name('index');
            Route::get('/datatable', [WarehouseController::class, 'transfersDatatable'])->name('datatable');
            Route::get('/create', [WarehouseController::class, 'transferCreate'])->name('create');
            Route::get('/item-search', [WarehouseController::class, 'transferItemSearch'])->name('item-search');
            Route::post('/', [WarehouseController::class, 'transferStore'])->name('store');
            Route::get('/{transfer}', [WarehouseController::class, 'transferShow'])->name('show');
            Route::post('/{transfer}/ship', [WarehouseController::class, 'transferShip'])->name('ship');
            Route::post('/{transfer}/cancel', [WarehouseController::class, 'transferCancel'])->name('cancel');
        });

        Route::post('/inventory/{inventory}/adjust', [WarehouseController::class, 'adjustInventory'])->name('inventory.adjust');
        Route::post('/inventory/{inventory}/count', [WarehouseController::class, 'stockCount'])->name('inventory.count');
        Route::get('/inventory/{inventory}/movements', [WarehouseController::class, 'getMovements'])->name('inventory.movements');

        // datatable endpoints must come before /{warehouse} wildcard to avoid shadowing
        Route::get('/dt/seller', [WarehouseController::class, 'sellerDt'])->name('dt.seller');
        Route::get('/dt/fbn', [WarehouseController::class, 'fbnDt'])->name('dt.fbn');

        Route::get('/{warehouse}', [WarehouseController::class, 'show'])->name('show');
        Route::put('/{warehouse}', [WarehouseController::class, 'update'])->name('update');
        Route::post('/{warehouse}/deactivate', [WarehouseController::class, 'deactivate'])->name('deactivate');
        Route::post('/{warehouse}/inventory', [WarehouseController::class, 'getInventory'])->name('inventory');
        Route::post('/{warehouse}/exceptional-zones/{zone}/toggle', [WarehouseController::class, 'toggleExceptionalZone'])->name('exceptional-zones.toggle');
    });

    // ─── Delivery Ratings ─────────────────────────────────────────────────────
    Route::post('/orders/{subOrder}/rate-delivery', [\App\Http\Controllers\Partner\DeliveryRatingController::class, 'store'])
        ->name('orders.rate-delivery');

    // ─── Packaging Supplies ───────────────────────────────────────────────────
    Route::prefix('packaging-supplies')->name('packaging-supplies.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Partner\PackagingSupplyController::class, 'index'])->name('index');
        Route::get('/request', [\App\Http\Controllers\Partner\PackagingSupplyController::class, 'request'])->name('request');
        Route::post('/request', [\App\Http\Controllers\Partner\PackagingSupplyController::class, 'submitRequest'])->name('submit');
        Route::get('/delivery-fee', [\App\Http\Controllers\Partner\PackagingSupplyController::class, 'deliveryFee'])->name('delivery-fee');
        Route::get('/my-requests', [\App\Http\Controllers\Partner\PackagingSupplyController::class, 'myRequests'])->name('my-requests');
        Route::get('/my-requests/{packagingSupplyRequest}', [\App\Http\Controllers\Partner\PackagingSupplyController::class, 'showRequest'])->name('show-request');
    });

    // ─── Returns ─────────────────────────────────────────────────────────────
    Route::prefix('returns')->name('returns.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Partner\ReturnController::class, 'index'])->name('index')->middleware('vendor.can:returns.view');
        Route::get('{returnNumber}', [\App\Http\Controllers\Partner\ReturnController::class, 'show'])->name('show')->middleware('vendor.can:returns.view');
        Route::post('{returnNumber}/approve', [\App\Http\Controllers\Partner\ReturnController::class, 'approve'])->name('approve')->middleware('vendor.can:returns.process');
        Route::post('{returnNumber}/reject', [\App\Http\Controllers\Partner\ReturnController::class, 'reject'])->name('reject')->middleware('vendor.can:returns.process');
    });

    // ─── Finance / Transactions ───────────────────────────────────────────────
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('/transactions', [\App\Http\Controllers\Partner\FinanceController::class, 'transactions'])->name('transactions')->middleware('vendor.can:finance.view');
        Route::get('/sales-report', [\App\Http\Controllers\Partner\FinanceController::class, 'salesReport'])->name('sales-report')->middleware('vendor.can:finance.view');
        Route::get('/sales-report/export', [\App\Http\Controllers\Partner\FinanceController::class, 'exportSalesReport'])->name('sales-report.export')->middleware('vendor.can:finance.view');
    });

    // ─── Marketer Campaigns (My Campaigns — vendor's own influencer/affiliate campaigns) ──
    Route::prefix('marketer-campaigns')->name('marketer-campaigns.')->group(function () {
        Route::get('/',                [MarketerCampaignController::class, 'index'])->name('index')->middleware('vendor.can:marketer_campaigns.view');
        Route::get('/{marketerCampaign}', [MarketerCampaignController::class, 'show'])->name('show')->middleware('vendor.can:marketer_campaigns.view');
        Route::post('/{marketerCampaign}/cancel', [MarketerCampaignController::class, 'cancel'])->name('cancel')->middleware('vendor.can:marketer_campaigns.cancel');
    });
    // Keep this route — vendor needs to search for marketers when creating campaigns
    Route::get('/campaigns/search-marketers', [MarketerCampaignController::class, 'searchMarketers'])
        ->name('campaigns.search-marketers')->middleware('vendor.can:marketer_campaigns.view');

    // ─── Ads ─────────────────────────────────────────────────────────────────
    Route::prefix('ads')->name('ads.')->group(function () {
        Route::get('/',                   [AdsController::class, 'index'])->name('index');
        Route::post('/datatable',         [AdsController::class, 'datatable'])->name('datatable');
        Route::post('/',                  [AdsController::class, 'store'])->name('store');
        Route::get('/categories',         [AdsController::class, 'categories'])->name('categories');
        Route::get('/{id}',               [AdsController::class, 'show'])->name('show');
        Route::get('/{id}/performance',   [AdsController::class, 'performance'])->name('performance');
        Route::get('/{id}/quality-score', [AdsController::class, 'qualityScore'])->name('quality-score');
        Route::post('/{id}/pause',        [AdsController::class, 'pause'])->name('pause');
        Route::post('/{id}/resume',       [AdsController::class, 'resume'])->name('resume');
    });

    // ─── السوق المفتوح (Classifieds) ─────────────────────────────────────────
    Route::prefix('classifieds')->name('classifieds.')->group(function () {
        Route::get('/',                [ClassifiedListingController::class, 'index'])->name('index');
        Route::post('/datatable',      [ClassifiedListingController::class, 'datatable'])->name('datatable');
        Route::get('/categories',      [ClassifiedListingController::class, 'categories'])->name('categories');
        Route::post('/',               [ClassifiedListingController::class, 'store'])->name('store');
        Route::get('/{id}',            [ClassifiedListingController::class, 'show'])->name('show');
        Route::get('/{id}/data',       [ClassifiedListingController::class, 'showData'])->name('show-data');
        Route::post('/{id}/pause',     [ClassifiedListingController::class, 'pause'])->name('pause');
        Route::post('/{id}/resume',    [ClassifiedListingController::class, 'resume'])->name('resume');
        Route::post('/{id}/mark-sold', [ClassifiedListingController::class, 'markSold'])->name('mark-sold');

        Route::prefix('{id}/inquiries')->name('inquiries.')->group(function () {
            Route::get('/', [ClassifiedListingController::class, 'inquiriesIndex'])->name('index');
            Route::post('/{inquiryId}/status', [ClassifiedListingController::class, 'updateInquiryStatus'])->name('status');
        });

        Route::prefix('{id}/contract')->name('contract.')->group(function () {
            Route::get('/',  [ClassifiedListingController::class, 'contractShow'])->name('show');
            Route::post('/', [ClassifiedListingController::class, 'contractAccept'])->name('accept');
        });
    });
});

Route::get('full-permissions', function () {
    $vendor = auth('vendor')->user();
    $role = Role::where('guard_name', 'vendor')->where('name', 'vendor_owner')->first();
    $permissions = Permission::where('guard_name', 'vendor')->pluck('name');

    $vendor->syncPermissions($permissions);
    $vendor->assignRole($role);
    echo 'Done';
});
