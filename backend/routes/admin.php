<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LiveStreamController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductCostController;
use App\Http\Controllers\Admin\ProductHighlightController;
use App\Http\Controllers\Admin\BestsellerController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomPageController;
use App\Http\Controllers\Admin\CategoryShippingMethodController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\WarrantyPlanController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\FlashSaleController;
use App\Http\Controllers\Admin\PageBuilderController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\VendorSectionLockController;
use App\Http\Controllers\Admin\VendorChangeRequestController;
use App\Http\Controllers\Admin\VendorProductCertificationController;
use App\Http\Controllers\Admin\CountryController;
use App\Http\Controllers\Admin\CityController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CartCardOfferController;
use App\Http\Controllers\Admin\FbtController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\AdminGiftCardController;
use App\Http\Controllers\Admin\AdminVoucherController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\DisputeController;
use App\Http\Controllers\Admin\ReturnController;
use App\Http\Controllers\Admin\WarrantyClaimController;
use App\Http\Controllers\Admin\WarrantyPurchaseController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\WishlistOverviewController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\AdCampaignController;
use App\Http\Controllers\Admin\MarketerCampaignController;
use App\Http\Controllers\Admin\MarketerSettingsController;
use App\Http\Controllers\Admin\AdSlotController;
use App\Http\Controllers\Admin\PaidAdBookingController;
use App\Http\Controllers\Admin\VendorApplicationController;
use App\Http\Controllers\Admin\VendorAcquisitionController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\LedgerController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\PortalContentController;
use App\Http\Controllers\Admin\ContentSettingsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ShippingSubsidyController;
use App\Http\Controllers\Admin\ShippingZoneController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Admin\WarehouseShippingSurchargeController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\FinancialReportController;
use App\Http\Controllers\Admin\ShippingMethodController;
use App\Http\Controllers\Admin\ShippingSettingController;
use App\Http\Controllers\Admin\ShippingWeightSlabController;
use App\Http\Controllers\Admin\DeliveryAgentController;
use App\Http\Controllers\Admin\DeliveryZoneController;
use App\Http\Controllers\Admin\DeliveryAssignmentController;
use App\Http\Controllers\Admin\DeliveryPayoutController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\FbnController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
| Loaded inside: Route::domain('admin.*')->name('admin.')->group(...)
|--------------------------------------------------------------------------
*/

// ─── Auth: Guest routes (login) ─────────────────────────────────────────────────────
Route::middleware('guest:admin')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
});

// ─── Locale switcher (public) ───────────────────────────────────────────────────
Route::post('/set-locale', function (Request $request) {
    $locale = $request->input('locale', 'en');
    if (in_array($locale, config('app.supported_locales', ['en', 'ar']), true)) {
        session(['locale' => $locale]);
    }
    return response()->json(['success' => true]);
})->name('set-locale');

// ─── Broadcasting auth (Reverb channel authorization for admin guard) ────────────
Broadcast::routes(['middleware' => ['web', 'auth.admin']]);

