<?php

namespace App\Http\Controllers\Partner;

use App\Enums\InventoryMovementReferenceType;
use App\Enums\InventoryMovementType;
use App\Enums\ProductStatus;
use App\Enums\VendorListingStatus;
use App\Enums\WarehouseType;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Country;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\VendorListing;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\PlatformShippingSubsidy;
use App\Models\ShippingZone;
use App\Notifications\Admin\ListingResubmittedNotification;
use App\Services\ListingCertificationGate;
use App\Services\ListingShippingResolver;
use App\Services\MarketerCampaignService;
use App\Services\Shared\PageCacheService;
use App\Services\ShippingWeightService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ListingController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function __construct(
        private readonly ListingShippingResolver $shippingResolver,
        private readonly ShippingWeightService $weightService,
        private readonly MarketerCampaignService $marketerCampaignService,
    ) {
    }

    private const HANDLING_CLASSES = ['standard', 'refrigerated', 'fragile', 'special_tech'];

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    private function vendor(): \App\Models\Vendor
    {
        return Auth::guard('vendor')->user()->vendor;
    }

    private function authoriseListing(VendorListing $listing): void
    {
        if ($listing->vendor_id !== $this->vendorId()) {
            abort(403);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportListings($request);
        }

        $vendorId = $this->vendorId();

        $counts = VendorListing::where('vendor_id', $vendorId)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $lowStockCount = VendorListing::where('vendor_id', $vendorId)
            ->whereExists(function ($q) {
                $q->from('warehouse_inventories')
                    ->whereColumn('vendor_listing_id', 'vendor_listings.id')
                    ->whereRaw('quantity_on_hand - quantity_reserved <= low_stock_threshold')
                    ->whereRaw('quantity_on_hand > 0');
            })
            ->count();

        return view('partner.listings.index', compact('counts', 'lowStockCount'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared query builder
    // ─────────────────────────────────────────────────────────────────────────

    private function buildListingsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $vendorId = $this->vendorId();

        $query = VendorListing::where('vendor_listings.vendor_id', $vendorId)
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'vendor_listings.product_variant_id')
            ->leftJoin('products as p', 'p.id', '=', 'pv.product_id')
            ->select([
                'vendor_listings.id',
                'vendor_listings.status',
                'vendor_listings.price',
                'vendor_listings.currency',
                'vendor_listings.fulfillment_model',
                'vendor_listings.total_sold',
                'vendor_listings.rating_avg',
                'vendor_listings.low_stock_threshold',
                'vendor_listings.created_at',
                'pv.id as variant_id',
                'pv.variant_name',
                'pv.sku',
                'p.id as product_id',
                'p.name_en',
                'p.name_ar',
            ])
            ->addSelect([
                'primary_image' => ProductImage::select('path')
                    ->whereColumn('product_id', 'p.id')
                    ->where('is_primary', true)
                    ->orderBy('position')
                    ->limit(1),
                'primary_image_disk' => ProductImage::select('disk')
                    ->whereColumn('product_id', 'p.id')
                    ->where('is_primary', true)
                    ->orderBy('position')
                    ->limit(1),
                'available_stock' => WarehouseInventory::selectRaw('COALESCE(SUM(quantity_on_hand - quantity_reserved), 0)')
                    ->whereColumn('vendor_listing_id', 'vendor_listings.id'),
            ]);

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $v === 'low_stock'
                ? $q->whereRaw('vendor_listings.id IN (SELECT vendor_listing_id FROM warehouse_inventories WHERE quantity_on_hand - quantity_reserved <= vendor_listings.low_stock_threshold AND quantity_on_hand > 0)')
                : $q->where('vendor_listings.status', $v),
            'date_from' => fn($q, $v) => $q->whereDate('vendor_listings.created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('vendor_listings.created_at', '<=', $v),
        ]);

        if ($request->filled('search_term')) {
            $search = $request->input('search_term');
            $query->where(function ($sq) use ($search) {
                $sq->where('p.name_en', 'like', "%{$search}%")
                    ->orWhere('p.name_ar', 'like', "%{$search}%")
                    ->orWhere('pv.sku', 'like', "%{$search}%")
                    ->orWhere('vendor_listings.vendor_sku', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────────

    private function exportListings(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $items = $this->buildListingsQuery($request)->orderByDesc('vendor_listings.created_at')->get();

        $headers = ['SKU', 'Name', 'Price', 'Currency', 'Fulfillment', 'Status', 'Stock'];

        $rows = $items->map(fn($row) => [
            $row->sku,
            $row->name_en,
            number_format($row->price, 2),
            $row->currency,
            $row->fulfillment_model instanceof \BackedEnum ? $row->fulfillment_model->value : $row->fulfillment_model,
            $row->status instanceof VendorListingStatus ? $row->status->value : $row->status,
            (int) $row->available_stock,
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('listings', $headers, $rows),
            'csv' => $this->exportCsv('listings', $headers, $rows),
            'word' => $this->exportWord('listings', 'Listings', $rows),
            default => abort(400, __('common.invalid_export_format')),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $query = $this->buildListingsQuery($request);

        $columns = [
            ['searchable_columns' => ['p.name_en', 'p.name_ar', 'pv.sku']],
            ['orderable_column' => 'pv.variant_name'],
            ['orderable_column' => 'vendor_listings.status'],
            ['orderable_column' => 'vendor_listings.price'],
            [],
            ['orderable_column' => 'vendor_listings.total_sold'],
            ['orderable_column' => 'vendor_listings.rating_avg'],
            [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            $imageUrl = null;
            if ($row->primary_image) {
                $imageUrl = Storage::disk($row->primary_image_disk ?? 'public')->url($row->primary_image);
            }

            $available = (int) $row->available_stock;
            $threshold = (int) ($row->low_stock_threshold ?? 5);
            $stockClass = $available === 0
                ? 'text-red-600 font-bold'
                : ($available <= $threshold ? 'text-orange-500 font-semibold' : 'text-gray-800');
            $stockHtml = "<span class=\"{$stockClass} text-xs\">{$available}</span>";

            return [
                'id' => $row->id,
                'name_en' => $row->name_en,
                'name_ar' => $row->name_ar,
                'image_url' => $imageUrl,
                'variant_name' => $row->variant_name ?: '—',
                'sku' => $row->sku,
                'status' => $row->status instanceof VendorListingStatus ? $row->status->value : $row->status,
                'price' => number_format($row->price, 2),
                'price_raw' => $row->price,
                'available_stock' => $stockHtml,
                'available_raw' => $available,
                'total_sold' => $row->total_sold ?? 0,
                'rating_avg' => $row->rating_avg ? number_format($row->rating_avg, 1) : '—',
                'show_url' => Route::has('partner.listings.show')
                    ? route('partner.listings.show', $row->id) : '#',
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Product Search (AJAX — for create form)
    // ─────────────────────────────────────────────────────────────────────────

    public function productSearch(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $products = Product::query()
            ->where('status', ProductStatus::Active->value)
            ->where(function ($query) use ($q) {
                $query->where('name_en', 'like', '%' . $q . '%')
                    ->orWhere('name_ar', 'like', '%' . $q . '%')
                    ->orWhere('model_number', 'like', '%' . $q . '%')
                    ->orWhere('gtin', 'like', $q . '%');
            })
            ->with([
                'variants' => function ($vq) {
                    $vq->where('is_active', true)->orderBy('position');
                }
            ])
            ->addSelect([
                'primary_image' => ProductImage::select('path')
                    ->whereColumn('product_id', 'products.id')
                    ->where('is_primary', true)
                    ->orderBy('position')
                    ->limit(1),
                'primary_image_disk' => ProductImage::select('disk')
                    ->whereColumn('product_id', 'products.id')
                    ->where('is_primary', true)
                    ->orderBy('position')
                    ->limit(1),
            ])
            ->limit(10)
            ->get(['id', 'name_en', 'name_ar', 'model_number', 'has_variants']);

        $vendorId = $this->vendorId();

        return response()->json($products->map(function ($product) use ($vendorId) {
            $imageUrl = null;
            if ($product->primary_image) {
                $imageUrl = Storage::disk($product->primary_image_disk ?? 'public')->url($product->primary_image);
            }

            // Get which variant IDs the vendor already has listed for this product
            $existingVariantIds = VendorListing::where('vendor_id', $vendorId)
                ->whereIn('product_variant_id', $product->variants->pluck('id'))
                ->whereNotIn('status', [VendorListingStatus::Archived->value])
                ->pluck('product_variant_id')
                ->all();

            return [
                'id' => $product->id,
                'name' => $product->name_ar ?: $product->name_en,
                'name_en' => $product->name_en,
                'name_ar' => $product->name_ar,
                'model' => $product->model_number,
                'image_url' => $imageUrl,
                'has_variants' => (bool) $product->has_variants,
                'variants' => $product->variants->map(fn($v) => [
                    'id' => $v->id,
                    'variant_name' => $v->variant_name ?: 'النسخة الافتراضية',
                    'sku' => $v->sku,
                    'already_listed' => in_array($v->id, $existingVariantIds),
                ]),
            ];
        }));
    }

    /**
     * Read-only slug/URL preview for a product variant. Vendors cannot edit
     * slugs — they are product-level and managed by admins only.
     */
    public function categorySamples(Request $request): JsonResponse
    {
        $productId = $request->input('product_id');
        if (!$productId) {
            return response()->json(['influencer' => 0, 'affiliate' => 0, 'platform' => 0]);
        }

        $category = Product::find($productId)?->category;

        return response()->json([
            'influencer' => $category?->influencer_sample_qty ?? 0,
            'affiliate'  => $category?->affiliate_sample_qty ?? 0,
            'platform'   => $category?->platform_sample_qty ?? 0,
        ]);
    }

    /**
     * Returns the influencer platform fee per slot and marketer commission
     * per conversion for a product's category, for a given country.
     */
    public function campaignPricing(Request $request): JsonResponse
    {
        $productId = $request->input('product_id');
        $countryId = $request->input('country_id');

        if (!$productId || !$countryId) {
            return response()->json([
                'fee_per_influencer'           => 0,
                'influencer_commission_amount' => 0,
                'affiliate_commission_amount'  => 0,
                'currency'                     => '',
                'affiliate_fee_is_free'        => true,
            ]);
        }

        $category = Product::find($productId)?->category;

        $feeSetting = \App\Models\MarketerInfluencerFeeCountrySetting::where('country_id', $countryId)->first();

        $commissionSetting = $category
            ? \App\Models\MarketerCommissionCountrySetting::where('category_id', $category->id)
                ->where('country_id', $countryId)
                ->first()
            : null;

        $currency = Country::find($countryId)?->currency_code ?? '';

        return response()->json([
            'fee_per_influencer'           => $feeSetting?->fee_per_influencer ?? 0,
            'influencer_commission_amount' => $commissionSetting?->influencer_commission_amount ?? 0,
            'affiliate_commission_amount'  => $commissionSetting?->affiliate_commission_amount ?? 0,
            'currency'                     => $currency,
            'affiliate_fee_is_free'        => true,
            'category_name'                => $category?->name_ar ?? $category?->name_en ?? '',
        ]);
    }

    /**
     * Influencer platform fee for the vendor's own country. Fee is
     * country-based only (not category-based), so no product is needed.
     */
    public function getInfluencerFee(): JsonResponse
    {
        $vendor    = auth()->guard('vendor')->user()->vendor;
        $countryId = $vendor->country_id;

        $feeSetting = \App\Models\MarketerInfluencerFeeCountrySetting::where('country_id', $countryId)->first();

        $currency = Country::find($countryId)?->currency_code ?? '';

        return response()->json([
            'fee_per_influencer' => $feeSetting?->fee_per_influencer ?? 0,
            'currency'           => $currency,
        ]);
    }

    public function slugPreview(string $product, string $variant): JsonResponse
    {
        $productModel = Product::query()->whereNull('deleted_at')->findOrFail($product);

        $variantModel = ProductVariant::query()
            ->where('id', $variant)
            ->where('product_id', $product)
            ->whereNull('deleted_at')
            ->firstOrFail();

        return response()->json([
            'variant_slug' => $variantModel->slug,
            'product_slug' => $productModel->slug,
            'preview_url' => "/products/{$productModel->slug}/{$variantModel->slug}?listing=preview",
            'attribute_summary' => $variantModel->attributeSummary(),
        ]);
    }

    /**
     * Read-only "customer URL" info for a product variant, shown live in the
     * create/edit form so vendors can confirm which variant they picked
     * before the listing (and its final URL) is saved.
     */
    public function variantUrlInfo(string $variant): JsonResponse
    {
        $variantModel = ProductVariant::query()
            ->where('id', $variant)
            ->whereNull('deleted_at')
            ->with('product')
            ->firstOrFail();

        $attributes = $variantModel->variantAttributeValues()
            ->with('attribute')
            ->get()
            ->map(fn ($value) => [
                'name' => $value->attribute?->name_en,
                'value' => $value->value_en,
            ]);

        return response()->json([
            'variant_id' => $variantModel->id,
            'variant_name' => $variantModel->variant_name,
            'product_slug' => $variantModel->product?->slug,
            'product_name_en' => $variantModel->product?->name_en,
            'attribute_summary' => $attributes
                ->map(fn ($attr) => "{$attr['name']}: {$attr['value']}")
                ->implode(' | '),
            'preview_url' => "/products/{$variantModel->id}/(new listing — ID assigned after save)",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Warehouses by Country (AJAX — for create form)
    // ─────────────────────────────────────────────────────────────────────────

    private function warehouseMatchesFulfillmentModel(Warehouse $warehouse, string $fulfillmentModel, $vendor): bool
    {
        return match ($fulfillmentModel) {
            'fbm' => $warehouse->type === WarehouseType::SellerOwned && $warehouse->owner_vendor_id === $vendor->id,
            'fbn' => $warehouse->type === WarehouseType::PlatformFbn,
            'cross_dock' => $warehouse->type === WarehouseType::ThirdParty,
            default => false,
        };
    }

    public function warehousesByCountry(Request $request): JsonResponse
    {
        $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'fulfillment_model' => ['nullable', 'in:fbm,fbn,cross_dock'],
        ]);

        $vendor = $this->vendor();

        $warehouses = Warehouse::where('is_active', true)
            ->where('country_id', $request->country_id)
            ->where(function ($q) use ($vendor) {
                $q->whereNull('owner_vendor_id')
                    ->orWhere('owner_vendor_id', $vendor->id);
            })
            ->when($request->fulfillment_model, function ($q) use ($request, $vendor) {
                match ($request->fulfillment_model) {
                    'fbm' => $q->where('type', WarehouseType::SellerOwned)
                        ->where('owner_vendor_id', $vendor->id),
                    'fbn' => $q->where('type', WarehouseType::PlatformFbn),
                    'cross_dock' => $q->where('type', WarehouseType::ThirdParty),
                };
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);

        return response()->json($warehouses->map(fn($w) => [
            'id' => $w->id,
            'name' => $w->name,
            'code' => $w->code,
            'type' => $w->type->value,
        ]));
    }

    public function availableShippingMethods(Request $request): JsonResponse
    {
        $request->validate([
            'variant_id' => ['required', 'uuid', 'exists:product_variants,id'],
            'fulfillment_model' => ['nullable', 'in:fbm,fbn,cross_dock'],
        ]);

        $methods = $this->shippingResolver->resolveForVariant(
            $request->variant_id,
            $request->fulfillment_model ?? 'fbm',
        );

        return response()->json($methods->map(fn($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'code' => $m->code,
            'badge_label_en' => $m->badge_label_en,
            'badge_color_hex' => $m->badge_color_hex,
            'is_default' => (bool) $m->pivot->is_default,
        ])->values());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────────

    public function show(VendorListing $listing): View
    {
        $this->authoriseListing($listing);

        $listing->load([
            'productVariant.product.category',
            'productVariant.product.images',
            'warehouseInventories.warehouse',
            'country',
            'primaryShippingMethod',
        ]);

        // Last 20 inventory movements for all warehouse inventories of this listing
        $inventoryIds = $listing->warehouseInventories->pluck('id');
        $movements = InventoryMovement::whereIn('warehouse_inventory_id', $inventoryIds)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $availableShippingMethods = $this->shippingResolver->resolveForListing($listing);
        $defaultShippingMethod = $listing->primary_shipping_method_id
            ? null
            : $this->shippingResolver->resolvePrimary($listing);

        $marketerCampaign = \App\Models\MarketerCampaign::where('vendor_listing_id', $listing->id)
            ->with([
                'invitations.marketer',
                'tieredRules',
                'conversions',
            ])
            ->latest()
            ->first();

        return view('partner.listings.show', compact('listing', 'movements', 'availableShippingMethods', 'defaultShippingMethod', 'marketerCampaign'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Clear Cache
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /partner/listings/{listing}/clear-cache
     * Vendors can clear their own listing's customer-facing cache.
     */
    public function clearCache(VendorListing $listing, PageCacheService $pageCache): JsonResponse
    {
        // Scope to this vendor only
        abort_unless($listing->vendor_id === $this->vendor()->id, 403);

        $pageCache->bustVendorListing($listing);

        return response()->json([
            'success' => true,
            'message' => 'Listing cache cleared. Changes are now live in the customer app.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        $vendor = $this->vendor();
        $countryId = $vendor->country_id;

        // Warehouses in vendor's country (active, either platform or vendor-owned)
        $warehouses = Warehouse::where('is_active', true)
            ->where(function ($q) use ($vendor, $countryId) {
                $q->where('country_id', $countryId)
                    ->where(function ($sq) use ($vendor) {
                        $sq->whereNull('owner_vendor_id')
                            ->orWhere('owner_vendor_id', $vendor->id);
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);

        $countries = Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'currency_code']);

        $fulfillmentModels = [
            'fbm' => 'FBM — الشحن الذاتي',
            'fbn' => 'FBN — التخزين في مستودعاتنا',
            'cross_dock' => 'Cross Dock — شحن جزئي',
        ];

        $conditions = [
            'new' => 'جديد',
            'like_new' => 'كالجديد',
            'good' => 'جيد',
            'acceptable' => 'مقبول',
            'refurbished' => 'مُجدَّد',
        ];

        $marketerVendors = \App\Models\Marketer::where('global_status', 'active')
            ->where('country_id', $countryId)
            ->orderBy('name')
            ->get(['id', 'name', 'marketer_type']);

        return view('partner.listings.create', compact(
            'warehouses',
            'countries',
            'fulfillmentModels',
            'conditions',
            'countryId',
            'marketerVendors'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Store
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $vendor = $this->vendor();

        $request->validate([
            'product_variant_id' => ['nullable', 'required_without:product_id', 'uuid', 'exists:product_variants,id'],
            'product_id' => ['nullable', 'required_without:product_variant_id', 'uuid', 'exists:products,id'],
            'country_id' => ['required', 'exists:countries,id'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'condition' => ['required', 'in:new,like_new,good,acceptable,refurbished'],
            'fulfillment_model' => ['required', 'in:fbm,fbn,cross_dock'],
            'vendor_sku' => ['nullable', 'string', 'max:100'],
            'vendor_notes' => ['nullable', 'string', 'max:1000'],
            'max_order_quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'initial_quantity' => ['required', 'integer', 'min:0', 'max:99999'],
            'vendor_covers_delivery' => ['nullable', 'boolean'],
            'influencer_commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'affiliate_commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'influencer_sample_quota' => ['nullable', 'integer', 'min:0', 'max:9999', 'required_with:influencer_commission_percentage'],
            'affiliate_sample_quota' => ['nullable', 'integer', 'min:0', 'max:9999', 'required_with:affiliate_commission_percentage'],
            'declared_weight_grams' => ['required', 'integer', 'min:1'],
            'declared_length_cm' => ['nullable', 'numeric', 'min:0.1'],
            'declared_width_cm' => ['nullable', 'numeric', 'min:0.1'],
            'declared_height_cm' => ['nullable', 'numeric', 'min:0.1'],
            'handling_class' => ['required', 'in:' . implode(',', self::HANDLING_CLASSES)],
            'primary_shipping_method_id' => ['nullable', 'uuid', 'exists:shipping_methods,id'],
            'campaign_enabled' => ['nullable', 'boolean'],
            'commission_type' => ['nullable', 'required_if:campaign_enabled,1', 'in:fixed,tiered,last_click'],
            'max_commission_budget' => ['nullable', 'numeric', 'min:0'],
            'marketer_ids' => ['nullable', 'array', 'required_if:campaign_enabled,1'],
            'marketer_ids.*' => ['uuid', 'distinct', 'exists:marketers,id'],
            'tiered_rules' => ['nullable', 'array'],
            'tiered_rules.*.from_sale_number' => ['nullable', 'integer', 'min:1'],
            'tiered_rules.*.commission_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $warehouse = Warehouse::findOrFail($request->warehouse_id);
        if (!$this->warehouseMatchesFulfillmentModel($warehouse, $request->fulfillment_model, $vendor)) {
            return response()->json([
                'success' => false,
                'message' => 'المستودع المختار غير متوافق مع نموذج التنفيذ المحدد.',
            ], 422);
        }

        // Resolve product_variant_id — either sent directly (has_variants=1) or resolved
        // from the product's sole default variant (has_variants=0).
        if ($request->filled('product_variant_id')) {
            $resolvedVariantId = $request->product_variant_id;
        } else {
            $product = Product::findOrFail($request->product_id);
            if ($product->has_variants) {
                return response()->json([
                    'success' => false,
                    'message' => 'يرجى اختيار نسخة محددة من هذا المنتج.',
                ], 422);
            }
            $defaultVariant = $product->variants()
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();
            if (!$defaultVariant) {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا المنتج غير متاح للبيع حالياً — يرجى التواصل مع الإدارة.',
                ], 422);
            }
            $resolvedVariantId = $defaultVariant->id;
        }

        if ($request->filled('primary_shipping_method_id')) {
            $availableMethods = $this->shippingResolver->resolveForVariant($resolvedVariantId, $request->fulfillment_model);
            if (!$availableMethods->contains('id', $request->primary_shipping_method_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'طريقة الشحن المختارة غير متاحة لهذه الفئة.',
                ], 422);
            }
        }

        $vendorId = $this->vendorId();

        // Prevent duplicate active listing for same vendor + variant + country
        $existing = VendorListing::where('vendor_id', $vendorId)
            ->where('product_variant_id', $resolvedVariantId)
            ->where('country_id', $request->country_id)
            ->whereNotIn('status', ['archived'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'لديك بالفعل قائمة نشطة لهذا المنتج في هذا البلد.',
            ], 422);
        }

        // Established vendor (> 10 orders) gets active status directly
        $status = ($vendor->total_orders >= 10) ? VendorListingStatus::Active->value : VendorListingStatus::PendingReview->value;

        if ($status === VendorListingStatus::Active->value) {
            $productIdForCertCheck = ProductVariant::find($resolvedVariantId)?->product_id;

            try {
                ListingCertificationGate::assertCanGoLiveFor($vendorId, $productIdForCertCheck, $request->country_id);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $status = VendorListingStatus::PendingReview->value;
            }
        }

        $currency = Country::find($request->country_id)?->currency_code ?? '';

        // Pre-fill dimensions from the product variant when the vendor left them blank
        // (but never overwrite what the vendor explicitly entered).
        $variantForDefaults = \App\Models\ProductVariant::find($resolvedVariantId);
        $length = $request->filled('declared_length_cm') ? (float) $request->declared_length_cm : $variantForDefaults?->length_cm;
        $width = $request->filled('declared_width_cm') ? (float) $request->declared_width_cm : $variantForDefaults?->width_cm;
        $height = $request->filled('declared_height_cm') ? (float) $request->declared_height_cm : $variantForDefaults?->height_cm;

        $billable = $this->weightService->billableWeightGrams(
            (int) $request->declared_weight_grams,
            $length !== null ? (float) $length : null,
            $width !== null ? (float) $width : null,
            $height !== null ? (float) $height : null,
        );
        $weightClass = $this->weightService->classifyWeight($billable);

        // try {
        $listing = null;
        DB::transaction(function () use ($request, $vendorId, $status, $currency, &$listing, $vendor, $resolvedVariantId, $length, $width, $height, $weightClass) {
            $listing = VendorListing::create([
                'id' => (string) Str::uuid(),
                'vendor_id' => $vendorId,
                'product_variant_id' => $resolvedVariantId,
                'country_id' => $request->country_id,
                'price' => (int) round((float) $request->price),
                'currency' => $currency,
                'condition' => $request->condition,
                'fulfillment_model' => $request->fulfillment_model,
                'vendor_sku' => $request->vendor_sku,
                'vendor_notes' => $request->vendor_notes,
                'status' => $status,
                'max_order_quantity' => $request->max_order_quantity,
                'low_stock_threshold' => $request->low_stock_threshold ?? 5,
                'vendor_covers_delivery' => $request->boolean('vendor_covers_delivery'),
                'influencer_commission_percentage' => $request->influencer_commission_percentage,
                'affiliate_commission_percentage' => $request->affiliate_commission_percentage,
                'influencer_sample_quota' => $request->influencer_sample_quota,
                'affiliate_sample_quota' => $request->affiliate_sample_quota,
                'declared_weight_grams' => (int) $request->declared_weight_grams,
                'declared_length_cm' => $length,
                'declared_width_cm' => $width,
                'declared_height_cm' => $height,
                'handling_class' => $request->handling_class,
                'weight_class' => $weightClass,
                'primary_shipping_method_id' => $request->primary_shipping_method_id ?: null,
            ]);

            // Create warehouse inventory record
            $inventory = WarehouseInventory::create([
                'id' => (string) Str::uuid(),
                'vendor_listing_id' => $listing->id,
                'warehouse_id' => $request->warehouse_id,
                'quantity_on_hand' => $request->initial_quantity,
                'quantity_reserved' => 0,
                'quantity_inbound' => 0,
                'quantity_damaged' => 0,
            ]);

            // Initial stock movement
            if ((int) $request->initial_quantity > 0) {
                InventoryMovement::create([
                    'warehouse_inventory_id' => $inventory->id,
                    'movement_type' => InventoryMovementType::Inbound->value,
                    'quantity_delta' => (int) $request->initial_quantity,
                    'quantity_after' => (int) $request->initial_quantity,
                    'reference_type' => InventoryMovementReferenceType::InboundShipment->value,
                    'reference_id' => $listing->id,
                    'reason' => 'initial_stock',
                    'created_by_user_id' => Auth::guard('vendor')->user()->id,
                ]);
            }
        });
        // } catch (\Throwable $e) {
        //     Log::error('ListingController::store failed', ['error' => $e->getMessage()]);
        //     return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء إنشاء القائمة. يرجى المحاولة مرة أخرى.'], 500);
        // }

        $message = $status === VendorListingStatus::Active->value ? 'تم إنشاء القائمة بنجاح وهي نشطة الآن.' : 'تم إنشاء القائمة وهي قيد المراجعة.';

        if ($request->boolean('campaign_enabled')) {
            try {
                $this->marketerCampaignService->createCampaign($vendor, array_merge($request->only([
                    'commission_type', 'max_commission_budget',
                ]), [
                    'vendor_listing_id'   => $listing->id,
                    'country_id'          => $listing->country_id,
                    'currency'            => $listing->currency,
                    'marketer_ids' => $request->input('marketer_ids', []),
                    'tiered_rules'        => $request->input('tiered_rules', []),
                ]));

                $message .= ' تم إنشاء حملة الماركتر بنجاح.';
            } catch (\Throwable $e) {
                Log::warning('Listing created but marketer campaign failed', ['listing_id' => $listing->id, 'error' => $e->getMessage()]);
                $message .= ' تعذر إنشاء حملة الماركتر: ' . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'redirect' => Route::has('partner.listings.show') ? route('partner.listings.show', $listing->id) : route('partner.listings.index'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(VendorListing $listing): View
    {
        $this->authoriseListing($listing);

        $listing->load([
            'productVariant.product.category',
            'productVariant.product.images',
            'warehouseInventories.warehouse',
            'country',
        ]);

        $fulfillmentModels = [
            'fbm' => 'FBM — الشحن الذاتي',
            'fbn' => 'FBN — التخزين في مستودعاتنا',
            'cross_dock' => 'Cross Dock — شحن جزئي',
        ];

        $conditions = [
            'new' => 'جديد',
            'like_new' => 'كالجديد',
            'good' => 'جيد',
            'acceptable' => 'مقبول',
            'refurbished' => 'مُجدَّد',
        ];

        $availableShippingMethods = $this->shippingResolver->resolveForListing($listing);

        $vendor = $this->vendor();
        $marketerVendors = \App\Models\Marketer::where('global_status', 'active')
            ->where('country_id', $listing->country_id)
            ->orderBy('name')
            ->get(['id', 'name', 'marketer_type']);

        $productId = $listing->productVariant->product_id;
        $requiresLocalCert = \App\Models\ProductCountry::where('product_id', $productId)
            ->where('country_id', $listing->country_id)
            ->where('requires_local_cert', 1)
            ->exists();

        $hasApprovedCert = $requiresLocalCert && \App\Models\VendorProductCertification::where('vendor_id', $vendor->id)
            ->where('product_id', $productId)
            ->where('country_id', $listing->country_id)
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        $missingCertification = $requiresLocalCert && ! $hasApprovedCert;

        return view('partner.listings.edit', compact('listing', 'fulfillmentModels', 'conditions', 'availableShippingMethods', 'marketerVendors', 'missingCertification'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, VendorListing $listing): RedirectResponse
    {
        $this->authoriseListing($listing);

        if ($listing->status === VendorListingStatus::Active) {
            return back()->withErrors(['status' => 'يرجى إيقاف القائمة مؤقتاً قبل تعديلها.'])->withInput();
        }

        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'condition' => ['required', 'in:new,like_new,good,acceptable,refurbished'],
            'fulfillment_model' => ['required', 'in:fbm,fbn,cross_dock'],
            'vendor_sku' => ['nullable', 'string', 'max:100'],
            'vendor_notes' => ['nullable', 'string', 'max:1000'],
            'max_order_quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'vendor_covers_delivery' => ['nullable', 'boolean'],
            'influencer_commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'affiliate_commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'influencer_sample_quota' => ['nullable', 'integer', 'min:0', 'max:9999', 'required_with:influencer_commission_percentage'],
            'affiliate_sample_quota' => ['nullable', 'integer', 'min:0', 'max:9999', 'required_with:affiliate_commission_percentage'],
            'declared_weight_grams' => ['required', 'integer', 'min:1'],
            'declared_length_cm' => ['nullable', 'numeric', 'min:0.1'],
            'declared_width_cm' => ['nullable', 'numeric', 'min:0.1'],
            'declared_height_cm' => ['nullable', 'numeric', 'min:0.1'],
            'handling_class' => ['required', 'in:' . implode(',', self::HANDLING_CLASSES)],
            'primary_shipping_method_id' => ['nullable', 'uuid', 'exists:shipping_methods,id'],
            'campaign_enabled' => ['nullable', 'boolean'],
            'commission_type' => ['nullable', 'required_if:campaign_enabled,1', 'in:fixed,tiered,last_click'],
            'max_commission_budget' => ['nullable', 'numeric', 'min:0'],
            'marketer_ids' => ['nullable', 'array', 'required_if:campaign_enabled,1'],
            'marketer_ids.*' => ['uuid', 'distinct', 'exists:marketers,id'],
            'tiered_rules' => ['nullable', 'array'],
            'tiered_rules.*.from_sale_number' => ['nullable', 'integer', 'min:1'],
            'tiered_rules.*.commission_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (!empty($validated['primary_shipping_method_id'])) {
            $availableMethods = $this->shippingResolver->resolveForListing($listing);
            if (!$availableMethods->contains('id', $validated['primary_shipping_method_id'])) {
                return back()->withErrors(['primary_shipping_method_id' => 'طريقة الشحن المختارة غير متاحة لهذه الفئة.'])->withInput();
            }
        }

        $billable = $this->weightService->billableWeightGrams(
            (int) $validated['declared_weight_grams'],
            isset($validated['declared_length_cm']) ? (float) $validated['declared_length_cm'] : null,
            isset($validated['declared_width_cm']) ? (float) $validated['declared_width_cm'] : null,
            isset($validated['declared_height_cm']) ? (float) $validated['declared_height_cm'] : null,
        );
        $weightClass = $this->weightService->classifyWeight($billable);

        DB::transaction(function () use ($listing, $validated, $request, $weightClass) {
            $listing->update([
                'price' => (int) round((float) $validated['price']),
                'condition' => $validated['condition'],
                'fulfillment_model' => $validated['fulfillment_model'],
                'vendor_sku' => $validated['vendor_sku'] ?? null,
                'vendor_notes' => $validated['vendor_notes'] ?? null,
                'max_order_quantity' => $validated['max_order_quantity'] ?? null,
                'low_stock_threshold' => $validated['low_stock_threshold'] ?? 5,
                'vendor_covers_delivery' => $request->boolean('vendor_covers_delivery'),
                'influencer_commission_percentage' => $validated['influencer_commission_percentage'] ?? null,
                'affiliate_commission_percentage' => $validated['affiliate_commission_percentage'] ?? null,
                'influencer_sample_quota' => $validated['influencer_sample_quota'] ?? null,
                'affiliate_sample_quota' => $validated['affiliate_sample_quota'] ?? null,
                'declared_weight_grams' => (int) $validated['declared_weight_grams'],
                'declared_length_cm' => $validated['declared_length_cm'] ?? null,
                'declared_width_cm' => $validated['declared_width_cm'] ?? null,
                'primary_shipping_method_id' => $validated['primary_shipping_method_id'] ?? null,
                'declared_height_cm' => $validated['declared_height_cm'] ?? null,
                'handling_class' => $validated['handling_class'],
                'weight_class' => $weightClass,
            ]);
        });

        $successMessage = 'تم تحديث القائمة بنجاح.';

        if ($request->boolean('campaign_enabled')) {
            try {
                $this->marketerCampaignService->createCampaign($this->vendor(), array_merge($request->only([
                    'commission_type', 'max_commission_budget',
                ]), [
                    'vendor_listing_id'   => $listing->id,
                    'country_id'          => $listing->country_id,
                    'currency'            => $listing->currency,
                    'marketer_ids' => $request->input('marketer_ids', []),
                    'tiered_rules'        => $request->input('tiered_rules', []),
                ]));

                $successMessage .= ' تم إنشاء حملة الماركتر بنجاح.';
            } catch (\Throwable $e) {
                Log::warning('Listing updated but marketer campaign failed', ['listing_id' => $listing->id, 'error' => $e->getMessage()]);
                $successMessage .= ' تعذر إنشاء حملة الماركتر: ' . $e->getMessage();
            }
        }

        return redirect()->route('partner.listings.show', $listing)->with('success', $successMessage);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resubmit (after rejection)
    // ─────────────────────────────────────────────────────────────────────────

    public function resubmit(VendorListing $listing): RedirectResponse
    {
        $this->authoriseListing($listing);

        if ($listing->status !== VendorListingStatus::Rejected) {
            return back()->withErrors(['status' => 'لا يمكن إعادة تقديم هذه القائمة.']);
        }

        $listing->update([
            'status' => VendorListingStatus::PendingReview,
            'rejection_reason' => null,
        ]);

        Notification::send(
            Admin::where('status', 'active')->get(),
            new ListingResubmittedNotification($listing),
        );

        return redirect()->route('partner.listings.show', $listing)->with('success', 'تم إعادة تقديم القائمة للمراجعة.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update Price
    // ─────────────────────────────────────────────────────────────────────────

    public function updatePrice(Request $request, VendorListing $listing): JsonResponse
    {
        $this->authoriseListing($listing);

        $request->validate([
            'price' => ['required', 'numeric', 'min:0.01', 'max:999999'],
        ]);

        $listing->update([
            'price' => (int) round((float) $request->price),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث السعر بنجاح.',
            'price_formatted' => number_format($listing->price, 2),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update Shipping Method
    // ─────────────────────────────────────────────────────────────────────────

    public function updateShipping(Request $request, VendorListing $listing): JsonResponse
    {
        $this->authoriseListing($listing);

        $request->validate([
            'primary_shipping_method_id' => ['required', 'uuid', 'exists:shipping_methods,id'],
        ]);

        $availableMethods = $this->shippingResolver->resolveForListing($listing);

        if (!$availableMethods->contains('id', $request->primary_shipping_method_id)) {
            return response()->json([
                'success' => false,
                'message' => 'طريقة الشحن هذه غير متاحة لهذه القائمة.',
            ], 422);
        }

        $listing->update(['primary_shipping_method_id' => $request->primary_shipping_method_id]);

        $method = $availableMethods->firstWhere('id', $request->primary_shipping_method_id);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث طريقة الشحن بنجاح.',
            'shipping_method_name' => $method->name,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Toggle Status (active ↔ paused)
    // ─────────────────────────────────────────────────────────────────────────

    public function toggleStatus(VendorListing $listing): JsonResponse
    {
        $this->authoriseListing($listing);

        if (!in_array($listing->status, [VendorListingStatus::Active, VendorListingStatus::Paused], true)) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تغيير حالة هذه القائمة.',
            ], 422);
        }

        if ($listing->status === VendorListingStatus::Paused) {
            // Check if any stock available before activating
            $available = WarehouseInventory::where('vendor_listing_id', $listing->id)
                ->selectRaw('COALESCE(SUM(quantity_on_hand - quantity_reserved), 0) as total')
                ->value('total');

            if ((int) $available <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن تفعيل القائمة لأن المخزون المتاح صفر.',
                ], 422);
            }

            try {
                ListingCertificationGate::assertCanGoLive($listing);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا المنتج يتطلب شهادة اعتماد محلية معتمدة في هذا البلد قبل تفعيل القائمة. يرجى رفع الشهادة من قسم شهادات المنتجات.',
                ], 422);
            }
        }

        $newStatus = $listing->status === VendorListingStatus::Active ? VendorListingStatus::Paused : VendorListingStatus::Active;
        $listing->update(['status' => $newStatus]);

        $statusLabels = [VendorListingStatus::Active->value => 'نشط', VendorListingStatus::Paused->value => 'موقوف مؤقتاً'];

        return response()->json([
            'success' => true,
            'new_status' => $newStatus->value,
            'label' => $statusLabels[$newStatus->value] ?? $newStatus->value,
            'message' => $newStatus === VendorListingStatus::Active ? 'تم تفعيل القائمة.' : 'تم إيقاف القائمة مؤقتاً.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Toggle Vendor Covers Delivery (FBP only)
    // ─────────────────────────────────────────────────────────────────────────

    public function toggleCoversDelivery(Request $request, VendorListing $listing): JsonResponse
    {
        $this->authoriseListing($listing);

        if ($listing->global_system_type !== \App\Enums\GlobalSystemType::MerchantFbp) {
            return response()->json([
                'success' => false,
                'message' => 'هذا الخيار متاح فقط لقوائم FBP.',
            ], 422);
        }

        $request->validate([
            'vendor_covers_delivery' => ['required', 'boolean'],
        ]);

        $listing->update(['vendor_covers_delivery' => $request->boolean('vendor_covers_delivery')]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث إعداد تغطية الشحن.',
            'vendor_covers_delivery' => $listing->vendor_covers_delivery,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Adjust Stock
    // ─────────────────────────────────────────────────────────────────────────

    public function adjustStock(Request $request, VendorListing $listing): JsonResponse
    {
        $this->authoriseListing($listing);

        $request->validate([
            'warehouse_inventory_id' => ['required', 'exists:warehouse_inventories,id'],
            'adjustment' => ['required', 'integer', 'between:-99999,99999', 'not_in:0'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        try {
            $result = DB::transaction(function () use ($request, $listing) {
                $inventory = WarehouseInventory::lockForUpdate()
                    ->where('id', $request->warehouse_inventory_id)
                    ->where('vendor_listing_id', $listing->id)
                    ->firstOrFail();

                $newOnHand = $inventory->quantity_on_hand + (int) $request->adjustment;

                if ($newOnHand < 0) {
                    throw new \InvalidArgumentException('لا يمكن أن يكون المخزون بالسالب.');
                }

                $inventory->update(['quantity_on_hand' => $newOnHand]);

                InventoryMovement::create([
                    'warehouse_inventory_id' => $inventory->id,
                    'movement_type' => 'adjustment',
                    'quantity_delta' => (int) $request->adjustment,
                    'quantity_after' => $newOnHand,
                    'reference_type' => 'adjustment',
                    'reference_id' => $listing->id,
                    'reason' => $request->reason,
                    'created_by_user_id' => Auth::guard('vendor')->user()->id,
                ]);

                return $newOnHand;
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('adjustStock failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'حدث خطأ أثناء التعديل.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل المخزون بنجاح.',
            'new_quantity' => $result,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update Shipping Dimensions / Weight / Handling Class
    // ─────────────────────────────────────────────────────────────────────────

    public function updateDimensions(Request $request, VendorListing $listing): JsonResponse
    {
        $this->authoriseListing($listing);

        $request->validate([
            'declared_weight_grams' => ['required', 'integer', 'min:1'],
            'declared_length_cm' => ['nullable', 'numeric', 'min:0.1'],
            'declared_width_cm' => ['nullable', 'numeric', 'min:0.1'],
            'declared_height_cm' => ['nullable', 'numeric', 'min:0.1'],
            'handling_class' => ['required', 'in:' . implode(',', self::HANDLING_CLASSES)],
            'vendor_covers_delivery' => ['nullable', 'boolean'],
        ]);

        $billable = $this->weightService->billableWeightGrams(
            (int) $request->declared_weight_grams,
            $request->declared_length_cm !== null ? (float) $request->declared_length_cm : null,
            $request->declared_width_cm !== null ? (float) $request->declared_width_cm : null,
            $request->declared_height_cm !== null ? (float) $request->declared_height_cm : null,
        );

        $listing->update([
            'declared_weight_grams' => (int) $request->declared_weight_grams,
            'declared_length_cm' => $request->declared_length_cm,
            'declared_width_cm' => $request->declared_width_cm,
            'declared_height_cm' => $request->declared_height_cm,
            'handling_class' => $request->handling_class,
            'weight_class' => $this->weightService->classifyWeight($billable),
            'vendor_covers_delivery' => $request->has('vendor_covers_delivery')
                ? $request->boolean('vendor_covers_delivery')
                : $listing->vendor_covers_delivery,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات الشحن.',
            'weight_class' => $listing->weight_class,
            'billable_weight_grams' => $billable,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shipping / Subsidy Preview (per zone)
    // ─────────────────────────────────────────────────────────────────────────

    public function shippingPreview(VendorListing $listing): JsonResponse
    {
        $this->authoriseListing($listing);

        $listing->load('productVariant.product.category');

        $billable = $this->weightService->billableWeightGrams(
            (int) ($listing->declared_weight_grams ?? $listing->productVariant?->weight_grams ?? 0),
            $listing->declared_length_cm !== null ? (float) $listing->declared_length_cm : null,
            $listing->declared_width_cm !== null ? (float) $listing->declared_width_cm : null,
            $listing->declared_height_cm !== null ? (float) $listing->declared_height_cm : null,
        );

        $methods = $this->shippingResolver->resolveForListing($listing);
        $currency = $listing->currency;

        $zones = ShippingZone::where('country_id', $listing->country_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($zones as $zone) {
            foreach ($methods as $method) {
                $rate = \App\Models\ShippingRate::where('destination_zone_id', $zone->id)
                    ->where('shipping_method_id', $method->id)
                    ->whereNull('origin_zone_id')
                    ->where('is_active', true)
                    ->orderBy('base_fee')
                    ->first();

                if (!$rate) {
                    continue;
                }

                $rawFee = $this->weightService->computeRawShippingFee($rate, $billable);

                $subsidy = PlatformShippingSubsidy::where('shipping_zone_id', $zone->id)
                    ->where('shipping_method_id', $method->id)
                    ->where('is_active', true)
                    ->first();

                $subsidyCap = 0;
                if ($subsidy) {
                    if ($subsidy->max_subsidy_weight_grams !== null && $billable > $subsidy->max_subsidy_weight_grams) {
                        $subsidizableFee = $this->weightService->computeRawShippingFee($rate, $subsidy->max_subsidy_weight_grams);
                        $subsidyCap = min($subsidy->subsidy_cap, $subsidizableFee);
                    } else {
                        $subsidyCap = min($subsidy->subsidy_cap, $rawFee);
                    }
                }

                $customerPays = max(0, $rawFee - $subsidyCap);
                $vendorContribution = 0;

                if ($listing->vendor_covers_delivery && $customerPays > 0) {
                    $vendorContribution = $customerPays;
                    $customerPays = 0;
                }

                $result[] = [
                    'zone_name' => $zone->name,
                    'method_name' => $method->name,
                    'raw_fee' => $rawFee,
                    'platform_subsidy' => $subsidyCap,
                    'vendor_contribution' => $vendorContribution,
                    'customer_pays' => $customerPays,
                    'is_free_for_customer' => $customerPays === 0,
                    'currency' => $currency,
                    'billable_weight_grams' => $billable,
                ];
            }
        }

        return response()->json($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Marketer Campaign
    // ─────────────────────────────────────────────────────────────────────────

    public function searchMarketers(Request $request): JsonResponse
    {
        $vendor = $this->vendor();
        $q = $request->input('q', '');

        $marketers = \App\Models\Marketer::where('global_status', 'active')
            ->where('country_id', $vendor->country_id)
            ->when($q, fn ($query, $q) => $query->where('name', 'like', "%{$q}%"))
            ->limit(20)
            ->get(['id', 'name', 'marketer_type']);

        return response()->json(
            $marketers->map(fn ($m) => [
                'id'   => $m->id,
                'text' => $m->name . ' — ' . ($m->marketer_type === 'influencer' ? 'مؤثر' : 'أفلييت'),
            ])
        );
    }
}