// ─── All protected admin routes ───────────────────────────────────────────────────
Route::middleware(['auth.admin', 'admin.vendor.scope'])->group(function () {

    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // ─── Profile ──────────────────────────────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // ─── Dashboard ────────────────────────────────────────────────────────────────
    Route::get('/', [DashboardController::class, 'index'])->middleware('admin.permission:dashboard.view')->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->middleware('admin.permission:dashboard.view')->name('dashboard.stats');
    Route::get('/dashboard/revenue-chart', [DashboardController::class, 'revenueChart'])->middleware('admin.permission:dashboard.view')->name('dashboard.revenue-chart');
    Route::get('/dashboard/orders-by-status', [DashboardController::class, 'ordersByStatus'])->middleware('admin.permission:dashboard.view')->name('dashboard.orders-by-status');
    Route::get('/dashboard/recent-orders', [DashboardController::class, 'recentOrders'])->middleware('admin.permission:dashboard.view')->name('dashboard.recent-orders');
    Route::get('/dashboard/top-sellers', [DashboardController::class, 'topSellers'])->middleware('admin.permission:dashboard.view')->name('dashboard.top-sellers');
    Route::get('/dashboard/pending-items', [DashboardController::class, 'pendingItems'])->middleware('admin.permission:dashboard.view')->name('dashboard.pending-items');
    Route::get('/dashboard/low-stock', [DashboardController::class, 'lowStock'])->middleware('admin.permission:dashboard.view')->name('dashboard.low-stock');

    // ─── Products ─────────────────────────────────────────────────────────────────
    Route::prefix('products')->name('products.')->middleware('admin.permission:products.view')->group(function () {
        // Specific paths BEFORE the {product} wildcard
        Route::get('/create', [ProductController::class, 'create'])->name('create');
        Route::post('/datatable', [ProductController::class, 'datatable'])->name('datatable');
        Route::post('/bulk', [ProductController::class, 'bulkAction'])->name('bulk');
        Route::post('/generate-variants', [ProductController::class, 'generateVariants'])->name('generate-variants');
        Route::post('/upload-image', [ProductController::class, 'uploadImage'])->name('upload-image');
        Route::get('/check-duplicate', [ProductController::class, 'checkDuplicate'])->name('check-duplicate');
        Route::get('/check-gtin', [ProductController::class, 'checkGtin'])->name('check-gtin');
        Route::post('/validate', [ProductController::class, 'validateStore'])->name('validate');
        Route::delete('/delete-image/{mediaId}', [ProductController::class, 'deleteImage'])->name('delete-image');
        Route::post('/country-settings/{setting}', [ProductController::class, 'updateCountrySetting'])->name('update-country-setting');

        // CRUD
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
        Route::post('/{product}/reorder-images', [ProductController::class, 'reorderImages'])->name('reorder-images');
        Route::get('/{product}/country-settings', [ProductController::class, 'countrySettings'])->name('country-settings');
        Route::post('/{product}/validate', [ProductController::class, 'validateUpdate'])->name('validate-update');
        Route::put('/{product}', [ProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');

        // ── Variant slug management ────────────────────────────────────────────
        Route::get('/{product}/variants/{variant}', [ProductController::class, 'variantDetail'])
            ->name('variants.show');
        Route::get('/{product}/variants/{variant}/slug-preview', [ProductController::class, 'slugPreview'])
            ->name('variants.slug-preview');
        Route::get('/variants/{variant}/url-info', [ProductController::class, 'variantUrlInfo'])
            ->name('variants.url-info');
        Route::patch('/{product}/variants/{variant}/regenerate-slug', [ProductController::class, 'regenerateVariantSlug'])
            ->name('variants.regenerate-slug')
            ->middleware('admin.permission:products.edit');
        Route::get('/{product}/variants/{variant}/images', [ProductController::class, 'variantImages'])
            ->name('variants.images');
        Route::post('/{product}/variants/{variant}/reorder-images', [ProductController::class, 'reorderVariantImages'])
            ->name('variants.reorder-images')
            ->middleware('admin.permission:products.edit');

        // ── Frequently bought together ─────────────────────────────────────────
        Route::prefix('/{product}/frequently-bought-together')->name('frequently-bought-together.')->group(function () {
            Route::get('/', [ProductController::class, 'frequentlyBoughtTogetherIndex'])->name('index');
            Route::get('/search', [ProductController::class, 'frequentlyBoughtTogetherSearch'])->name('search');
            Route::post('/', [ProductController::class, 'frequentlyBoughtTogetherAdd'])->name('add');
            Route::delete('/{relatedProduct}', [ProductController::class, 'frequentlyBoughtTogetherRemove'])->name('remove');
            Route::post('/reorder', [ProductController::class, 'frequentlyBoughtTogetherReorder'])->name('reorder');
        });

        // ── Cost Reference (requires elevated permission) ─────────────────────
        Route::prefix('/{product}/cost')->name('cost.')->group(function () {
            Route::get('/', [ProductCostController::class, 'show'])->name('show');
            Route::post('/', [ProductCostController::class, 'save'])->name('save');
            Route::post('/calculate', [ProductCostController::class, 'calculateMargin'])->name('calculate');
            Route::post('/check-competitors', [ProductCostController::class, 'checkCompetitorPrices'])->name('check-competitors');
        });
    });

    // ─── Product Highlights ──────────────────────────────────────────────────────
    Route::prefix('product-highlights')->name('product-highlights.')->middleware('admin.permission:products.view')->group(function () {
        Route::get('/create', [ProductHighlightController::class, 'create'])->name('create');
        Route::post('/datatable', [ProductHighlightController::class, 'datatable'])->name('datatable');
        Route::get('/search/products', [ProductHighlightController::class, 'searchProducts'])->name('search.products');
        Route::get('/', [ProductHighlightController::class, 'index'])->name('index');
        Route::post('/', [ProductHighlightController::class, 'store'])->name('store');
        Route::get('/{productHighlight}/edit', [ProductHighlightController::class, 'edit'])->name('edit');
        Route::patch('/{productHighlight}', [ProductHighlightController::class, 'update'])->name('update');
        Route::delete('/{productHighlight}', [ProductHighlightController::class, 'destroy'])->name('destroy');
    });

    // ─── Product Relations / Frequently Bought Together (overview) ─────────────
    Route::prefix('fbt')->name('fbt.')->middleware('admin.permission:products.view')->group(function () {
        Route::post('/datatable', [FbtController::class, 'datatable'])->name('datatable');
        Route::get('/', [FbtController::class, 'index'])->name('index');
    });

    // ─── Bestseller Rankings (read-only) ────────────────────────────────────────
    Route::prefix('bestsellers')->name('bestsellers.')->middleware('admin.permission:products.view')->group(function () {
        Route::post('/datatable', [BestsellerController::class, 'datatable'])->name('datatable');
        Route::get('/', [BestsellerController::class, 'index'])->name('index');
    });

    // ─── Brands ──────────────────────────────────────────────────────────────────
    Route::prefix('brands')->name('brands.')->middleware('admin.permission:brands.view')->group(function () {
        Route::get('/create', [BrandController::class, 'create'])->name('create');
        Route::post('/datatable', [BrandController::class, 'datatable'])->name('datatable');
        Route::get('/search', [BrandController::class, 'search'])->name('search');
        Route::get('/', [BrandController::class, 'index'])->name('index');
        Route::post('/', [BrandController::class, 'store'])->name('store');
        Route::get('/{brand}/edit', [BrandController::class, 'edit'])->name('edit');
        Route::put('/{brand}', [BrandController::class, 'update'])->name('update');
        Route::delete('/{brand}', [BrandController::class, 'destroy'])->name('destroy');
        Route::post('/{brand}/upload-logo',  [\App\Http\Controllers\Admin\BrandController::class, 'uploadLogo'])
            ->name('upload-logo');
        Route::delete('/{brand}/delete-logo', [\App\Http\Controllers\Admin\BrandController::class, 'deleteLogo'])
            ->name('delete-logo');
    });

    // ─── Warranty Plans ─────────────────────────────────────────────────────────────
    Route::prefix('warranty-plans')->name('warranty-plans.')->middleware('admin.permission:warranty_plans.view')->group(function () {
        Route::get('/create', [WarrantyPlanController::class, 'create'])->name('create');
        Route::post('/datatable', [WarrantyPlanController::class, 'datatableData'])->name('datatable');
        Route::get('/', [WarrantyPlanController::class, 'index'])->name('index');
        Route::post('/', [WarrantyPlanController::class, 'store'])->name('store');
        Route::get('/{warrantyPlan}/edit', [WarrantyPlanController::class, 'edit'])->name('edit');
        Route::patch('/{warrantyPlan}', [WarrantyPlanController::class, 'update'])->name('update');
        Route::post('/{warrantyPlan}/toggle', [WarrantyPlanController::class, 'toggleActive'])->name('toggle');
        Route::delete('/{warrantyPlan}', [WarrantyPlanController::class, 'destroy'])->name('destroy');
    });

    // ─── Notifications ────────────────────────────────────────────────────────────
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


    // ─── Categories (CRUD) ────────────────────────────────────────────────────────
    Route::prefix('categories')->name('categories.')->middleware('admin.permission:categories.view')->group(function () {
        Route::get('/create', [CategoryController::class, 'create'])->name('create');
        Route::post('/reorder', [CategoryController::class, 'reorder'])->name('reorder');
        Route::post('/bulk-commission', [CategoryController::class, 'bulkCommission'])->name('bulk-commission');
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::get('/{category}/edit', [CategoryController::class, 'edit'])->name('edit');
        Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
        Route::post('/{category}/toggle-featured', [CategoryController::class, 'toggleFeatured'])->name('toggle-featured');
        Route::post('/{category}/toggle-visible',  [CategoryController::class, 'toggleVisible'])->name('toggle-visible');
        Route::post('/{category}/sync-attributes', [CategoryController::class, 'syncAttributes'])->name('sync-attributes');
        Route::post('/{category}/marketer-commission', [CategoryController::class, 'updateMarketerCommission'])->name('marketer-commission.update');
        Route::post('/{category}/upload-image', [CategoryController::class, 'uploadImage'])->name('upload-image');
        Route::delete('/{category}/delete-image', [CategoryController::class, 'deleteImage'])->name('delete-image');

        Route::prefix('{category}/shipping-methods')->name('shipping-methods.')->group(function () {
            Route::get('/', [CategoryShippingMethodController::class, 'index'])->name('index');
            Route::post('/', [CategoryShippingMethodController::class, 'store'])->name('store');
            Route::put('/{csm}', [CategoryShippingMethodController::class, 'update'])->name('update');
            Route::delete('/{csm}', [CategoryShippingMethodController::class, 'destroy'])->name('destroy');
            Route::post('/reorder', [CategoryShippingMethodController::class, 'reorder'])->name('reorder');
        });


        Route::get('/search', function (Request $request) {
            $term = trim($request->input('q', ''));
            $results = DB::table('categories')
                ->where('is_active', true)
                ->where(function ($q) use ($term) {
                    $q->where('name_en', 'like', "%{$term}%")
                        ->orWhere('name_ar', 'like', "%{$term}%");
                })
                ->limit(30)
                ->get(['id', 'name_en as text']);
            return response()->json(['results' => $results]);
        })->name('search');

        Route::get('/{id}/attributes', function (string $id) {
            $attrs = DB::table('category_attributes as ca')
                ->join('attributes as a', 'a.id', '=', 'ca.attribute_id')
                ->where('ca.category_id', $id)
                ->where('a.is_variant_attribute', true)
                ->select('a.id', 'a.name_en')
                ->orderBy('a.sort_order')
                ->get();
            return response()->json(['data' => $attrs]);
        })->name('attributes');

    });

    // ─── Custom Pages (CRUD) ──────────────────────────────────────────────────────
    Route::prefix('custom-pages')->name('custom-pages.')->middleware('admin.permission:categories.view')->group(function () {
        Route::get('/create', [CustomPageController::class, 'create'])->name('create');
        Route::post('/reorder', [CustomPageController::class, 'reorder'])->name('reorder');
        Route::get('/', [CustomPageController::class, 'index'])->name('index');
        Route::post('/', [CustomPageController::class, 'store'])->name('store');
        Route::get('/{customPage}/edit', [CustomPageController::class, 'edit'])->name('edit');
        Route::put('/{customPage}', [CustomPageController::class, 'update'])->name('update');
        Route::delete('/{customPage}', [CustomPageController::class, 'destroy'])->name('destroy');
        Route::post('/{customPage}/toggle-active', [CustomPageController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/{customPage}/categories', [CustomPageController::class, 'syncCategories'])->name('categories.sync');
        Route::post('/{customPage}/upload-image', [CustomPageController::class, 'uploadImage'])->name('upload-image');
    });

    // ─── Attributes (CRUD) ────────────────────────────────────────────────────────
    Route::prefix('attributes')->name('attributes.')->middleware('admin.permission:attributes.view')->group(function () {
        Route::get('/create', [AttributeController::class, 'create'])->name('create');
        Route::post('/datatable', [AttributeController::class, 'datatable'])->name('datatable');
        Route::get('/', [AttributeController::class, 'index'])->name('index');
        Route::post('/', [AttributeController::class, 'store'])->name('store');
        Route::get('/{attribute}/edit', [AttributeController::class, 'edit'])->name('edit');
        Route::put('/{attribute}', [AttributeController::class, 'update'])->name('update');
        Route::delete('/{attribute}', [AttributeController::class, 'destroy'])->name('destroy');
        Route::post('/{attribute}/values', [AttributeController::class, 'storeValue'])->name('values.store');
        Route::put('/{attribute}/values/{value}', [AttributeController::class, 'updateValue'])->name('values.update');
        Route::delete('/{attribute}/values/{value}', [AttributeController::class, 'destroyValue'])->name('values.destroy');
        Route::post('/{attribute}/values/reorder', [AttributeController::class, 'reorderValues'])->name('values.reorder');
        Route::post('/{attribute}/values/{value}/upload-swatch', [AttributeController::class, 'uploadValueSwatch'])
            ->name('values.upload-swatch')
            ->middleware('admin.permission:attributes.edit');
        Route::delete('/{attribute}/values/{value}/delete-swatch', [AttributeController::class, 'deleteValueSwatch'])
            ->name('values.delete-swatch')
            ->middleware('admin.permission:attributes.edit');
        Route::post('/{attribute}/values/{value}/regenerate-variant-slugs', [AttributeController::class, 'regenerateVariantSlugs'])
            ->name('values.regenerate-variant-slugs')
            ->middleware('admin.permission:attributes.edit');
    });

    Route::post('/country', function (Request $request) {
        $code = strtoupper(trim($request->input('country', '')));
        if ($code && preg_match('/^[A-Z]{2,3}$/', $code)) {
            session(['admin_country' => $code]);
        }
        return response()->json(['success' => true]);
    })->name('country');

    // ─── Placeholders ─────────────────────────────────────────────────────────────
// ─── Orders ───────────────────────────────────────────────────────────────────

    Route::prefix('orders')->name('orders.')->middleware('admin.permission:orders.view')->group(function () {
        Route::post('/datatable', [OrderController::class, 'datatable'])->name('datatable');
        Route::post('/update-sub-order-status', [OrderController::class, 'updateSubOrderStatus'])->name('update-sub-order-status');
        Route::post('/{id}/update-status', [OrderController::class, 'updateOrderStatus'])->name('update-status');
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/{id}', [OrderController::class, 'show'])->name('show');
        Route::post('/{id}/force-cancel', [OrderController::class, 'forceCancel'])->name('force-cancel');
        Route::post('/{id}/cancel-items', [OrderController::class, 'cancelItems'])->name('cancel-items');
        Route::post('/{id}/refund', [OrderController::class, 'processRefund'])->name('refund');
        Route::post('/{id}/dispute', [OrderController::class, 'escalateDispute'])->name('dispute');
        Route::post('/{id}/flag-fraud', [OrderController::class, 'flagFraud'])->name('flag-fraud');
    });
    Route::get('/sub-orders/{id}/next-statuses', [OrderController::class, 'nextStatuses'])->middleware('admin.permission:orders.view')->name('sub-orders.next-statuses');
    Route::get('/sub-orders/{subOrder}/shipping-methods', [OrderController::class, 'availableShippingMethods'])->middleware('admin.permission:orders.view')->name('orders.sub-orders.shipping-methods');
    Route::post('/sub-orders/{subOrder}/assign-shipping', [OrderController::class, 'assignShippingMethod'])->middleware('admin.permission:orders.view')->name('orders.sub-orders.assign-shipping');
    // ─── Payouts ──────────────────────────────────────────────────────────────────

    Route::prefix('payouts')->name('payouts.')->middleware('admin.permission:payouts.view')->group(function () {
        Route::get('/', [PayoutController::class, 'index'])->name('index');
        Route::post('/datatable', [PayoutController::class, 'datatable'])->name('datatable');
        Route::get('/{payout}', [PayoutController::class, 'show'])->name('show');
        Route::post('/{payout}/approve', [PayoutController::class, 'approve'])->name('approve');
        Route::post('/{payout}/process', [PayoutController::class, 'process'])->name('process');
        Route::post('/{payout}/hold', [PayoutController::class, 'hold'])->name('hold');
        Route::post('/{payout}/recalculate', [PayoutController::class, 'recalculate'])->name('recalculate');
    });

    // ─── Flash Sales ──────────────────────────────────────────────────────────────

    Route::prefix('flash-sales')->name('flash-sales.')->middleware('admin.permission:flash_sales.view')->group(function () {

        // List + create
        Route::get('/', [FlashSaleController::class, 'index'])->name('index');
        Route::post('/datatable', [FlashSaleController::class, 'datatable'])->name('datatable');
        Route::get('/create', [FlashSaleController::class, 'create'])->name('create')
            ->middleware('admin.permission:flash_sales.create');
        Route::post('/', [FlashSaleController::class, 'store'])->name('store')
            ->middleware('admin.permission:flash_sales.create');

        // Misc (before /{flashSale} wildcard)
        Route::get('/price-history', [FlashSaleController::class, 'priceHistory'])->name('price-history');
        Route::get('/search/admin-listings', [FlashSaleController::class, 'searchAdminListings'])->name('search.admin-listings');
        Route::get('/search/vendor-listings', [FlashSaleController::class, 'searchVendorListings'])->name('search.vendor-listings');
        Route::get('/search/vendors', [FlashSaleController::class, 'searchVendors'])->name('search.vendors');

        // Submission review (before /{flashSale} wildcard)
        Route::post('/submissions/{submission}/review', [FlashSaleController::class, 'reviewSubmission'])
            ->name('submissions.review')
            ->middleware('admin.permission:flash_sales.review_submissions');

        Route::get('/submissions/{submission}/detail', [FlashSaleController::class, 'submissionDetail'])
            ->name('submissions.detail')
            ->middleware('admin.permission:flash_sales.review_submissions');

        // Per-sale routes
        Route::prefix('/{flashSale}')->group(function () {

            Route::get('/', [FlashSaleController::class, 'show'])->name('show');
            Route::get('/edit', [FlashSaleController::class, 'edit'])->name('edit')
                ->middleware('admin.permission:flash_sales.edit');
            Route::put('/', [FlashSaleController::class, 'update'])->name('update')
                ->middleware('admin.permission:flash_sales.edit');
            Route::delete('/', [FlashSaleController::class, 'destroy'])->name('destroy')
                ->middleware('admin.permission:flash_sales.edit');

            Route::post('/transition', [FlashSaleController::class, 'transition'])->name('transition');
            Route::get('/eligible-vendor-count', [FlashSaleController::class, 'eligibleVendorCount'])->name('eligible-vendor-count');
            Route::post('/invite-vendors', [FlashSaleController::class, 'inviteVendors'])->name('invite-vendors');
            Route::post('/invitations/datatable', [FlashSaleController::class, 'invitationsDatatable'])->name('invitations.datatable');
            Route::post('/invitations/{invitation}/resend', [FlashSaleController::class, 'resendInvitation'])->name('invitations.resend')
                ->middleware('admin.permission:flash_sales.edit');

            Route::get('/submission-stats', [FlashSaleController::class, 'submissionStats'])->name('submission-stats');
            Route::post('/submissions/datatable', [FlashSaleController::class, 'submissionsDatatable'])->name('submissions.datatable');
            Route::post('/submissions', [FlashSaleController::class, 'storeSubmission'])->name('submissions.store')
                ->middleware('admin.permission:flash_sales.edit');
            Route::post('/bulk-review', [FlashSaleController::class, 'bulkReviewSubmissions'])->name('submissions.bulk-review')
                ->middleware('admin.permission:flash_sales.review_submissions');

            Route::get('/live-data', [FlashSaleController::class, 'liveMonitorData'])->name('live-data');
            Route::get('/analytics-data', [FlashSaleController::class, 'analyticsData'])->name('analytics-data');
        });
    });

    // ─── Page Builder ──────────────────────────────────────────────────────────────

    Route::prefix('page-builder')->name('page-builder.')->middleware('admin.permission:pages.view')->group(function () {

        // Builder UI
        Route::get('/', [PageBuilderController::class, 'index'])->name('index');
        Route::get('/load', [PageBuilderController::class, 'loadPage'])->name('load');

        // Pages
        Route::post('/pages', [PageBuilderController::class, 'createPage'])->name('pages.create');
        Route::put('/pages/{page}', [PageBuilderController::class, 'updatePage'])->name('pages.update');
        Route::delete('/pages/{page}', [PageBuilderController::class, 'deletePage'])->name('pages.delete');
        Route::post('/pages/{page}/duplicate', [PageBuilderController::class, 'duplicatePage'])->name('pages.duplicate');
        Route::post('/pages/{page}/publish', [PageBuilderController::class, 'publishPage'])->name('pages.publish');
        Route::post('/pages/{page}/clear-cache', [PageBuilderController::class, 'clearPageCache'])->name('pages.clear-cache');
        Route::get('/pages/{page}/revisions', [PageBuilderController::class, 'getPageRevisions'])->name('pages.revisions');
        Route::post('/page-revisions/{revision}/restore', [PageBuilderController::class, 'restorePageRevision'])->name('page-revisions.restore');

        // Sections
        Route::post('/sections', [PageBuilderController::class, 'addSection'])->name('sections.add');
        Route::put('/sections/{section}', [PageBuilderController::class, 'updateSection'])->name('sections.update');
        Route::delete('/sections/{section}', [PageBuilderController::class, 'deleteSection'])->name('sections.delete');
        Route::post('/sections/reorder', [PageBuilderController::class, 'reorderSections'])->name('sections.reorder');

        // Blocks
        Route::post('/blocks', [PageBuilderController::class, 'addBlock'])->name('blocks.add');
        Route::get('/blocks/{block}/config', [PageBuilderController::class, 'getBlockConfig'])->name('blocks.get-config');
        Route::get('/blocks/{block}/analytics', [PageBuilderController::class, 'blockAnalytics'])->name('blocks.analytics');
        Route::post('/blocks/{block}/config', [PageBuilderController::class, 'updateBlockConfig'])->name('blocks.config');
        Route::post('/blocks/{block}/visibility', [PageBuilderController::class, 'updateBlockVisibility'])->name('blocks.visibility');
        Route::post('/blocks/{block}/assign-column', [PageBuilderController::class, 'assignBlockColumn'])->name('blocks.assign-column');
        Route::delete('/blocks/{block}', [PageBuilderController::class, 'removeBlock'])->name('blocks.remove');
        Route::post('/reorder', [PageBuilderController::class, 'reorderBlocks'])->name('reorder');

        // Block revisions
        Route::get('/blocks/{block}/revisions', [PageBuilderController::class, 'getRevisions'])->name('blocks.revisions');
        Route::post('/revisions/{revision}/restore', [PageBuilderController::class, 'restoreBlockRevision'])->name('revisions.restore');

        // Config form partials
        Route::get('/config-form', [PageBuilderController::class, 'configFormPartial'])->name('config-form');

        // Slides
        Route::get('/blocks/{block}/slides', [PageBuilderController::class, 'getSlides'])->name('slides.list');
        Route::post('/blocks/{block}/slides', [PageBuilderController::class, 'saveSlide'])->name('slides.save');
        Route::delete('/slides/{slide}', [PageBuilderController::class, 'deleteSlide'])->name('slides.delete');
        Route::post('/blocks/{block}/slides/reorder', [PageBuilderController::class, 'reorderSlides'])->name('slides.reorder');

        // Ad images
        Route::get('/blocks/{block}/ad-images/panel', [PageBuilderController::class, 'getAdImagesManagerPanel'])->name('ad-images.panel');
        Route::get('/blocks/{block}/ad-images', [PageBuilderController::class, 'getAdImages'])->name('ad-images.list');
        Route::post('/blocks/{block}/ad-images', [PageBuilderController::class, 'saveAdImage'])->name('ad-images.save');
        Route::delete('/ad-images/{adImage}', [PageBuilderController::class, 'deleteAdImage'])->name('ad-images.delete');
        Route::post('/blocks/{block}/ad-images/reorder', [PageBuilderController::class, 'reorderAdImages'])->name('ad-images.reorder');

        // Slide image upload
        Route::post('/slides/upload-image', [PageBuilderController::class, 'uploadSlideImage'])->name('slides.upload-image');

        // Ad image upload
        Route::post('/ad-images/upload-image', [PageBuilderController::class, 'uploadAdImage'])->name('ad-images.upload-image');
        Route::post('/promo-tiles/upload-image', [PageBuilderController::class, 'uploadPromoTileImage'])->name('promo-tiles.upload-image');

        // Section background image upload
        Route::post('/sections/upload-background-image', [PageBuilderController::class, 'uploadSectionBackgroundImage'])->name('sections.upload-background-image');

        // Search (for manual selectors)
        Route::get('/search/products', [PageBuilderController::class, 'searchProducts'])->name('search.products');
        Route::get('/search/categories', [PageBuilderController::class, 'searchCategories'])->name('search.categories');
        Route::get('/search/brands', [PageBuilderController::class, 'searchBrands'])->name('search.brands');
        Route::get('/search/vendors', [PageBuilderController::class, 'searchVendors'])->name('search.vendors');
        Route::get('/search/custom-pages', [PageBuilderController::class, 'searchCustomPages'])->name('search.custom-pages');
        Route::get('/search/flash-sales', [PageBuilderController::class, 'searchFlashSales'])->name('search.flash-sales');
        Route::get('/search/vendor-listings', [PageBuilderController::class, 'searchVendorListings'])->name('search.vendor-listings');
        Route::get('/search/admin-listings', [PageBuilderController::class, 'searchAdminListings'])->name('search.admin-listings');

        // Block product pickers
        Route::get('/blocks/{block}/products', [PageBuilderController::class, 'getBlockProducts'])->name('products.list');
        Route::post('/blocks/{block}/products', [PageBuilderController::class, 'addBlockProduct'])->name('products.add');
        Route::delete('/block-products/{blockProduct}', [PageBuilderController::class, 'removeBlockProduct'])->name('products.remove');
        Route::post('/blocks/{block}/products/reorder', [PageBuilderController::class, 'reorderBlockProducts'])->name('products.reorder');

        // Block category pickers (category_pills)
        Route::get('/blocks/{block}/categories', [PageBuilderController::class, 'getBlockCategories'])->name('categories.list');
        Route::post('/blocks/{block}/categories', [PageBuilderController::class, 'addBlockCategory'])->name('categories.add');
        Route::delete('/block-categories/{blockCategory}', [PageBuilderController::class, 'removeBlockCategory'])->name('categories.remove');
        Route::post('/blocks/{block}/categories/reorder', [PageBuilderController::class, 'reorderBlockCategories'])->name('categories.reorder');

        // Block seller pickers (brand_strip)
        Route::get('/blocks/{block}/sellers', [PageBuilderController::class, 'getBlockSellers'])->name('sellers.list');
        Route::post('/blocks/{block}/sellers', [PageBuilderController::class, 'addBlockSeller'])->name('sellers.add');
        Route::delete('/block-sellers/{blockSeller}', [PageBuilderController::class, 'removeBlockSeller'])->name('sellers.remove');
        Route::post('/blocks/{block}/sellers/reorder', [PageBuilderController::class, 'reorderBlockSellers'])->name('sellers.reorder');

        // ── Brand Strip — Brand picker ─────────────────────────────────────────────
        Route::get('/blocks/{block}/brands', [PageBuilderController::class, 'loadBlockBrands'])->name('blocks.brands.load');
        Route::post('/blocks/{block}/brands', [PageBuilderController::class, 'addBlockBrand'])->name('blocks.brands.add');
        Route::delete('/block-brands/{blockBrand}', [PageBuilderController::class, 'removeBlockBrand'])->name('block-brands.remove');
        Route::post('/blocks/{block}/brands/reorder', [PageBuilderController::class, 'reorderBlockBrands'])->name('blocks.brands.reorder');
    });

    // ─── Vendors ─────────────────────────────────────────────────────────────────

    Route::prefix('vendors')->name('vendors.')->middleware('admin.permission:vendors.view')->group(function () {
        Route::post('/datatable', [VendorController::class, 'datatable'])->name('datatable');
        Route::post('/bulk', [VendorController::class, 'bulkAction'])->name('bulk');

        Route::post('/documents/{document}/verify', [VendorController::class, 'verifyDocument'])->name('documents.verify');
        Route::post('/documents/{document}/reject', [VendorController::class, 'rejectDocument'])->name('documents.reject');

        Route::get('/', [VendorController::class, 'index'])->name('index');
        Route::get('/{vendor}', [VendorController::class, 'show'])->name('show');
        Route::put('/{vendor}', [VendorController::class, 'update'])->name('update');

        Route::post('/{vendor}/approve', [VendorController::class, 'approve'])->name('approve');
        Route::post('/{vendor}/reject', [VendorController::class, 'reject'])->name('reject');
        Route::post('/{vendor}/request-info', [VendorController::class, 'requestInfo'])->name('request-info');
        Route::post('/{vendor}/suspend', [VendorController::class, 'suspend'])->name('suspend');
        Route::post('/{vendor}/reactivate', [VendorController::class, 'reactivate'])->name('reactivate');
        Route::post('/{vendor}/blacklist', [VendorController::class, 'blacklist'])->name('blacklist');
        Route::post('/{vendor}/strikes', [VendorController::class, 'issueStrike'])->name('strikes.store');
        Route::post('/{vendor}/hold', [VendorController::class, 'placeHold'])->name('hold.place');
        Route::post('/{vendor}/release-hold', [VendorController::class, 'releaseHold'])->name('hold.release');
        Route::post('/{vendor}/assign-manager', [VendorController::class, 'assignManager'])->name('assign-manager');
        Route::patch('/{vendor}/account-manager', [VendorController::class, 'assignAccountManager'])->name('account-manager');
        Route::get('/{vendor}/documents', [VendorController::class, 'documents'])->name('documents.index');
        Route::post('/{vendor}/bank-accounts/{accountId}/verify', [VendorController::class, 'verifyBankAccount'])->name('bank-accounts.verify');
        Route::get('/{vendor}/performance-data', [VendorController::class, 'performanceData'])->name('performance-data');
        Route::post('/{vendor}/notify', [VendorController::class, 'sendNotification'])->name('notify');

        Route::post('/{vendor}/team/{vendorAdmin}/deactivate', [VendorController::class, 'deactivateTeamMember'])->name('team.deactivate');
        Route::post('/{vendor}/team/{vendorAdmin}/reactivate', [VendorController::class, 'reactivateTeamMember'])->name('team.reactivate');

        Route::post('/{vendor}/lock', [VendorSectionLockController::class, 'lock'])->name('lock');
        Route::post('/{vendor}/unlock', [VendorSectionLockController::class, 'unlock'])->name('unlock');

        Route::get('/{vendor}/acquisition-agent', [VendorAcquisitionController::class, 'show'])->name('acquisition-agent.show');
        Route::post('/{vendor}/acquisition-agent', [VendorAcquisitionController::class, 'store'])->name('acquisition-agent.store');
        Route::put('/{vendor}/acquisition-agent/{commission}', [VendorAcquisitionController::class, 'update'])->name('acquisition-agent.update');
        Route::post('/{vendor}/acquisition-agent/{commission}/revoke', [VendorAcquisitionController::class, 'revoke'])->name('acquisition-agent.revoke');
    });

    // ─── Vendor Acquisition Commissions ───────────────────────────────────────────
    Route::prefix('acquisition-commissions')->name('acquisition-commissions.')->middleware('admin.permission:vendors.view')->group(function () {
        Route::post('/datatable', [VendorAcquisitionController::class, 'datatable'])->name('datatable');
        Route::get('/', [VendorAcquisitionController::class, 'index'])->name('index');
    });

    // ─── My Acquisition Commissions (agent self-view) ─────────────────────────────
    Route::prefix('my-acquisition-commissions')->name('my-acquisition-commissions.')->group(function () {
        Route::get('/', [VendorAcquisitionController::class, 'myCommissions'])->name('index');
        Route::get('/datatable', [VendorAcquisitionController::class, 'myDatatable'])->name('datatable');
    });

    // ─── Vendor Change Requests ────────────────────────────────────────────────
    Route::prefix('vendor-change-requests')->name('vendor-change-requests.')->middleware('admin.permission:vendor_change_requests.view')->group(function () {
        Route::post('/datatable', [VendorChangeRequestController::class, 'datatable'])->name('datatable');
    });
    Route::resource('vendor-change-requests', VendorChangeRequestController::class)
        ->only(['index', 'show'])
        ->middleware('admin.permission:vendor_change_requests.view');
    Route::post('vendor-change-requests/{changeRequest}/approve', [VendorChangeRequestController::class, 'approve'])
        ->name('vendor-change-requests.approve')
        ->middleware('admin.permission:vendor_change_requests.approve');
    Route::post('vendor-change-requests/{changeRequest}/reject', [VendorChangeRequestController::class, 'reject'])
        ->name('vendor-change-requests.reject')
        ->middleware('admin.permission:vendor_change_requests.approve');

    // ─── Vendor Product Certifications ──────────────────────────────────────────
    Route::prefix('vendor-product-certifications')->name('vendor-product-certifications.')->middleware('admin.permission:vendor_product_certifications.view')->group(function () {
        Route::get('/', [VendorProductCertificationController::class, 'index'])->name('index');
        Route::get('/{id}', [VendorProductCertificationController::class, 'show'])->name('show');
        Route::get('/{id}/download', [VendorProductCertificationController::class, 'download'])->name('download')->middleware('signed');
        Route::post('/bulk-approve', [VendorProductCertificationController::class, 'bulkApprove'])
            ->name('bulk-approve')
            ->middleware('admin.permission:vendor_product_certifications.approve');
        Route::post('/{id}/approve', [VendorProductCertificationController::class, 'approve'])
            ->name('approve')
            ->middleware('admin.permission:vendor_product_certifications.approve');
        Route::post('/{id}/reject', [VendorProductCertificationController::class, 'reject'])
            ->name('reject')
            ->middleware('admin.permission:vendor_product_certifications.approve');
    });

    // ─── Geography ───────────────────────────────────────────────────────────────

    Route::prefix('countries')->name('countries.')->middleware('admin.permission:countries.view')->group(function () {
        Route::post('/datatable', [CountryController::class, 'datatable'])->name('datatable');
        Route::get('/create', [CountryController::class, 'create'])->name('create');
        Route::post('/', [CountryController::class, 'store'])->name('store');
        Route::get('/', [CountryController::class, 'index'])->name('index');
        Route::get('/{id}/edit', [CountryController::class, 'edit'])->name('edit');
        Route::put('/{id}', [CountryController::class, 'update'])->name('update');
        Route::delete('/{id}', [CountryController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/launch', [CountryController::class, 'launch'])->name('launch');
        Route::post('/{id}/deactivate', [CountryController::class, 'deactivate'])->name('deactivate');
        Route::post('/{id}/reactivate', [CountryController::class, 'reactivate'])->name('reactivate');
        // Shipping Settings
        Route::post('/{id}/shipping-settings', [CountryController::class, 'updateShippingSettings'])->name('shipping-settings.update');
        // Category Overrides
        Route::post('/{id}/categories/datatable', [CountryController::class, 'categoryOverridesDatatable'])->name('categories.datatable');
        Route::post('/{id}/category-overrides', [CountryController::class, 'updateCategoryOverrides'])->name('category-overrides.update');
    });

    // ─── Cities ─────────────────────────────────────────────────────────────────────────────
    Route::prefix('cities')->name('cities.')->middleware('admin.permission:countries.view')->group(function () {
        Route::post('/datatable', [CityController::class, 'datatable'])->name('datatable');
        Route::post('/bulk-import', [CityController::class, 'bulkImport'])->name('bulk-import');
        Route::get('/create', [CityController::class, 'create'])->name('create');
        Route::post('/', [CityController::class, 'store'])->name('store');
        Route::get('/', [CityController::class, 'index'])->name('index');
        Route::get('/{id}/edit', [CityController::class, 'edit'])->name('edit');
        Route::put('/{id}', [CityController::class, 'update'])->name('update');
        Route::delete('/{id}', [CityController::class, 'destroy'])->name('destroy');
    });

    // ─── Currencies ────────────────────────────────────────────────────────────────────────────
    Route::prefix('currencies')->name('currencies.')->middleware('admin.permission:countries.view')->group(function () {
        Route::get('/', [CurrencyController::class, 'index'])->name('index');
        Route::get('/rates-table', [CurrencyController::class, 'ratesTable'])->name('rates-table');
        Route::post('/dispatch-update', [CurrencyController::class, 'dispatchUpdate'])->name('dispatch-update');
        Route::post('/refresh-rates', [CurrencyController::class, 'refreshRates'])->name('refresh-rates');
        Route::get('/{code}/edit', [CurrencyController::class, 'edit'])->name('edit');
        Route::put('/{code}', [CurrencyController::class, 'update'])->name('update');
        Route::patch('/{code}/rate', [CurrencyController::class, 'updateRate'])->name('update-rate');
    });

    // ─── Coupons ─────────────────────────────────────────────────────────────────
    Route::prefix('coupons')->name('coupons.')->middleware('admin.permission:coupons.view')->group(function () {
        Route::get('/create', [CouponController::class, 'create'])->name('create');
        Route::get('/generate-code', [CouponController::class, 'generateCode'])->name('generate-code');
        Route::get('/search/customers', [CouponController::class, 'searchCustomers'])->name('search-customers');
        Route::post('/datatable', [CouponController::class, 'datatable'])->name('datatable');
        Route::post('/bulk', [CouponController::class, 'bulkAction'])->name('bulk');
        Route::post('/clear-cache', [CouponController::class, 'clearCache'])->name('clear-cache');
        Route::get('/', [CouponController::class, 'index'])->name('index');
        Route::post('/', [CouponController::class, 'store'])->name('store');
        Route::get('/{coupon}/usages', [CouponController::class, 'usages'])->name('usages');
        Route::get('/{coupon}/usage-chart', [CouponController::class, 'usageChart'])->name('usage-chart');
        Route::get('/{coupon}/edit', [CouponController::class, 'edit'])->name('edit');
        Route::get('/{coupon}', [CouponController::class, 'show'])->name('show');
        Route::put('/{coupon}', [CouponController::class, 'update'])->name('update');
        Route::put('/{coupon}/toggle-active', [CouponController::class, 'toggleActive'])->name('toggle-active');
        Route::delete('/{coupon}', [CouponController::class, 'destroy'])->name('destroy');
    });

    // ─── Vouchers ────────────────────────────────────────────────────────────────
    Route::prefix('vouchers')->name('vouchers.')->group(function () {
        Route::middleware('admin.permission:vouchers.create')->group(function () {
            Route::get('/create', [AdminVoucherController::class, 'create'])->name('create');
            Route::post('/', [AdminVoucherController::class, 'store'])->name('store');
            Route::post('/bulk-generate', [AdminVoucherController::class, 'bulkGenerate'])->name('bulk_generate');
        });
        Route::middleware('admin.permission:vouchers.view')->group(function () {
            Route::get('/', [AdminVoucherController::class, 'index'])->name('index');
            Route::match(['GET', 'POST'], '/datatable/data', [AdminVoucherController::class, 'datatable'])->name('datatable');
            Route::get('/{voucher}', [AdminVoucherController::class, 'show'])->name('show');
            Route::match(['GET', 'POST'], '/{voucher}/redemptions/data', [AdminVoucherController::class, 'redemptionsDatatable'])->name('redemptions.data');
            Route::get('/{voucher}/redemptions/export', [AdminVoucherController::class, 'exportRedemptions'])->name('export');
        });
        Route::middleware('admin.permission:vouchers.edit')->group(function () {
            Route::get('/{voucher}/edit', [AdminVoucherController::class, 'edit'])->name('edit');
            Route::put('/{voucher}', [AdminVoucherController::class, 'update'])->name('update');
            Route::patch('/{voucher}/toggle', [AdminVoucherController::class, 'toggle'])->name('toggle');
        });
        Route::delete('/{voucher}', [AdminVoucherController::class, 'destroy'])
            ->name('destroy')->middleware('admin.permission:vouchers.delete');
    });

    // ─── Cart Card Offers ──────────────────────────────────────────────────────────
    Route::prefix('cart-card-offers')->name('cart-card-offers.')->middleware('admin.permission:cart_card_offers.view')->group(function () {
        Route::get('/create', [CartCardOfferController::class, 'create'])->name('create');
        Route::post('/datatable', [CartCardOfferController::class, 'datatable'])->name('datatable');
        Route::get('/', [CartCardOfferController::class, 'index'])->name('index');
        Route::post('/', [CartCardOfferController::class, 'store'])->name('store');
        Route::get('/{cartCardOffer}/edit', [CartCardOfferController::class, 'edit'])->name('edit');
        Route::put('/{cartCardOffer}', [CartCardOfferController::class, 'update'])->name('update');
        Route::delete('/{cartCardOffer}', [CartCardOfferController::class, 'destroy'])->name('destroy');
    });

    // ─── Gift Cards ──────────────────────────────────────────────────────────────
    Route::prefix('gift-cards')->name('gift-cards.')->middleware('admin.permission:gift_cards.view')->group(function () {
        Route::get('/batches/create', [AdminGiftCardController::class, 'batchCreate'])->middleware('admin.permission:gift_cards.create')->name('batches.create');
        Route::post('/batches', [AdminGiftCardController::class, 'batchStore'])->middleware('admin.permission:gift_cards.create')->name('batches.store');
        Route::post('/batches/{batch}/datatable', [AdminGiftCardController::class, 'batchDatatable'])->name('batches.datatable');
        Route::post('/batches/{batch}/purchases/datatable', [AdminGiftCardController::class, 'purchasesDatatable'])->name('batches.purchases.datatable');
        Route::patch('/purchases/{purchase}/resend', [AdminGiftCardController::class, 'resendDelivery'])->middleware('admin.permission:gift_cards.edit')->name('purchases.resend');
        Route::post('/batches/{batch}/activate', [AdminGiftCardController::class, 'activateBatch'])->middleware('admin.permission:gift_cards.edit')->name('batches.activate');
        Route::get('/batches/{batch}/download-pins', [AdminGiftCardController::class, 'downloadPins'])->name('batches.download_pins');
        Route::get('/batches/{batch}/edit', [AdminGiftCardController::class, 'batchEdit'])->middleware('admin.permission:gift_cards.edit')->name('batches.edit');
        Route::put('/batches/{batch}', [AdminGiftCardController::class, 'batchUpdate'])->middleware('admin.permission:gift_cards.edit')->name('batches.update');
        Route::get('/batches/{batch}', [AdminGiftCardController::class, 'batchShow'])->name('batches.show');
        Route::get('/batches', [AdminGiftCardController::class, 'batchIndex'])->name('batches.index');
        Route::post('/expire-stale', [AdminGiftCardController::class, 'expireStale'])->middleware('admin.permission:gift_cards.edit')->name('expire-stale');
        Route::post('/{card}/activate', [AdminGiftCardController::class, 'activateCard'])->middleware('admin.permission:gift_cards.edit')->name('activate');
        Route::post('/{card}/adjust', [AdminGiftCardController::class, 'adjustBalance'])->middleware('admin.permission:gift_cards.edit')->name('adjust');
        Route::post('/{card}/block', [AdminGiftCardController::class, 'blockCard'])->middleware('admin.permission:gift_cards.edit')->name('block');
        Route::get('/{card}', [AdminGiftCardController::class, 'showCard'])->name('show');
        Route::post('/datatable', [AdminGiftCardController::class, 'datatable'])->name('datatable');
        Route::get('/', [AdminGiftCardController::class, 'cardIndex'])->name('index');
    });

    // ─── Support Tickets ──────────────────────────────────────────────────────────
    Route::prefix('support-tickets')->name('support-tickets.')->middleware('admin.permission:support.view')->group(function () {
        Route::post('/datatable', [SupportTicketController::class, 'datatable'])->name('datatable');
        Route::get('/', [SupportTicketController::class, 'index'])->name('index');
        Route::get('/{ticket}', [SupportTicketController::class, 'show'])->name('show');
        Route::post('/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('reply');
        Route::post('/{ticket}/assign', [SupportTicketController::class, 'assign'])->name('assign');
        Route::post('/{ticket}/assign-me', [SupportTicketController::class, 'assignMe'])->name('assign-me');
        Route::post('/{ticket}/update-status', [SupportTicketController::class, 'updateStatus'])->name('update-status');
        Route::post('/{ticket}/update-priority', [SupportTicketController::class, 'updatePriority'])->name('update-priority');
    });

    // ─── Disputes ─────────────────────────────────────────────────────────────────
    Route::prefix('disputes')->name('disputes.')->middleware('admin.permission:disputes.view')->group(function () {
        Route::post('/datatable', [DisputeController::class, 'datatable'])->name('datatable');
        Route::get('/', [DisputeController::class, 'index'])->name('index');
        Route::get('/{dispute}', [DisputeController::class, 'show'])->name('show');
        Route::post('/{dispute}/reply', [DisputeController::class, 'reply'])->name('reply');
        Route::post('/{dispute}/assign', [DisputeController::class, 'assign'])->name('assign');
        Route::post('/{dispute}/assign-me', [DisputeController::class, 'assignMe'])->name('assign-me');
        Route::post('/{dispute}/update-status', [DisputeController::class, 'updateStatus'])->name('update-status');
        Route::post('/{dispute}/resolve', [DisputeController::class, 'resolve'])->name('resolve');
    });

    // ─── Returns ─────────────────────────────────────────────────────────────────
    Route::prefix('returns')->name('returns.')->middleware('admin.permission:returns.view')->group(function () {
        Route::post('/datatable', [ReturnController::class, 'datatable'])->name('datatable');
        Route::get('/', [ReturnController::class, 'index'])->name('index');
        Route::get('/{returnRequest}', [ReturnController::class, 'show'])->name('show');
        Route::post('/{returnRequest}/approve', [ReturnController::class, 'approve'])->name('approve');
        Route::post('/{returnRequest}/schedule-pickup', [ReturnController::class, 'schedulePickup'])->name('schedule-pickup');
        Route::post('/{returnRequest}/mark-received', [ReturnController::class, 'markReceived'])->name('mark-received');
        Route::post('/{returnRequest}/inspect', [ReturnController::class, 'inspect'])->name('inspect');
        Route::post('/{returnRequest}/reject', [ReturnController::class, 'reject'])->name('reject');
    });

    // ─── Warranty Claims ─────────────────────────────────────────────────────────
    Route::prefix('warranty-claims')->name('warranty-claims.')->middleware('admin.permission:warranty_claims.view')->group(function () {
        Route::post('/datatable', [WarrantyClaimController::class, 'datatable'])->name('datatable');
        Route::get('/', [WarrantyClaimController::class, 'index'])->name('index');
        Route::get('/{claim}', [WarrantyClaimController::class, 'show'])->name('show');
        Route::post('/{claim}/status', [WarrantyClaimController::class, 'updateStatus'])->name('status');
        Route::post('/{claim}/resolve', [WarrantyClaimController::class, 'resolve'])->name('resolve');
        Route::post('/{claim}/messages', [WarrantyClaimController::class, 'addMessage'])->name('messages');
    });

    // ─── Warranty Purchases ─────────────────────────────────────────────────────
    Route::prefix('warranty-purchases')->name('warranty-purchases.')->middleware('admin.permission:warranty_plans.view')->group(function () {
        Route::post('/datatable', [WarrantyPurchaseController::class, 'datatableData'])->name('datatable');
        Route::get('/summary', [WarrantyPurchaseController::class, 'summary'])->name('summary');
        Route::get('/', [WarrantyPurchaseController::class, 'index'])->name('index');
        Route::get('/{warrantyPurchase}', [WarrantyPurchaseController::class, 'show'])->name('show');
    });

    // ─── Stop Impersonating (no extra permission required, just auth) ─────────────
    Route::post('/admins/stop-impersonating', [AdminController::class, 'stopImpersonating'])
        ->name('admins.stop-impersonating');

    // ─── Admins ───────────────────────────────────────────────────────────────────
    Route::prefix('admins')->name('admins.')->middleware('admin.permission:admins.view')->group(function () {
        Route::get('/search', [AdminController::class, 'search'])->name('search');
        Route::get('/create', [AdminController::class, 'create'])->name('create');
        Route::post('/datatable', [AdminController::class, 'datatable'])->name('datatable');
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::post('/', [AdminController::class, 'store'])->name('store');
        Route::get('/{admin}/edit', [AdminController::class, 'edit'])->name('edit');
        Route::get('/{admin}/login-sessions', [AdminController::class, 'loginSessions'])->name('login-sessions');
        Route::post('/{admin}/reset-password', [AdminController::class, 'resetPassword'])->name('reset-password');
        Route::post('/{admin}/impersonate', [AdminController::class, 'impersonate'])->name('impersonate');
        Route::post('/{admin}/toggle-status', [AdminController::class, 'toggleStatus'])->name('toggle-status');
        Route::put('/{admin}', [AdminController::class, 'update'])->name('update');
        Route::delete('/{admin}', [AdminController::class, 'destroy'])->name('destroy');
    });

    // ─── Roles ────────────────────────────────────────────────────────────────────
    Route::prefix('roles')->name('roles.')->middleware('admin.permission:roles.view')->group(function () {
        Route::get('/permissions', [RoleController::class, 'permissions'])->name('permissions');
        Route::get('/create', [RoleController::class, 'create'])->name('create');
        Route::post('/datatable', [RoleController::class, 'datatable'])->name('datatable');
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('edit');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    });


    // ─── Customers ───────────────────────────────────────────────────────────────
    Route::prefix('customers')->name('customers.')->middleware('admin.permission:customers.view')->group(function () {
        Route::post('/datatable', [CustomerController::class, 'datatable'])->name('datatable');
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/{customer}/export', [CustomerController::class, 'exportData'])->name('export');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');
        Route::post('/{customer}/suspend', [CustomerController::class, 'suspend'])->name('suspend');
        Route::post('/{customer}/ban', [CustomerController::class, 'ban'])->name('ban');
        Route::post('/{customer}/reactivate', [CustomerController::class, 'reactivate'])->name('reactivate');
        Route::post('/{customer}/adjust-loyalty', [CustomerController::class, 'adjustLoyaltyPoints'])->name('adjust-loyalty');
        Route::post('/{customer}/orders/datatable', [CustomerController::class, 'orders'])->name('orders.datatable');
        Route::post('/{customer}/send-notification', [CustomerController::class, 'sendNotification'])->name('send-notification');
        Route::post('/{customer}/regenerate-qr', [CustomerController::class, 'regenerateQrCode'])->name('regenerate-qr');
        Route::get('/{customer}/notifications', [AdminNotificationController::class, 'customerNotifications'])->name('notifications')
            ->middleware('admin.permission:notifications.view');
        Route::post('/{customer}/devices/revoke-all', [CustomerController::class, 'revokeAllDevices'])->name('devices.revoke-all')
            ->middleware('admin.permission:customers.edit');
        Route::post('/{customer}/wallet/transactions/datatable', [CustomerController::class, 'walletTransactions'])->name('wallet.transactions.datatable');
        Route::post('/{customer}/wallet/adjust', [CustomerController::class, 'adjustWallet'])->name('wallet.adjust')
            ->middleware('admin.permission:wallet.manual_adjust');
        Route::post('/{customer}/referrals/datatable', [CustomerController::class, 'referralsDatatable'])->name('referrals.datatable');
    });

    // ─── Wishlist Overview (read-only) ────────────────────────────────────────
    Route::prefix('wishlist')->name('wishlist.')->middleware('admin.permission:wishlists.view')->group(function () {
        Route::post('/datatable', [WishlistOverviewController::class, 'datatable'])->name('datatable');
        Route::get('/', [WishlistOverviewController::class, 'index'])->name('index');
        Route::get('/groups/{groupId}', [WishlistOverviewController::class, 'show'])->name('show');
    });

    // ─── Notification Management (platform-wide) ─────────────────────────────
    // NOTE: prefixed "notification-management" rather than "notifications" to avoid
    // colliding with the shared per-admin bell-dropdown routes registered above
    // (admin.notifications.index/recent/unread/...).
    Route::prefix('notification-management')->name('notification-management.')
        ->middleware('admin.permission:notifications.view')
        ->group(function () {
            Route::get('/', [AdminNotificationController::class, 'index'])->name('index');
            Route::post('/datatable', [AdminNotificationController::class, 'datatable'])->name('datatable');
            Route::get('/send', [AdminNotificationController::class, 'create'])->name('send')
                ->middleware('admin.permission:notifications.send');
            Route::post('/send', [AdminNotificationController::class, 'sendManual'])->name('send.store')
                ->middleware('admin.permission:notifications.send');
        });

    // ─── Banners ──────────────────────────────────────────────────────────────────
    Route::prefix('banners')->name('banners.')->middleware('admin.permission:banners.view')->group(function () {
        Route::post('/datatable', [BannerController::class, 'datatable'])->name('datatable');
        Route::get('/placements', [BannerController::class, 'placements'])->name('placements');
        Route::get('/create', [BannerController::class, 'create'])->name('create');
        Route::post('/', [BannerController::class, 'store'])->name('store');
        Route::get('/', [BannerController::class, 'index'])->name('index');
        Route::get('/{banner}/edit', [BannerController::class, 'edit'])->name('edit');
        Route::put('/{banner}', [BannerController::class, 'update'])->name('update');
        Route::delete('/{banner}', [BannerController::class, 'destroy'])->name('destroy');
        Route::post('/{banner}/duplicate', [BannerController::class, 'duplicate'])->name('duplicate');
        Route::post('/{banner}/upload-image', [BannerController::class, 'uploadImage'])->name('upload-image');
        Route::delete('/image', [BannerController::class, 'deleteImage'])->name('delete-image');
        Route::post('/bulk', [BannerController::class, 'bulk'])->name('bulk');
    });

    // ─── Ad Campaigns ──────────────────────────────────────────────────────────────
    Route::prefix('ad-campaigns')->name('ad-campaigns.')->middleware('admin.permission:ad_campaigns.view')->group(function () {
        Route::post('/datatable', [AdCampaignController::class, 'datatable'])->name('datatable');
        Route::get('/fraud', [AdCampaignController::class, 'fraudAlerts'])->name('fraud');
        Route::post('/fraud/datatable', [AdCampaignController::class, 'fraudDatatable'])->name('fraud.datatable');
        Route::post('/fraud/{pattern}/block', [AdCampaignController::class, 'blockFraudPattern'])->name('fraud.block');
        Route::get('/listings/vendor-search/{campaign}', [AdCampaignController::class, 'searchVendorListings'])->name('listings.vendor-search');
        Route::get('/listings/admin-search', [AdCampaignController::class, 'searchAdminListings'])->name('listings.admin-search');
        Route::get('/', [AdCampaignController::class, 'index'])->name('index');
        Route::get('/{campaign}', [AdCampaignController::class, 'show'])->name('show');
        Route::post('/{campaign}/approve', [AdCampaignController::class, 'approve'])->name('approve');
        Route::post('/{campaign}/reject', [AdCampaignController::class, 'reject'])->name('reject');
        Route::post('/{campaign}/pause', [AdCampaignController::class, 'pauseCampaign'])->name('pause');
        Route::post('/{campaign}/resume', [AdCampaignController::class, 'resumeCampaign'])->name('resume');
        Route::post('/{campaign}/products/datatable', [AdCampaignController::class, 'productsDatatable'])->name('products.datatable');
        Route::post('/{campaign}/products', [AdCampaignController::class, 'storeProduct'])->name('products.store');
        Route::delete('/{campaign}/products/{product}', [AdCampaignController::class, 'destroyProduct'])->name('products.destroy');
    });

    // ─── Marketer Campaigns ────────────────────────────────────────────────────────
    Route::prefix('marketer-campaigns')->name('marketer-campaigns.')->middleware('admin.permission:marketer_campaigns.view')->group(function () {
        Route::get('/', [MarketerCampaignController::class, 'index'])->name('index');
        Route::get('/financials', [MarketerCampaignController::class, 'financials'])->name('financials');
        Route::get('/{marketerCampaign}', [MarketerCampaignController::class, 'show'])->name('show');
        Route::post('/{marketerCampaign}/approve', [MarketerCampaignController::class, 'approve'])->name('approve');
        Route::post('/{marketerCampaign}/reject', [MarketerCampaignController::class, 'reject'])->name('reject');
        Route::patch('/{marketerCampaign}/samples/{sample}', [MarketerCampaignController::class, 'updateSampleStatus'])->name('samples.update');
        Route::patch('/{marketerCampaign}/invitations/{invitation}/mark-fee-paid', [MarketerCampaignController::class, 'markInvitationFeePaid'])->name('invitations.mark-fee-paid');
    });

    // ─── Marketer Settings (Commission & Fees) ────────────────────────────────────
    Route::prefix('marketer-settings')->name('marketer-settings.')->middleware('admin.permission:marketer_commission_settings.view')->group(function () {
        Route::get('/', [MarketerSettingsController::class, 'index'])->name('index');
        Route::post('/fee', [MarketerSettingsController::class, 'updateInfluencerFee'])->name('update-fee');
    });


    // ─── Ad Slots ──────────────────────────────────────────────────────────────────
    Route::prefix('ad-slots')->name('ad-slots.')->middleware('admin.permission:ad_campaigns.view')->group(function () {
        Route::post('/datatable', [AdSlotController::class, 'datatable'])->name('datatable');
        Route::get('/create', [AdSlotController::class, 'create'])->name('create');
        Route::post('/', [AdSlotController::class, 'store'])->name('store');
        Route::get('/', [AdSlotController::class, 'index'])->name('index');
        Route::get('/{adSlot}/bookings', [AdSlotController::class, 'bookings'])->name('bookings');
        Route::get('/{adSlot}/edit', [AdSlotController::class, 'edit'])->name('edit');
        Route::put('/{adSlot}', [AdSlotController::class, 'update'])->name('update');
        Route::delete('/{adSlot}', [AdSlotController::class, 'destroy'])->name('destroy');
    });

    // ─── Paid Ad Bookings ──────────────────────────────────────────────────────────
    Route::prefix('paid-ad-bookings')->name('paid-ad-bookings.')->middleware('admin.permission:ad_campaigns.view')->group(function () {
        Route::post('/datatable', [PaidAdBookingController::class, 'datatable'])->name('datatable');
        Route::post('/creatives/{paidAdCreative}/review', [PaidAdBookingController::class, 'reviewCreative'])->name('creatives.review');
        Route::get('/', [PaidAdBookingController::class, 'index'])->name('index');
        Route::get('/{paidAdBooking}', [PaidAdBookingController::class, 'show'])->name('show');
        Route::post('/{paidAdBooking}/approve', [PaidAdBookingController::class, 'approve'])->name('approve');
        Route::post('/{paidAdBooking}/reject', [PaidAdBookingController::class, 'reject'])->name('reject');
    });

    // ─── Vendor Applications Queue ────────────────────────────────────────────────
    Route::prefix('vendor-applications')->name('vendor-applications.')->middleware('admin.permission:vendors.view')->group(function () {
        Route::post('/datatable', [VendorApplicationController::class, 'datatable'])->name('datatable');
        Route::post('/documents/{document}/verify', [VendorApplicationController::class, 'verifyDocument'])->name('documents.verify');
        Route::post('/documents/{document}/reject', [VendorApplicationController::class, 'rejectDocument'])->name('documents.reject');
        Route::get('/', [VendorApplicationController::class, 'index'])->name('index');
        Route::get('/{vendor}', [VendorApplicationController::class, 'show'])->name('show');
        Route::post('/{vendor}/start-review', [VendorApplicationController::class, 'startReview'])->name('start-review');
        Route::post('/{vendor}/assign-me', [VendorApplicationController::class, 'assignMe'])->name('assign-me');
        Route::post('/{vendor}/approve', [VendorApplicationController::class, 'approve'])->name('approve');
        Route::post('/{vendor}/reject', [VendorApplicationController::class, 'reject'])->name('reject');
        Route::post('/{vendor}/request-info', [VendorApplicationController::class, 'requestMoreInfo'])->name('request-info');
    });

    // ─── Reviews ──────────────────────────────────────────────────────────────────
    Route::prefix('reviews')->name('reviews.')->middleware('admin.permission:reviews.view')->group(function () {
        Route::post('/datatable', [ReviewController::class, 'datatable'])->name('datatable');
        Route::post('/bulk-action', [ReviewController::class, 'bulkAction'])->name('bulk-action');
        Route::post('/vendor-replies/{reply}/hide', [ReviewController::class, 'hideVendorReply'])->name('vendor-replies.hide');
        Route::post('/vendor-replies/{reply}/show', [ReviewController::class, 'showVendorReply'])->name('vendor-replies.show');
        Route::get('/', [ReviewController::class, 'index'])->name('index');
        Route::get('/{review}', [ReviewController::class, 'show'])->name('show');
        Route::post('/{review}/approve', [ReviewController::class, 'approve'])->name('approve');
        Route::post('/{review}/reject', [ReviewController::class, 'reject'])->name('reject');
        Route::delete('/{review}', [ReviewController::class, 'delete'])->name('delete');
    });

    // ─── Transactions & Finance ───────────────────────────────────────────────────
    Route::prefix('transactions')->name('transactions.')->middleware('admin.permission:transactions.view')->group(function () {
        Route::post('/datatable', [TransactionController::class, 'datatable'])->name('datatable');
        // Refund sub-routes BEFORE the /{transaction} wildcard
        Route::get('/refunds', [TransactionController::class, 'refundIndex'])->name('refunds.index');
        Route::post('/refunds/datatable', [TransactionController::class, 'refundDatatable'])->name('refunds.datatable');
        Route::post('/refunds/{refund}/approve', [TransactionController::class, 'approveRefund'])->name('refunds.approve');
        Route::post('/refunds/{refund}/reject', [TransactionController::class, 'rejectRefund'])->name('refunds.reject');
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::get('/{transaction}', [TransactionController::class, 'show'])->name('show');
    });

    // ─── Ledger ───────────────────────────────────────────────────────────────────
    Route::prefix('ledger')->name('ledger.')->middleware('admin.permission:ledger.view')->group(function () {
        Route::post('/datatable', [LedgerController::class, 'datatable'])->name('datatable');
        Route::get('/transaction-group/{groupId}', [LedgerController::class, 'transactionGroup'])->name('transaction-group');
        Route::get('/', [LedgerController::class, 'index'])->name('index');
    });

    // ─── Settings ─────────────────────────────────────────────────────────────
    Route::prefix('settings')->name('settings.')->middleware('admin.permission:settings.view')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::get('/group/{category}', [SettingsController::class, 'getGroup'])->name('group');
        Route::post('/group/{category}', [SettingsController::class, 'saveGroup'])->name('save')->middleware('admin.permission:settings.edit');
        Route::post('/reset', [SettingsController::class, 'reset'])->name('reset')->middleware('admin.permission:settings.edit');
        Route::post('/test-gateway', [SettingsController::class, 'testGateway'])->name('test-gateway');
        Route::post('/clear-cache', [SettingsController::class, 'clearCache'])->name('clear-cache')->middleware('admin.permission:settings.edit');
    });

    // ─── Newsletter ───────────────────────────────────────────────────────────
    Route::prefix('newsletter')->name('newsletter.')->middleware('admin.permission:settings.view')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\NewsletterController::class, 'index'])->name('index');
        Route::post('/datatable', [\App\Http\Controllers\Admin\NewsletterController::class, 'datatable'])->name('datatable');
        Route::get('/export', [\App\Http\Controllers\Admin\NewsletterController::class, 'export'])->name('export');
        Route::delete('/{subscriber}', [\App\Http\Controllers\Admin\NewsletterController::class, 'destroy'])->name('destroy');
    });

    // ─── Content Settings ─────────────────────────────────────────────────────
    Route::prefix('content-settings')->name('content-settings.')->middleware('admin.permission:settings.content')->group(function () {
        Route::get('/', [ContentSettingsController::class, 'index'])->name('index');
        Route::get('/{group}', [ContentSettingsController::class, 'showGroup'])->name('group');
        Route::post('/update', [ContentSettingsController::class, 'update'])->name('update');
    });

    // ─── Portal Content ───────────────────────────────────────────────────────
    Route::prefix('portal-content')->name('portal-content.')->middleware('admin.permission:portal_content.view')->group(function () {
        Route::get('/', [PortalContentController::class, 'index'])->name('index');
        Route::get('/{pageKey}', [PortalContentController::class, 'page'])->name('page');
        Route::post('/{pageKey}', [PortalContentController::class, 'save'])->name('save')->middleware('admin.permission:portal_content.edit');
    });

    // ─── FAQs ─────────────────────────────────────────────────────────────────
    Route::prefix('faqs')->name('faqs.')->middleware('admin.permission:faqs.view')->group(function () {
        Route::post('/reorder', [FaqController::class, 'reorder'])->name('reorder');
        Route::get('/', [FaqController::class, 'index'])->name('index');
        Route::post('/', [FaqController::class, 'store'])->name('store')->middleware('admin.permission:faqs.create');
        Route::put('/{faq}', [FaqController::class, 'update'])->name('update')->middleware('admin.permission:faqs.edit');
        Route::delete('/{faq}', [FaqController::class, 'destroy'])->name('destroy')->middleware('admin.permission:faqs.delete');
        Route::post('/{faq}/toggle', [FaqController::class, 'toggleActive'])->name('toggle')->middleware('admin.permission:faqs.edit');
    });

    // ─── Activity Log ─────────────────────────────────────────────────────────
    Route::prefix('activity-log')->name('activity-log.')->middleware('admin.permission:activity-log.view')->group(function () {
        Route::post('/datatable', [ActivityLogController::class, 'datatable'])->name('datatable');
        Route::get('/causer-search', [ActivityLogController::class, 'causerSearch'])->name('causer-search');
        Route::get('/', [ActivityLogController::class, 'index'])->name('index');
        Route::get('/{id}', [ActivityLogController::class, 'show'])->name('show');
    });

    // ─── Shipping Zones ───────────────────────────────────────────────────────
    Route::prefix('shipping-zones')->name('shipping-zones.')->middleware('admin.permission:settings.view')->group(function () {
        // Zone index + datatable
        Route::get('/', [ShippingZoneController::class, 'index'])->name('index');
        Route::post('/datatable', [ShippingZoneController::class, 'datatable'])->name('datatable');

        // Rates endpoints (specific routes BEFORE /{zone} wildcard)
        Route::post('/rates/datatable', [ShippingZoneController::class, 'getRates'])->name('rates.datatable');
        Route::post('/rates/estimate', [ShippingZoneController::class, 'calculateEstimate'])->name('rates.estimate');
        Route::middleware('admin.permission:settings.edit')->group(function () {
            Route::post('/rates/bulk', [ShippingZoneController::class, 'bulkRates'])->name('rates.bulk');
            Route::post('/rates/copy', [ShippingZoneController::class, 'copyRates'])->name('rates.copy');
            Route::post('/rates', [ShippingZoneController::class, 'storeRate'])->name('rates.store');
            Route::put('/rates/{rate}', [ShippingZoneController::class, 'updateRate'])->name('rates.update');
            Route::delete('/rates/{rate}', [ShippingZoneController::class, 'destroyRate'])->name('rates.destroy');
            Route::post('/rates/{rate}/toggle', [ShippingZoneController::class, 'toggleRate'])->name('rates.toggle');
        });

        // City endpoints (specific before /{zone} wildcard)
        Route::get('/cities/unassigned', [ShippingZoneController::class, 'getUnassigned'])->name('cities.unassigned');
        Route::post('/cities/unassign', [ShippingZoneController::class, 'unassignCity'])->name('cities.unassign')
            ->middleware('admin.permission:settings.edit');

        // Zone show
        Route::get('/{zone}', [ShippingZoneController::class, 'show'])->name('show');

        // Zone CRUD (write operations)
        Route::middleware('admin.permission:settings.edit')->group(function () {
            Route::post('/', [ShippingZoneController::class, 'store'])->name('store');
            Route::put('/{zone}', [ShippingZoneController::class, 'update'])->name('update');
            Route::delete('/{zone}', [ShippingZoneController::class, 'destroy'])->name('destroy');
            Route::post('/{zone}/toggle', [ShippingZoneController::class, 'toggleActive'])->name('toggle');
            Route::post('/{zone}/duplicate', [ShippingZoneController::class, 'duplicate'])->name('duplicate');
        });

        // City assignment per zone
        Route::get('/{zone}/cities', [ShippingZoneController::class, 'getCities'])->name('cities');
        Route::post('/{zone}/cities', [ShippingZoneController::class, 'assignCities'])->name('cities.assign')
            ->middleware('admin.permission:settings.edit');
    });

    // ─── Platform Shipping Subsidies ───────────────────────────────────────────
    Route::prefix('shipping-subsidies')->name('shipping-subsidies.')->middleware('admin.permission:settings.view')->group(function () {
        Route::get('/', [ShippingSubsidyController::class, 'index'])->name('index');

        Route::middleware('admin.permission:settings.edit')->group(function () {
            Route::post('/', [ShippingSubsidyController::class, 'store'])->name('store');
            Route::put('/{subsidy}', [ShippingSubsidyController::class, 'update'])->name('update');
            Route::delete('/{subsidy}', [ShippingSubsidyController::class, 'destroy'])->name('destroy');
        });

        Route::get('/vendor-alerts', [ShippingSubsidyController::class, 'vendorAlerts'])->name('alerts.index');
        Route::middleware('admin.permission:settings.edit')->group(function () {
            Route::post('/vendor-alerts/{alert}/accept', [ShippingSubsidyController::class, 'acceptAlert'])->name('alerts.accept');
            Route::post('/vendor-alerts/{alert}/reject', [ShippingSubsidyController::class, 'rejectAlert'])->name('alerts.reject');
        });
    });

    // ─── Warehouses ───────────────────────────────────────────────────────────
    Route::prefix('warehouses')->name('warehouses.')->middleware('admin.permission:warehouses.view')->group(function () {
        // Index + datatable
        Route::get('/', [WarehouseController::class, 'index'])->name('index');
        Route::post('/datatable', [WarehouseController::class, 'datatable'])->name('datatable');

        // Create / Store
        Route::get('/create', [WarehouseController::class, 'create'])->name('create');
        Route::post('/', [WarehouseController::class, 'store'])->name('store');

        // Shipping surcharges (must be before /{warehouse} so 'shipping-surcharges' is not treated as a UUID)
        // Applies to FBN-fulfilled listings shipped from a warehouse.
        Route::prefix('shipping-surcharges')->name('shipping-surcharges.')
            ->controller(WarehouseShippingSurchargeController::class)
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('/{surcharge}', 'update')->name('update');
                Route::post('/{surcharge}/toggle-active', 'toggleActive')->name('toggle-active');
            });

        // Transfers (must be before /{warehouse} so 'transfers' is not treated as a UUID)
        Route::prefix('transfers')->name('transfers.')->group(function () {
            Route::get('/', [WarehouseController::class, 'transfersIndex'])->name('index');
            Route::post('/datatable', [WarehouseController::class, 'transfersDatatable'])->name('datatable');
            Route::get('/create', [WarehouseController::class, 'transferCreate'])->name('create');
            Route::post('/', [WarehouseController::class, 'transferStore'])->name('store');
            Route::get('/{transfer}', [WarehouseController::class, 'transferShow'])->name('show');
            Route::post('/{transfer}/ship', [WarehouseController::class, 'transferShip'])->name('ship');
            Route::post('/{transfer}/receive', [WarehouseController::class, 'transferReceive'])->name('receive');
            Route::post('/{transfer}/cancel', [WarehouseController::class, 'transferCancel'])->name('cancel');
        });

        // Single warehouse
        Route::get('/{warehouse}', [WarehouseController::class, 'show'])->name('show');
        Route::get('/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('edit');
        Route::put('/{warehouse}', [WarehouseController::class, 'update'])->name('update');
        Route::post('/{warehouse}/toggle-active', [WarehouseController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/{warehouse}/exceptional-zones/{zone}/toggle', [WarehouseController::class, 'toggleExceptionalZone'])->name('exceptional-zones.toggle');

        // Inventory endpoints
        Route::post('/{warehouse}/inventory/datatable', [WarehouseController::class, 'inventoryDatatable'])->name('inventory.datatable');
        Route::post('/{warehouse}/inventory/{inventory}/adjust', [WarehouseController::class, 'adjustInventory'])->name('inventory.adjust');
        Route::get('/{warehouse}/inventory/{inventory}/movements', [WarehouseController::class, 'movements'])->name('inventory.movements');

        // Daily overage fees report (platform FBN warehouses)
        Route::post('/{warehouse}/overage-fees/datatable', [WarehouseController::class, 'overageFeesDatatable'])->name('overage-fees.datatable');

        // Vendor storage limits (platform FBN warehouses only)
        Route::post('/{warehouse}/vendor-limits', [WarehouseController::class, 'storeVendorLimit'])->name('vendor-limits.store');
        Route::delete('/{warehouse}/vendor-limits/{limit}', [WarehouseController::class, 'destroyVendorLimit'])->name('vendor-limits.destroy');
        Route::post('/{warehouse}/vendor-limits/apply-default', [WarehouseController::class, 'applyDefaultLimitToAllVendors'])->name('vendor-limits.apply-default');
    });

    // ─── Analytics ───────────────────────────────────────────────────────────────
    // ─── Financial Reports ────────────────────────────────────────────────────
    Route::prefix('reports/financial')->name('reports.financial.')->middleware('admin.permission:analytics.view')->group(function () {
        Route::get('/', [FinancialReportController::class, 'index'])->name('index');
        Route::get('/data', [FinancialReportController::class, 'data'])->name('data');
        Route::get('/export', [FinancialReportController::class, 'export'])->name('export');
    });

    Route::prefix('analytics')->name('analytics.')->middleware('admin.permission:analytics.view')->group(function () {
        Route::get('/', [AnalyticsController::class, 'index'])->name('index');
        Route::get('/overview', [AnalyticsController::class, 'overview'])->name('overview');
        Route::get('/revenue-chart', [AnalyticsController::class, 'revenueChart'])->name('revenue-chart');
        Route::get('/orders-by-status', [AnalyticsController::class, 'ordersByStatus'])->name('orders-by-status');
        Route::get('/orders-by-payment', [AnalyticsController::class, 'ordersByPaymentMethod'])->name('orders-by-payment');
        Route::get('/top-products', [AnalyticsController::class, 'topProducts'])->name('top-products');
        Route::get('/top-vendors', [AnalyticsController::class, 'topVendors'])->name('top-vendors');
        Route::get('/top-categories', [AnalyticsController::class, 'topCategories'])->name('top-categories');
        Route::get('/customers', [AnalyticsController::class, 'customerStats'])->name('customers');
        Route::get('/search', [AnalyticsController::class, 'searchAnalytics'])->name('search');
        Route::get('/products', [AnalyticsController::class, 'productAnalytics'])->name('products');
        Route::get('/sla', [AnalyticsController::class, 'slaMetrics'])->name('sla');
        Route::get('/ads', [AnalyticsController::class, 'adPerformance'])->name('ads');
        Route::get('/flash-sales', [AnalyticsController::class, 'flashSaleAnalytics'])->name('flash-sales');
        Route::get('/returns', [AnalyticsController::class, 'returnAnalytics'])->name('returns');
        Route::get('/support', [AnalyticsController::class, 'supportMetrics'])->name('support');
    });

    // ─── Payment Gateways (DB-credential Strategy Pattern) ───────────────────
    Route::prefix('payment-gateways')
        ->name('payment-gateways.')
        ->middleware('admin.permission:settings.view')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'store'])->name('store')->middleware('admin.permission:settings.edit');
            Route::put('/{countryGateway}', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'update'])->name('update')->middleware('admin.permission:settings.edit');
            Route::delete('/{countryGateway}', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'destroy'])->name('destroy')->middleware('admin.permission:settings.edit');
            Route::post('/{countryGateway}/toggle', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'toggleActive'])->name('toggle')->middleware('admin.permission:settings.edit');
            Route::post('/{countryGateway}/test-connection', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'testConnection'])->name('test-connection')->middleware('admin.permission:settings.edit');
            Route::get('/{countryGateway}/webhook-logs', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'webhookLogs'])->name('webhook-logs');
            Route::post('/sort-order', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'updateSortOrder'])->name('sort-order')->middleware('admin.permission:settings.edit');
            Route::post('/gateways/{gateway}/image', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'uploadImage'])->name('gateways.upload-image')->middleware('admin.permission:settings.edit');
            Route::delete('/gateways/{gateway}/image', [\App\Http\Controllers\Admin\PaymentGatewayController::class, 'deleteImage'])->name('gateways.delete-image')->middleware('admin.permission:settings.edit');
        });

    // ─── Vendor Document Types ────────────────────────────────────────────────
    Route::prefix('vendor-document-types')->name('vendor-document-types.')->middleware('admin.permission:settings.view')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\VendorDocumentTypeController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\VendorDocumentTypeController::class, 'store'])->name('store')->middleware('admin.permission:settings.edit');
        Route::post('/requirements', [\App\Http\Controllers\Admin\VendorDocumentTypeController::class, 'updateRequirement'])->name('requirements.update')->middleware('admin.permission:settings.edit');
        Route::put('/{type}', [\App\Http\Controllers\Admin\VendorDocumentTypeController::class, 'update'])->name('update')->middleware('admin.permission:settings.edit');
        Route::delete('/{type}', [\App\Http\Controllers\Admin\VendorDocumentTypeController::class, 'destroy'])->name('destroy')->middleware('admin.permission:settings.edit');
        Route::post('/{type}/toggle', [\App\Http\Controllers\Admin\VendorDocumentTypeController::class, 'toggleActive'])->name('toggle')->middleware('admin.permission:settings.edit');
    });

    // ─── Shipping Methods (resource CRUD) ─────────────────────────────────────
    Route::prefix('shipping-methods')->name('shipping-methods.')->middleware('admin.permission:settings.view')->group(function () {
        Route::get('/', [ShippingMethodController::class, 'index'])->name('index');
        Route::get('/create', [ShippingMethodController::class, 'create'])->name('create')->middleware('admin.permission:settings.edit');
        Route::post('/', [ShippingMethodController::class, 'store'])->name('store')->middleware('admin.permission:settings.edit');
        Route::get('/{shipping_method}/edit', [ShippingMethodController::class, 'edit'])->name('edit')->middleware('admin.permission:settings.edit');
        Route::put('/{shipping_method}', [ShippingMethodController::class, 'update'])->name('update')->middleware('admin.permission:settings.edit');
        Route::delete('/{shipping_method}', [ShippingMethodController::class, 'destroy'])->name('destroy')->middleware('admin.permission:settings.edit');
        Route::post('/{shipping_method}/upload-badge-image', [ShippingMethodController::class, 'uploadBadgeImage'])->name('upload-badge-image')->middleware('admin.permission:settings.edit');
        Route::delete('/{shipping_method}/delete-badge-image', [ShippingMethodController::class, 'deleteBadgeImage'])->name('delete-badge-image')->middleware('admin.permission:settings.edit');
    });

    // ─── Shipping Settings: Carriers / Rates / Country Settings ───────────────
    Route::prefix('shipping-settings')->name('shipping-settings.')->middleware('admin.permission:settings.view')->group(function () {
        Route::get('/', [ShippingSettingController::class, 'index'])->name('index');

        // Carriers — test MUST come before {carrier} wildcard
        Route::post('/carriers/test', [ShippingSettingController::class, 'testCarrier'])->name('carriers.test');
        Route::post('/carriers', [ShippingSettingController::class, 'storeCarrier'])->name('carriers.store')->middleware('admin.permission:settings.edit');
        Route::put('/carriers/{carrier}', [ShippingSettingController::class, 'updateCarrier'])->name('carriers.update')->middleware('admin.permission:settings.edit');
        Route::post('/carriers/{carrier}/toggle', [ShippingSettingController::class, 'toggleCarrier'])->name('carriers.toggle')->middleware('admin.permission:settings.edit');

        // Rates — datatable + store MUST come before {rate} wildcard
        Route::post('/rates/datatable', [ShippingSettingController::class, 'ratesDatatable'])->name('rates.datatable');
        Route::post('/rates', [ShippingSettingController::class, 'storeRate'])->name('rates.store')->middleware('admin.permission:settings.edit');
        Route::put('/rates/{rate}', [ShippingSettingController::class, 'updateRate'])->name('rates.update')->middleware('admin.permission:settings.edit');
        Route::delete('/rates/{rate}', [ShippingSettingController::class, 'destroyRate'])->name('rates.destroy')->middleware('admin.permission:settings.edit');
        Route::post('/rates/{rate}/toggle', [ShippingSettingController::class, 'toggleRate'])->name('rates.toggle')->middleware('admin.permission:settings.edit');

        // Country Settings
        Route::post('/country-settings', [ShippingSettingController::class, 'upsertCountrySetting'])->name('country-settings.upsert')->middleware('admin.permission:settings.edit');
        Route::get('/country-settings', [ShippingSettingController::class, 'countrySettings'])->name('country-settings.index');
    });

    // ─── Shipping Weight Slabs ────────────────────────────────────────────────
    Route::prefix('shipping')->name('shipping.')->middleware('admin.permission:settings.view')->group(function () {
        Route::get('/weight-slabs', [ShippingWeightSlabController::class, 'index'])->name('weight-slabs.index');
        Route::post('/weight-slabs/datatable', [ShippingWeightSlabController::class, 'datatable'])->name('weight-slabs.datatable');
        Route::post('/weight-slabs', [ShippingWeightSlabController::class, 'store'])->name('weight-slabs.store')->middleware('admin.permission:settings.edit');
        Route::put('/weight-slabs/{slab}', [ShippingWeightSlabController::class, 'update'])->name('weight-slabs.update')->middleware('admin.permission:settings.edit');
        Route::delete('/weight-slabs/{slab}', [ShippingWeightSlabController::class, 'destroy'])->name('weight-slabs.destroy')->middleware('admin.permission:settings.edit');
    });

    // ─── Delivery ────────────────────────────────────────────────────────────
    Route::prefix('delivery')->name('delivery.')->group(function () {
        // Agents
        Route::get('/agents', [DeliveryAgentController::class, 'index'])->name('agents.index');
        Route::post('/agents', [DeliveryAgentController::class, 'store'])->name('agents.store');
        Route::post('/agents/datatable', [DeliveryAgentController::class, 'datatable'])->name('agents.datatable');
        Route::get('/agents/{agent}', [DeliveryAgentController::class, 'show'])->name('agents.show');
        Route::put('/agents/{agent}', [DeliveryAgentController::class, 'update'])->name('agents.update');
        Route::delete('/agents/{agent}', [DeliveryAgentController::class, 'destroy'])->name('agents.destroy');
        Route::post('/agents/{agent}/suspend', [DeliveryAgentController::class, 'suspend'])->name('agents.suspend');
        Route::post('/agents/{agent}/activate', [DeliveryAgentController::class, 'activate'])->name('agents.activate');
        Route::post('/agents/{agent}/reset-password', [DeliveryAgentController::class, 'resetPassword'])->name('agents.reset-password');
        Route::post('/agents/{agent}/assign-zone', [DeliveryAgentController::class, 'assignToZone'])->name('agents.assign-zone');
        Route::post('/agents/{agent}/assignments/datatable', [DeliveryAgentController::class, 'assignmentsDatatable'])->name('agents.assignments.datatable');
        Route::get('/agents/{agent}/earnings-summary', [DeliveryAgentController::class, 'earningsSummary'])->name('agents.earnings-summary');
        // Documents
        Route::post('/documents/{doc}/verify', [DeliveryAgentController::class, 'verifyDocument'])->name('documents.verify');
        Route::post('/documents/{doc}/reject', [DeliveryAgentController::class, 'rejectDocument'])->name('documents.reject');
        // Zones
        Route::get('/zones', [DeliveryZoneController::class, 'index'])->name('zones.index');
        Route::post('/zones', [DeliveryZoneController::class, 'store'])->name('zones.store');
        Route::get('/zones/live-map', [DeliveryZoneController::class, 'getAgentMap'])->name('zones.live-map');
        Route::get('/zones/{zone}', [DeliveryZoneController::class, 'show'])->name('zones.show');
        Route::put('/zones/{zone}', [DeliveryZoneController::class, 'update'])->name('zones.update');
        Route::delete('/zones/{zone}', [DeliveryZoneController::class, 'destroy'])->name('zones.destroy');
        Route::post('/zones/{zone}/assign-agents', [DeliveryZoneController::class, 'assignAgents'])->name('zones.assign-agents');
        Route::get('/zones/{zone}/agent-map', [DeliveryZoneController::class, 'getAgentMap'])->name('zones.agent-map');
        // Assignments
        Route::get('/assignments', [DeliveryAssignmentController::class, 'index'])->name('assignments.index');
        Route::post('/assignments/datatable', [DeliveryAssignmentController::class, 'datatable'])->name('assignments.datatable');
        Route::post('/assignments/auto-assign', [DeliveryAssignmentController::class, 'autoAssign'])->name('assignments.auto-assign');
        Route::post('/assignments/manual-assign', [DeliveryAssignmentController::class, 'manualAssign'])->name('assignments.manual-assign');
        Route::get('/assignments/live-map', [DeliveryAssignmentController::class, 'liveMap'])->name('assignments.live-map');
        Route::get('/assignments/search/sub-orders', [DeliveryAssignmentController::class, 'searchSubOrders'])->name('assignments.search.sub-orders');
        Route::get('/assignments/search/shipments', [DeliveryAssignmentController::class, 'searchShipments'])->name('assignments.search.shipments');
        // Payouts
        Route::get('/payouts', [DeliveryPayoutController::class, 'index'])->name('payouts.index');
        Route::post('/payouts/datatable', [DeliveryPayoutController::class, 'datatable'])->name('payouts.datatable');
        Route::post('/payouts/generate', [DeliveryPayoutController::class, 'generate'])->name('payouts.generate');
        Route::post('/payouts/{payout}/approve', [DeliveryPayoutController::class, 'approve'])->name('payouts.approve');
        Route::post('/payouts/{payout}/process', [DeliveryPayoutController::class, 'process'])->name('payouts.process');
        // COD Settlements
        Route::prefix('cod-settlements')->name('cod-settlements.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\CodSettlementController::class, 'index'])->name('index');
            Route::post('/generate', [\App\Http\Controllers\Admin\CodSettlementController::class, 'generate'])->name('generate');
            Route::get('/{settlement}', [\App\Http\Controllers\Admin\CodSettlementController::class, 'show'])->name('show');
            Route::post('/{settlement}/settle', [\App\Http\Controllers\Admin\CodSettlementController::class, 'markSettled'])->name('settle');
            Route::post('/{settlement}/dispute', [\App\Http\Controllers\Admin\CodSettlementController::class, 'dispute'])->name('dispute');
        });
    });

    // ── FBN / Fulfillment ─────────────────────────────────────────────────────
    Route::prefix('fbn')->name('fbn.')->group(function () {

        // Inbound requests
        Route::prefix('inbound')->name('inbound.')->group(function () {
            Route::get('/', [FbnController::class, 'inboundIndex'])->name('index');
            Route::post('/datatable', [FbnController::class, 'inboundDatatable'])->name('datatable');
            Route::post('/{request}/approve', [FbnController::class, 'approveInbound'])->name('approve');
            Route::post('/{request}/reject', [FbnController::class, 'rejectInbound'])->name('reject');
            Route::post('/{request}/tracking', [FbnController::class, 'updateTracking'])->name('tracking');
            Route::post('/{request}/receive', [FbnController::class, 'receiveInbound'])->name('receive');
        });

        // Storage fees
        Route::prefix('storage-fees')->name('storage-fees.')->group(function () {
            Route::get('/', [FbnController::class, 'storageFeesIndex'])->name('index');
            Route::post('/datatable', [FbnController::class, 'storageFeesDatatable'])->name('datatable');
            Route::post('/generate', [FbnController::class, 'generateMonthlyFees'])->name('generate');
            Route::post('/{fee}/status', [FbnController::class, 'updateStorageFeeStatus'])->name('status');
        });

        // Marketplace shipping rules
        Route::prefix('marketplace')->name('marketplace.')->group(function () {
            Route::get('/', [FbnController::class, 'marketplaceIndex'])->name('index');
            Route::post('/datatable', [FbnController::class, 'marketplaceDatatable'])->name('datatable');
            Route::post('/', [FbnController::class, 'storeMarketplaceRule'])->name('store');
            Route::put('/{rule}', [FbnController::class, 'updateMarketplaceRule'])->name('update');
            Route::delete('/{rule}', [FbnController::class, 'destroyMarketplaceRule'])->name('destroy');
        });
    });

    // ── Vendor Subscriptions ──────────────────────────────────────────────────────
    Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
        // Plans CRUD
        Route::get('/plans', [SubscriptionController::class, 'plansIndex'])->name('plans.index');
        Route::post('/plans', [SubscriptionController::class, 'storePlan'])->name('plans.store');
        Route::put('/plans/{plan}', [SubscriptionController::class, 'updatePlan'])->name('plans.update');
        Route::post('/plans/{plan}/toggle-active', [SubscriptionController::class, 'togglePlanActive'])->name('plans.toggle-active');
        Route::delete('/plans/{plan}', [SubscriptionController::class, 'destroyPlan'])->name('plans.destroy');

        // Vendor subscriptions
        Route::get('/', [SubscriptionController::class, 'index'])->name('index');
        Route::post('/datatable', [SubscriptionController::class, 'datatable'])->name('datatable');
        Route::post('/subscribe-vendor', [SubscriptionController::class, 'subscribeVendor'])->name('subscribe-vendor');
        // Specific before wildcard
        Route::get('/invoices/list', [SubscriptionController::class, 'invoicesIndex'])->name('invoices.index');
        Route::post('/invoices/datatable', [SubscriptionController::class, 'invoicesDatatable'])->name('invoices.datatable');
        Route::post('/invoices/{invoice}/mark-paid', [SubscriptionController::class, 'markInvoicePaid'])->name('invoices.mark-paid');

        Route::get('/{subscription}', [SubscriptionController::class, 'show'])->name('show');
        Route::post('/{subscription}/cancel', [SubscriptionController::class, 'cancelSubscription'])->name('cancel');
    });

    // ─── Classifieds ──────────────────────────────────────────────────────────
    Route::prefix('classifieds')->name('classifieds.')->group(function () {

        // Categories
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ClassifiedCategoryController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Admin\ClassifiedCategoryController::class, 'store'])->name('store');
            Route::put('/{category}', [\App\Http\Controllers\Admin\ClassifiedCategoryController::class, 'update'])->name('update');
            Route::delete('/{category}', [\App\Http\Controllers\Admin\ClassifiedCategoryController::class, 'destroy'])->name('destroy');
            Route::post('/{category}/toggle', [\App\Http\Controllers\Admin\ClassifiedCategoryController::class, 'toggleActive'])->name('toggle');
            Route::post('/reorder', [\App\Http\Controllers\Admin\ClassifiedCategoryController::class, 'reorder'])->name('reorder');
        });

        // Contract Templates
        Route::prefix('contract-templates')->name('contract-templates.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ClassifiedContractTemplateController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Admin\ClassifiedContractTemplateController::class, 'store'])->name('store');
            Route::put('/{contractTemplate}', [\App\Http\Controllers\Admin\ClassifiedContractTemplateController::class, 'update'])->name('update');
            Route::delete('/{contractTemplate}', [\App\Http\Controllers\Admin\ClassifiedContractTemplateController::class, 'destroy'])->name('destroy');
        });

        // Listings (review queue)
        Route::prefix('listings')->name('listings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ClassifiedListingController::class, 'index'])->name('index');
            Route::get('/{listing}', [\App\Http\Controllers\Admin\ClassifiedListingController::class, 'show'])->name('show');
            Route::post('/{listing}/approve', [\App\Http\Controllers\Admin\ClassifiedListingController::class, 'approve'])->name('approve');
            Route::post('/{listing}/reject', [\App\Http\Controllers\Admin\ClassifiedListingController::class, 'reject'])->name('reject');
        });

        // Attachment verification
        Route::post('/attachments/{attachment}/verify', [\App\Http\Controllers\Admin\ClassifiedListingController::class, 'verifyAttachment'])
            ->name('attachments.verify');
    });

    // ─── Admin Listings (Platform Listings) ──────────────────────────────────
    Route::prefix('admin-listings')->name('admin-listings.')
        ->middleware('admin.permission:admin_listings.view')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdminListingController::class, 'index'])->name('index');
            Route::post('/datatable', [\App\Http\Controllers\Admin\AdminListingController::class, 'datatable'])->name('datatable');
            Route::post('/bulk', [\App\Http\Controllers\Admin\AdminListingController::class, 'bulkAction'])
                ->middleware('admin.permission:admin_listings.edit')->name('bulk');
            Route::get('/create', [\App\Http\Controllers\Admin\AdminListingController::class, 'create'])
                ->middleware('admin.permission:admin_listings.create')->name('create');
            Route::get('/search/variants', [\App\Http\Controllers\Admin\AdminListingController::class, 'searchVariants'])->name('search-variants');
            Route::post('/', [\App\Http\Controllers\Admin\AdminListingController::class, 'store'])
                ->middleware('admin.permission:admin_listings.create')->name('store');
            Route::get('/{adminListing}/edit', [\App\Http\Controllers\Admin\AdminListingController::class, 'edit'])
                ->middleware('admin.permission:admin_listings.edit')->name('edit');
            Route::put('/{adminListing}', [\App\Http\Controllers\Admin\AdminListingController::class, 'update'])
                ->middleware('admin.permission:admin_listings.edit')->name('update');
            Route::delete('/{adminListing}', [\App\Http\Controllers\Admin\AdminListingController::class, 'destroy'])
                ->middleware('admin.permission:admin_listings.delete')->name('destroy');
            Route::post('/{adminListing}/activate', [\App\Http\Controllers\Admin\AdminListingController::class, 'activate'])
                ->middleware('admin.permission:admin_listings.toggle_status')->name('activate');
            Route::post('/{adminListing}/toggle-status', [\App\Http\Controllers\Admin\AdminListingController::class, 'toggleStatus'])
                ->middleware('admin.permission:admin_listings.toggle_status')->name('toggle-status');
            Route::post('/{adminListing}/reference', [\App\Http\Controllers\Admin\AdminListingController::class, 'saveReference'])
                ->middleware('admin.permission:admin_listings.edit')->name('save-reference');
            Route::patch('/{adminListing}/status', [\App\Http\Controllers\Admin\AdminListingController::class, 'updateStatus'])
                ->middleware('admin.permission:admin_listings.toggle_status')->name('update-status');
            Route::post('/{adminListing}/adjust-stock', [\App\Http\Controllers\Admin\AdminListingController::class, 'adjustStock'])
                ->middleware('admin.permission:admin_listings.edit')->name('adjust-stock');
            Route::post('/{adminListing}/clear-cache', [\App\Http\Controllers\Admin\AdminListingController::class, 'clearCache'])
                ->middleware('admin.permission:admin_listings.edit')
                ->name('clear-cache');
            Route::get('/{adminListing}/inventory', [\App\Http\Controllers\Admin\AdminListingInventoryController::class, 'index'])
                ->name('inventory.index');
            Route::post('/{adminListing}/inventory', [\App\Http\Controllers\Admin\AdminListingInventoryController::class, 'store'])
                ->middleware('admin.permission:admin_listings.edit')->name('inventory.store');
            Route::put('/{adminListing}/inventory/{inventory}', [\App\Http\Controllers\Admin\AdminListingInventoryController::class, 'update'])
                ->middleware('admin.permission:admin_listings.edit')->name('inventory.update');
            Route::post('/{adminListing}/shipping-rule', [\App\Http\Controllers\Admin\AdminListingController::class, 'saveShippingRule'])
                ->middleware('admin.permission:admin_listings.edit')->name('save-shipping-rule');
            Route::post('/{adminListing}/cost-references/datatable', [\App\Http\Controllers\Admin\AdminListingController::class, 'costReferences'])
                ->name('cost-references.datatable');
            Route::post('/{adminListing}/cost-references', [\App\Http\Controllers\Admin\AdminListingController::class, 'storeCostReference'])
                ->name('cost-references.store');
            Route::put('/{adminListing}/cost-references/{costReference}', [\App\Http\Controllers\Admin\AdminListingController::class, 'updateCostReference'])
                ->name('cost-references.update');
            Route::delete('/{adminListing}/cost-references/{costReference}', [\App\Http\Controllers\Admin\AdminListingController::class, 'destroyCostReference'])
                ->name('cost-references.destroy');
            Route::get('/{adminListing}/reviews', [\App\Http\Controllers\Admin\AdminListingReviewController::class, 'index'])
                ->name('reviews.index');
            Route::get('/{adminListing}', [\App\Http\Controllers\Admin\AdminListingController::class, 'show'])->name('show');
        });

    // ─── Vendor Listings ──────────────────────────────────────────────────────
    Route::prefix('vendor-listings')->name('vendor-listings.')
        ->middleware('admin.permission:vendors.view')
        ->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\VendorListingController::class, 'index'])->name('index');
            Route::post('/datatable', [\App\Http\Controllers\Admin\VendorListingController::class, 'datatable'])->name('datatable');
            Route::get('/{vendorListing}/edit', [\App\Http\Controllers\Admin\VendorListingController::class, 'edit'])->name('edit');
            Route::put('/{vendorListing}', [\App\Http\Controllers\Admin\VendorListingController::class, 'update'])->name('update');
            Route::post('/{vendorListing}/clear-cache', [\App\Http\Controllers\Admin\VendorListingController::class, 'clearCache'])
                ->name('clear-cache');
            Route::get('/{vendorListing}', [\App\Http\Controllers\Admin\VendorListingController::class, 'show'])->name('show');
        });

    // ─── Travel Agencies & Packages ───────────────────────────────────────────
    Route::prefix('travel')->name('travel.')->group(function () {

        Route::prefix('agencies')->name('agencies.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\TravelAgencyController::class, 'index'])->name('index');
            Route::get('/datatable', [\App\Http\Controllers\Admin\TravelAgencyController::class, 'datatable'])->name('datatable');
            Route::get('/{travelAgency}', [\App\Http\Controllers\Admin\TravelAgencyController::class, 'show'])->name('show');
            Route::post('/{travelAgency}/approve', [\App\Http\Controllers\Admin\TravelAgencyController::class, 'approve'])->name('approve');
            Route::post('/{travelAgency}/suspend', [\App\Http\Controllers\Admin\TravelAgencyController::class, 'suspend'])->name('suspend');
            Route::post('/{travelAgency}/reactivate', [\App\Http\Controllers\Admin\TravelAgencyController::class, 'reactivate'])->name('reactivate');
            Route::post('/{travelAgency}/reject', [\App\Http\Controllers\Admin\TravelAgencyController::class, 'reject'])->name('reject');
        });

        Route::prefix('change-requests')->name('change-requests.')
            ->middleware('admin.permission:travel_agency_change_requests.view')
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\Admin\TravelAgencyChangeRequestController::class, 'index'])->name('index');
                Route::get('/{changeRequest}', [\App\Http\Controllers\Admin\TravelAgencyChangeRequestController::class, 'show'])->name('show');
                Route::post('/{changeRequest}/approve', [\App\Http\Controllers\Admin\TravelAgencyChangeRequestController::class, 'approve'])
                    ->name('approve')
                    ->middleware('admin.permission:travel_agency_change_requests.approve');
                Route::post('/{changeRequest}/reject', [\App\Http\Controllers\Admin\TravelAgencyChangeRequestController::class, 'reject'])
                    ->name('reject')
                    ->middleware('admin.permission:travel_agency_change_requests.approve');
            });

        Route::prefix('packages')->name('packages.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\TravelPackageController::class, 'index'])->name('index');
            Route::post('/datatable', [\App\Http\Controllers\Admin\TravelPackageController::class, 'datatable'])->name('datatable');
            Route::get('/{travelPackage}', [\App\Http\Controllers\Admin\TravelPackageController::class, 'show'])->name('show');
            Route::post('/{travelPackage}/approve', [\App\Http\Controllers\Admin\TravelPackageController::class, 'approve'])->name('approve');
            Route::post('/{travelPackage}/reject', [\App\Http\Controllers\Admin\TravelPackageController::class, 'reject'])->name('reject');
            Route::post('/{travelPackage}/expire', [\App\Http\Controllers\Admin\TravelPackageController::class, 'expire'])->name('expire');
            Route::get('/{travelPackage}/contract', [\App\Http\Controllers\Admin\TravelPackageController::class, 'downloadContract'])->name('contract.download');
            Route::post('/{travelPackage}/categories', [\App\Http\Controllers\Admin\TravelPackageController::class, 'syncCategories'])->name('categories.sync');
        });

        Route::prefix('bookings')->name('bookings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\TravelBookingController::class, 'index'])->name('index');
            Route::post('/datatable', [\App\Http\Controllers\Admin\TravelBookingController::class, 'datatable'])->name('datatable');
            Route::get('/{travelBooking}', [\App\Http\Controllers\Admin\TravelBookingController::class, 'show'])->name('show');
            Route::get('/{travelBooking}/passport', [\App\Http\Controllers\Admin\TravelBookingController::class, 'downloadPassport'])->name('passport.download');
        });

        Route::prefix('countries')->name('countries.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\TravelCountryController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Admin\TravelCountryController::class, 'store'])->name('store');
            Route::put('/{travelCountry}', [\App\Http\Controllers\Admin\TravelCountryController::class, 'update'])->name('update');
            Route::delete('/{travelCountry}', [\App\Http\Controllers\Admin\TravelCountryController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('cities')->name('cities.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\TravelCityController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Admin\TravelCityController::class, 'store'])->name('store');
            Route::put('/{travelCity}', [\App\Http\Controllers\Admin\TravelCityController::class, 'update'])->name('update');
            Route::delete('/{travelCity}', [\App\Http\Controllers\Admin\TravelCityController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('inquiries')->name('inquiries.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\TravelPackageInquiryController::class, 'index'])->name('index');
        });

        Route::prefix('inclusions')->name('inclusions.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\TravelInclusionController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Admin\TravelInclusionController::class, 'store'])->name('store');
            Route::put('/{travelInclusion}', [\App\Http\Controllers\Admin\TravelInclusionController::class, 'update'])->name('update');
            Route::delete('/{travelInclusion}', [\App\Http\Controllers\Admin\TravelInclusionController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\TravelCategoryController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Admin\TravelCategoryController::class, 'store'])->name('store');
            Route::put('/{travelCategory}', [\App\Http\Controllers\Admin\TravelCategoryController::class, 'update'])->name('update');
            Route::delete('/{travelCategory}', [\App\Http\Controllers\Admin\TravelCategoryController::class, 'destroy'])->name('destroy');
        });
    });

    // ─── Shipping Companies (Carrier Portal) ─────────────────────────────────
    Route::prefix('shipping-companies')->name('shipping-companies.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'index'])->name('index');

        Route::get('/create', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'create'])
            ->name('create')
            ->middleware('admin.permission:settings.edit');

        Route::post('/', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'store'])
            ->name('store')
            ->middleware('admin.permission:settings.edit');

        Route::get('/{shippingCompany}', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'show'])->name('show');
        Route::put('/{shippingCompany}', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'update'])
            ->name('update')
            ->middleware('admin.permission:settings.edit');
        Route::delete('/{shippingCompany}', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'destroy'])
            ->name('destroy')
            ->middleware('admin.permission:settings.edit');
        Route::post('/{shippingCompany}/approve', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'approve'])->name('approve');
        Route::post('/{shippingCompany}/suspend', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'suspend'])->name('suspend');
        Route::post('/supervisors/{supervisor}/toggle-notifications', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'toggleSupervisorNotifications'])->name('supervisors.toggle-notifications');

        Route::post('/supervisors', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'storeSupervisor'])
            ->name('supervisors.store')
            ->middleware('admin.permission:settings.edit');

        Route::put('/supervisors/{supervisor}', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'updateSupervisor'])
            ->name('supervisors.update')
            ->middleware('admin.permission:settings.edit');

        Route::post('/supervisors/{supervisor}/reset-password', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'resetSupervisorPassword'])
            ->name('supervisors.reset-password')
            ->middleware('admin.permission:settings.edit');

        Route::delete('/supervisors/{supervisor}', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'destroySupervisor'])
            ->name('supervisors.destroy')
            ->middleware('admin.permission:settings.edit');
        Route::get('/fallback-rules', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'fallbackRules'])->name('fallback-rules.index');
        Route::post('/fallback-rules', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'storeFallbackRule'])->name('fallback-rules.store');
        Route::delete('/fallback-rules/{rule}', [\App\Http\Controllers\Admin\ShippingCompanyController::class, 'destroyFallbackRule'])->name('fallback-rules.destroy');
    });

    // ─── Wallets ──────────────────────────────────────────────────────────────
    Route::prefix('wallets')->name('wallets.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\WalletController::class, 'index'])->name('index');
        Route::post('/datatable', [\App\Http\Controllers\Admin\WalletController::class, 'datatable'])->name('datatable');
        Route::get('/{wallet}', [\App\Http\Controllers\Admin\WalletController::class, 'show'])->name('show');
        Route::post('/{wallet}/adjust', [\App\Http\Controllers\Admin\WalletController::class, 'adjustBalance'])->name('adjust');
        Route::patch('/{wallet}/freeze', [\App\Http\Controllers\Admin\WalletController::class, 'freezeWallet'])->name('freeze');
        Route::patch('/{wallet}/unfreeze', [\App\Http\Controllers\Admin\WalletController::class, 'unfreezeWallet'])->name('unfreeze');

        // Withdrawal requests
        Route::get('/withdrawals/queue', [\App\Http\Controllers\Admin\WalletController::class, 'withdrawalRequests'])->name('withdrawals');
        Route::patch('/withdrawals/{withdrawal}/approve', [\App\Http\Controllers\Admin\WalletController::class, 'approveWithdrawal'])->name('withdrawals.approve');
        Route::patch('/withdrawals/{withdrawal}/reject', [\App\Http\Controllers\Admin\WalletController::class, 'rejectWithdrawal'])->name('withdrawals.reject');
        Route::patch('/withdrawals/{withdrawal}/processed', [\App\Http\Controllers\Admin\WalletController::class, 'markWithdrawalProcessed'])->name('withdrawals.processed');

        // COD settlements
        Route::get('/cod/settlements', [\App\Http\Controllers\Admin\WalletController::class, 'codSettlements'])->name('cod-settlements');
        Route::post('/cod/settlements/run', [\App\Http\Controllers\Admin\WalletController::class, 'runCodSettlement'])->name('cod-settlements.run');
        Route::patch('/cod/settlements/{settlement}/settle', [\App\Http\Controllers\Admin\WalletController::class, 'markSettlementSettled'])->name('cod-settlements.settle');
    });

    // ── AI Features Dashboard ─────────────────────────────────────────────
    Route::prefix('ai')->name('ai.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AiDashboardController::class, 'index'])->name('index');
        Route::post('/credits/allocate', [\App\Http\Controllers\Admin\AiDashboardController::class, 'allocateCredits'])->name('credits.allocate');
    });

    // ── Radio ─────────────────────────────────────────────────────────────────
    // ── Live Streams ──────────────────────────────────────────────────────────
    Route::prefix('live-streams')->name('live-streams.')->middleware('admin.permission:pages.view')->group(function () {
        Route::get('/',                                   [LiveStreamController::class, 'index'])->name('index');
        Route::get('/create',                             [LiveStreamController::class, 'create'])->name('create');
        Route::post('/',                                  [LiveStreamController::class, 'store'])->name('store');
        Route::get('/{liveStream}',                       [LiveStreamController::class, 'show'])->name('show');
        Route::get('/{liveStream}/edit',                  [LiveStreamController::class, 'edit'])->name('edit');
        Route::put('/{liveStream}',                       [LiveStreamController::class, 'update'])->name('update');
        Route::delete('/{liveStream}',                    [LiveStreamController::class, 'destroy'])->name('destroy');
        Route::post('/{liveStream}/go-live',              [LiveStreamController::class, 'goLive'])->name('go-live');
        Route::post('/{liveStream}/end',                  [LiveStreamController::class, 'endStream'])->name('end');
        Route::post('/{liveStream}/signal',               [LiveStreamController::class, 'signal'])->name('signal');
        Route::get('/{liveStream}/comments',              [LiveStreamController::class, 'comments'])->name('comments');
        Route::delete('/{liveStream}/comments/{comment}', [LiveStreamController::class, 'deleteComment'])->name('comments.destroy');
    });

    Route::prefix('radio')->name('radio.')->group(function () {
        Route::resource('channels', \App\Http\Controllers\Admin\RadioChannelController::class)
            ->names('channels')
            ->except(['show']);

        Route::get('/channels/{channel}/schedule', [\App\Http\Controllers\Admin\RadioChannelController::class, 'schedule'])->name('schedule');
        Route::get('/channels/{channel}/schedule/events', [\App\Http\Controllers\Admin\RadioChannelController::class, 'scheduleEvents'])->name('schedule.events');
        Route::post('/channels/{channel}/slots', [\App\Http\Controllers\Admin\RadioChannelController::class, 'storeSlot'])->name('slots.store');
        Route::put('/channels/{channel}/slots/{slot}', [\App\Http\Controllers\Admin\RadioChannelController::class, 'updateSlot'])->name('slots.update');
        Route::delete('/channels/{channel}/slots/{slot}', [\App\Http\Controllers\Admin\RadioChannelController::class, 'destroySlot'])->name('slots.destroy');
    });

    // ─── Carrier Claims ───────────────────────────────────────────────────────
    Route::prefix('carrier-claims')->name('carrier-claims.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\CarrierClaimController::class, 'index'])->name('index');
        Route::get('/{carrierClaim}', [\App\Http\Controllers\Admin\CarrierClaimController::class, 'show'])->name('show');
        Route::patch('/{carrierClaim}/resolve', [\App\Http\Controllers\Admin\CarrierClaimController::class, 'resolve'])->name('resolve');
        Route::patch('/{carrierClaim}/under-review', [\App\Http\Controllers\Admin\CarrierClaimController::class, 'markUnderReview'])->name('under-review');
    });

    // ─── Carrier Scorecard ────────────────────────────────────────────────────
    Route::prefix('carrier-scorecard')->name('carrier-scorecard.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\CarrierScorecardController::class, 'index'])->name('index');
        Route::get('/{shippingCompany}', [\App\Http\Controllers\Admin\CarrierScorecardController::class, 'show'])->name('show');
        Route::get('/{shippingCompany}/trend', [\App\Http\Controllers\Admin\CarrierScorecardController::class, 'trendData'])->name('trend');
    });

    // ─── Packaging Supplies ───────────────────────────────────────────────────
    Route::prefix('packaging')->name('packaging.')->middleware('admin.permission:packaging.manage')->group(function () {
        Route::get('/catalog', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'catalog'])->name('catalog');
        Route::post('/catalog/datatable', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'datatableCatalog'])->name('catalog.datatable');
        Route::post('/catalog', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'storeCatalogItem'])->name('catalog.store');
        Route::get('/catalog/{supply}', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'getCatalogItem'])->name('catalog.show');
        Route::put('/catalog/{supply}', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'updateCatalogItem'])->name('catalog.update');
        Route::delete('/catalog/{supply}', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'destroyCatalogItem'])->name('catalog.destroy');
        Route::patch('/catalog/{supply}/toggle', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'toggleActive'])->name('catalog.toggle');

        Route::get('/requests', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'requests'])->name('requests');
        Route::post('/requests/datatable', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'datatableRequests'])->name('requests.datatable');
        Route::get('/requests/{request}', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'showRequest'])->name('requests.show');
        Route::post('/requests/{request}/approve', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'approve'])->name('requests.approve');
        Route::post('/requests/{request}/reject', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'reject'])->name('requests.reject');
        Route::post('/requests/{request}/ship', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'markShipped'])->name('requests.ship');
        Route::post('/requests/{request}/deliver', [\App\Http\Controllers\Admin\PackagingSupplyController::class, 'markDelivered'])->name('requests.deliver');
    });


    // ─── Blog Categories ──────────────────────────────────────────────────────
    Route::prefix('blog/categories')->name('blog.categories.')->middleware('admin.permission:pages.view')->group(function () {
        Route::post('/reorder', [\App\Http\Controllers\Admin\BlogCategoryController::class, 'reorder'])->name('reorder');
        Route::get('/', [\App\Http\Controllers\Admin\BlogCategoryController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\BlogCategoryController::class, 'store'])->name('store');
        Route::put('/{category}', [\App\Http\Controllers\Admin\BlogCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [\App\Http\Controllers\Admin\BlogCategoryController::class, 'destroy'])->name('destroy');
        Route::post('/{category}/toggle', [\App\Http\Controllers\Admin\BlogCategoryController::class, 'toggleActive'])->name('toggle');
    });

    // ─── Blog Posts ───────────────────────────────────────────────────────────
    Route::prefix('blog/posts')->name('blog.posts.')->middleware('admin.permission:pages.view')->group(function () {
        Route::post('/datatable', [\App\Http\Controllers\Admin\BlogPostController::class, 'datatable'])->name('datatable');
        Route::get('/create', [\App\Http\Controllers\Admin\BlogPostController::class, 'create'])->name('create');
        Route::get('/', [\App\Http\Controllers\Admin\BlogPostController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\BlogPostController::class, 'store'])->name('store');
        Route::get('/{post}/edit', [\App\Http\Controllers\Admin\BlogPostController::class, 'edit'])->name('edit');
        Route::put('/{post}', [\App\Http\Controllers\Admin\BlogPostController::class, 'update'])->name('update');
        Route::delete('/{post}', [\App\Http\Controllers\Admin\BlogPostController::class, 'destroy'])->name('destroy');
        Route::post('/{post}/archive', [\App\Http\Controllers\Admin\BlogPostController::class, 'archive'])->name('archive');
        Route::post('/{post}/feature', [\App\Http\Controllers\Admin\BlogPostController::class, 'feature'])->name('feature');
        Route::delete('/{post}/attachments/{file}', [\App\Http\Controllers\Admin\BlogPostController::class, 'deleteAttachment'])->name('attachments.delete');
    });

    // ─── Ad Support Collections (Knowledge Hub) ──────────────────────────────
    Route::prefix('adsupport/collections')->name('adsupport.collections.')->middleware('admin.permission:pages.view')->group(function () {
        Route::post('/reorder', [\App\Http\Controllers\Admin\AdSupportCollectionController::class, 'reorder'])->name('reorder');
        Route::get('/', [\App\Http\Controllers\Admin\AdSupportCollectionController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\AdSupportCollectionController::class, 'store'])->name('store');
        Route::put('/{collection:id}', [\App\Http\Controllers\Admin\AdSupportCollectionController::class, 'update'])->name('update');
        Route::delete('/{collection:id}', [\App\Http\Controllers\Admin\AdSupportCollectionController::class, 'destroy'])->name('destroy');
        Route::post('/{collection:id}/toggle', [\App\Http\Controllers\Admin\AdSupportCollectionController::class, 'toggleActive'])->name('toggle');
    });

    // ─── Ad Support Articles (Knowledge Hub) ─────────────────────────────────
    Route::prefix('adsupport/articles')->name('adsupport.articles.')->middleware('admin.permission:pages.view')->group(function () {
        Route::post('/datatable', [\App\Http\Controllers\Admin\AdSupportArticleController::class, 'datatable'])->name('datatable');
        Route::get('/create', [\App\Http\Controllers\Admin\AdSupportArticleController::class, 'create'])->name('create');
        Route::get('/', [\App\Http\Controllers\Admin\AdSupportArticleController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\AdSupportArticleController::class, 'store'])->name('store');
        Route::get('/{article:id}/edit', [\App\Http\Controllers\Admin\AdSupportArticleController::class, 'edit'])->name('edit');
        Route::put('/{article:id}', [\App\Http\Controllers\Admin\AdSupportArticleController::class, 'update'])->name('update');
        Route::delete('/{article:id}', [\App\Http\Controllers\Admin\AdSupportArticleController::class, 'destroy'])->name('destroy');
        Route::post('/{article:id}/feature', [\App\Http\Controllers\Admin\AdSupportArticleController::class, 'feature'])->name('feature');
    });

    // ─── Help Center Categories (Portal) ─────────────────────────────────────
    Route::prefix('helpcenter/categories')->name('helpcenter.categories.')->middleware('admin.permission:pages.view')->group(function () {
        Route::post('/reorder', [\App\Http\Controllers\Admin\HelpCenterCategoryController::class, 'reorder'])->name('reorder');
        Route::get('/', [\App\Http\Controllers\Admin\HelpCenterCategoryController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\HelpCenterCategoryController::class, 'store'])->name('store');
        Route::put('/{category:id}', [\App\Http\Controllers\Admin\HelpCenterCategoryController::class, 'update'])->name('update');
        Route::delete('/{category:id}', [\App\Http\Controllers\Admin\HelpCenterCategoryController::class, 'destroy'])->name('destroy');
        Route::post('/{category:id}/toggle', [\App\Http\Controllers\Admin\HelpCenterCategoryController::class, 'toggleActive'])->name('toggle');
    });

    // ─── Help Center Articles (Portal) ───────────────────────────────────────
    Route::prefix('helpcenter/articles')->name('helpcenter.articles.')->middleware('admin.permission:pages.view')->group(function () {
        Route::post('/datatable', [\App\Http\Controllers\Admin\HelpCenterArticleController::class, 'datatable'])->name('datatable');
        Route::get('/create', [\App\Http\Controllers\Admin\HelpCenterArticleController::class, 'create'])->name('create');
        Route::get('/', [\App\Http\Controllers\Admin\HelpCenterArticleController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Admin\HelpCenterArticleController::class, 'store'])->name('store');
        Route::get('/{article:id}/edit', [\App\Http\Controllers\Admin\HelpCenterArticleController::class, 'edit'])->name('edit');
        Route::put('/{article:id}', [\App\Http\Controllers\Admin\HelpCenterArticleController::class, 'update'])->name('update');
        Route::delete('/{article:id}', [\App\Http\Controllers\Admin\HelpCenterArticleController::class, 'destroy'])->name('destroy');
        Route::post('/{article:id}/feature', [\App\Http\Controllers\Admin\HelpCenterArticleController::class, 'feature'])->name('feature');
    });

    // ─── App Contexts (Platform Navigation) ──────────────────────────────────
    Route::prefix('app-contexts')->name('app-contexts.')->middleware('admin.permission:app_contexts.view')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AppContextController::class, 'index'])->name('index');
        Route::get('/{context}', [\App\Http\Controllers\Admin\AppContextController::class, 'show'])->name('show');
        Route::put('/{context}', [\App\Http\Controllers\Admin\AppContextController::class, 'update'])->name('update');
        Route::post('/{context}/countries', [\App\Http\Controllers\Admin\AppContextController::class, 'saveCountryAssignment'])->name('countries.save');
        Route::post('/{context}/nav', [\App\Http\Controllers\Admin\AppContextController::class, 'saveNavItem'])->name('nav.save');
        Route::put('/{context}/nav/{item}', [\App\Http\Controllers\Admin\AppContextController::class, 'updateNavItem'])->name('nav.update');
    });

    // ─── Documentation ────────────────────────────────────────────────────────
    Route::prefix('docs')->name('docs.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DocsController::class, 'index'])->name('index');

        // Panels
        Route::get('/panels/admin', [\App\Http\Controllers\Admin\DocsController::class, 'adminPanel'])->name('panels.admin');
        Route::get('/panels/partner', [\App\Http\Controllers\Admin\DocsController::class, 'partnerPanel'])->name('panels.partner');
        Route::get('/panels/travel', [\App\Http\Controllers\Admin\DocsController::class, 'travelPanel'])->name('panels.travel');
        Route::get('/panels/delivery', [\App\Http\Controllers\Admin\DocsController::class, 'deliveryPanel'])->name('panels.delivery');
        Route::get('/panels/carrier', [\App\Http\Controllers\Admin\DocsController::class, 'carrierPanel'])->name('panels.carrier');

        // Features
        Route::get('/features/order-lifecycle', [\App\Http\Controllers\Admin\DocsController::class, 'orderLifecycle'])->name('features.order-lifecycle');
        Route::get('/features/shipping', [\App\Http\Controllers\Admin\DocsController::class, 'shipping'])->name('features.shipping');
        Route::get('/features/warehouses', [\App\Http\Controllers\Admin\DocsController::class, 'warehouses'])->name('features.warehouses');
        Route::get('/features/payments', [\App\Http\Controllers\Admin\DocsController::class, 'payments'])->name('features.payments');
        Route::get('/features/page-builder', [\App\Http\Controllers\Admin\DocsController::class, 'pageBuilder'])->name('features.page-builder');
        Route::get('/features/banners', [\App\Http\Controllers\Admin\DocsController::class, 'banners'])->name('features.banners');
        Route::get('/features/ad-campaigns', [\App\Http\Controllers\Admin\DocsController::class, 'adCampaigns'])->name('features.ad-campaigns');
        Route::get('/features/vendor-campaigns', [\App\Http\Controllers\Admin\DocsController::class, 'vendorCampaigns'])->name('features.vendor-campaigns');
        Route::get('/features/flash-sales', [\App\Http\Controllers\Admin\DocsController::class, 'flashSales'])->name('features.flash-sales');
        Route::get('/features/finance', [\App\Http\Controllers\Admin\DocsController::class, 'finance'])->name('features.finance');
        Route::get('/features/subsidy', [\App\Http\Controllers\Admin\DocsController::class, 'subsidy'])->name('features.subsidy');
        Route::get('/features/packaging', [\App\Http\Controllers\Admin\DocsController::class, 'packaging'])->name('features.packaging');
        Route::get('/features/content-pages', [\App\Http\Controllers\Admin\DocsController::class, 'contentPages'])->name('features.content-pages');
        Route::get('/features/system-pages', [\App\Http\Controllers\Admin\DocsController::class, 'systemPages'])->name('features.system-pages');
        Route::get('/features/roles', [\App\Http\Controllers\Admin\DocsController::class, 'roles'])->name('features.roles');
        Route::get('/features/warranties', [\App\Http\Controllers\Admin\DocsController::class, 'warranties'])->name('features.warranties');
        Route::get('/features/classifieds', [\App\Http\Controllers\Admin\DocsController::class, 'classifieds'])->name('features.classifieds');
        Route::get('/features/travel', [\App\Http\Controllers\Admin\DocsController::class, 'travelFeature'])->name('features.travel');
        Route::get('/features/radio', [\App\Http\Controllers\Admin\DocsController::class, 'radioFeature'])->name('features.radio');
    });

}); // end auth.admin middleware group

