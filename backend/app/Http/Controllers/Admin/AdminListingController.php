<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminListingStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminListing;
use App\Enums\MarketplaceShippingRuleCommissionType;
use App\Models\Country;
use App\Models\MarketplaceShippingRule;
use App\Models\ProductCostReference;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Enums\WarehouseType;
use App\Models\Warehouse;
use App\Services\ShippingMethodResolverService;
use App\Services\Shared\PageCacheService;
use App\Models\WarehouseInventory;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminListingController extends Controller
{
    use HasDataTable;

    public function index(Request $request): View
    {
        $query = AdminListing::query()
            ->with(['productVariant.product.images', 'country', 'warehouseInventory'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->input('search');
                $q->whereHas('productVariant.product', function ($pq) use ($term) {
                    $pq->where('name_en', 'like', "%{$term}%")
                        ->orWhere('name_ar', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('country_id'), fn($q) => $q->where('country_id', $request->input('country_id')))
            ->when($request->filled('status') && $request->input('status') !== 'all',
                fn($q) => $q->where('status', $request->input('status')))
            ->when($request->boolean('daily_deal'), fn($q) => $q->where('is_daily_deal', true))
            ->latest('created_at');

        return view('admin.admin-listings.index', [
            'listings' => $query->paginate(25)->withQueryString(),
            'countries' => Country::query()->orderBy('name_en')->pluck('name_en', 'id'),
            'statuses' => collect(AdminListingStatus::cases())
                ->mapWithKeys(fn($status) => [$status->value => Str::headline($status->value)]),
            'stats' => $this->buildStats(),
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.admin_listings.title')],
            ],
        ]);
    }

    /** Quick stats block for the admin listings index page. */
    private function buildStats(): array
    {
        $activeListings = AdminListing::where('status', AdminListingStatus::Active)->count();

        $countriesActive = AdminListing::where('status', AdminListingStatus::Active)
            ->distinct()
            ->count('country_id');

        $revenueByCurrency = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('order_items.admin_listing_id')
            ->select('orders.currency', DB::raw('SUM(order_items.line_total) as total'))
            ->groupBy('orders.currency')
            ->orderByDesc('total')
            ->get();

        $topSelling = DB::table('order_items')
            ->join('admin_listings as apl', 'apl.id', '=', 'order_items.admin_listing_id')
            ->join('product_variants as pv', 'pv.id', '=', 'apl.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->whereNotNull('order_items.admin_listing_id')
            ->select('p.name_en', DB::raw('SUM(order_items.quantity) as units_sold'))
            ->groupBy('p.name_en')
            ->orderByDesc('units_sold')
            ->first();

        return [
            'active_listings' => $activeListings,
            'countries_active' => $countriesActive,
            'revenue_by_currency' => $revenueByCurrency,
            'top_selling' => $topSelling,
        ];
    }

    public function datatable(Request $request): JsonResponse
    {
        $columns = [
            ['searchable_columns' => ['p.name_en', 'p.name_ar', 'pv.sku'], 'orderable_column' => 'p.name_en'],
            ['orderable_column' => 'pv.variant_name'],
            ['searchable_columns' => ['co.name_en'], 'orderable_column' => 'co.name_en'],
            ['orderable_column' => 'admin_listings.currency'],
            ['orderable_column' => 'admin_listings.price'],
            ['orderable_column' => 'admin_listings.compare_at_price'],
            ['orderable_column' => 'admin_listings.status'],
            ['orderable_column' => 'admin_listings.rating_avg'],
            ['orderable_column' => 'admin_listings.total_sold'],
            ['orderable_column' => 'wh.name'],
            [],
            ['orderable_column' => 'admin_listings.created_at'],
            [],
        ];

        $query = AdminListing::query()
            ->with(['primaryShippingMethod'])
            ->join('product_variants as pv', 'pv.id', '=', 'admin_listings.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('countries as co', 'co.id', '=', 'admin_listings.country_id')
            ->leftJoin('warehouses as wh', 'wh.id', '=', 'admin_listings.warehouse_id')
            ->leftJoin('category_shipping_methods as csm', function ($join) {
                $join->on('csm.category_id', '=', 'p.category_id')->where('csm.is_default', true);
            })
            ->leftJoin('shipping_methods as dsm', 'dsm.id', '=', 'csm.shipping_method_id')
            ->whereNull('p.deleted_at')
            ->select([
                'admin_listings.id',
                'admin_listings.primary_shipping_method_id',
                'admin_listings.price',
                'admin_listings.compare_at_price',
                'admin_listings.currency',
                'admin_listings.status',
                'admin_listings.rating_avg',
                'admin_listings.rating_count',
                'admin_listings.total_sold',
                'admin_listings.created_at',
                'p.name_en as product_name',
                'pv.sku as variant_sku',
                'pv.variant_name as variant_name',
                'co.name_en as country_name',
                'wh.name as warehouse_name',
                'dsm.name as default_shipping_method_name',
                'dsm.badge_label_en as default_shipping_badge_label',
                'dsm.badge_color_hex as default_shipping_badge_color',
                'dsm.badge_text_color_hex as default_shipping_badge_text_color',
            ]);

        $query = $this->applyFilters($query, $request, [
            'country_id' => fn($q, $v) => $q->where('admin_listings.country_id', $v),
            'status' => fn($q, $v) => $q->where('admin_listings.status', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            $effectiveMethod = $row->primaryShippingMethod ?? ($row->default_shipping_method_name ? (object) [
                'name' => $row->default_shipping_method_name,
                'badge_label_en' => $row->default_shipping_badge_label,
                'badge_color_hex' => $row->default_shipping_badge_color,
                'badge_text_color_hex' => $row->default_shipping_badge_text_color,
            ] : null);

            return [
                'id' => $row->id,
                'shipping' => $effectiveMethod ? [
                    'label' => $effectiveMethod->badge_label_en ?: $effectiveMethod->name,
                    'color' => $effectiveMethod->badge_color_hex ?? '#e5e7eb',
                    'text_color' => $effectiveMethod->badge_text_color_hex ?? '#374151',
                    'is_inherited' => $row->primaryShippingMethod === null,
                ] : null,
                'product_name' => e($row->product_name),
                'variant_name' => e($row->variant_name),
                'variant_sku' => e($row->variant_sku),
                'country' => e($row->country_name),
                'currency' => e($row->currency),
                'price' => $row->price,
                'compare_at_price' => $row->compare_at_price,
                'status' => $row->status?->value,
                'rating_avg' => $row->rating_avg,
                'rating_count' => $row->rating_count,
                'total_sold' => $row->total_sold,
                'warehouse_name' => $row->warehouse_name ? e($row->warehouse_name) : null,
                'created_at' => $row->created_at?->format('Y-m-d H:i'),
                'show_url' => route('admin.admin-listings.show', $row->id),
                'edit_url' => route('admin.admin-listings.edit', $row->id),
                'delete_url' => route('admin.admin-listings.destroy', $row->id),
                'activate_url' => route('admin.admin-listings.activate', $row->id),
            ];
        });
    }

    /** Bulk status change (active/paused/archived) from the index table's selection bar. */
    public function bulkAction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['active', 'paused', 'archived'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string', 'exists:admin_listings,id'],
        ]);

        $count = AdminListing::query()
            ->whereIn('id', $data['ids'])
            ->update(['status' => $data['action']]);

        return response()->json([
            'success' => true,
            'message' => __('admin.admin_listings.bulk_action_updated', ['count' => $count]),
        ]);
    }

    public function create(): View
    {
        return view('admin.admin-listings.create', [
            'countries'       => Country::where('is_active', true)->orderBy('name_en')->get(),
            'warehouses'      => Warehouse::where('type', 'platform_fbn')->where('is_active', true)->orderBy('name')->get(),
            'shippingMethods' => ShippingMethod::where('is_active', true)->orderBy('name')->get(),
            'selectedVariant' => null,
            'breadcrumbs'     => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.admin_listings.title'), 'url' => route('admin.admin-listings.index')],
                ['label' => __('admin.admin_listings.new_listing')],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->storeRules());

        if (!empty($data['warehouse_id'])) {
            $warehouse = Warehouse::find($data['warehouse_id']);
            if (!$warehouse || $warehouse->type !== WarehouseType::PlatformFbn) {
                return back()->withErrors(['warehouse_id' => 'Only platform FBN warehouses are allowed.'])->withInput();
            }
        }

        $listing = DB::transaction(function () use ($data) {
            $listing = AdminListing::create(array_merge($data, [
                'currency'               => Country::findOrFail($data['country_id'])->currency_code,
                'created_by_admin_id'    => auth('admin')->id(),
            ]));

            if (!empty($listing->warehouse_id)) {
                WarehouseInventory::create([
                    'admin_listing_id'  => $listing->id,
                    'vendor_listing_id' => null,
                    'warehouse_id'      => $listing->warehouse_id,
                    'quantity_on_hand'  => 0,
                    'quantity_reserved' => 0,
                    'quantity_inbound'  => 0,
                    'quantity_damaged'  => 0,
                ]);
            }

            return $listing;
        });

        return redirect()
            ->route('admin.admin-listings.show', $listing)
            ->with('success', __('admin.admin_listings.created_success'));
    }

    public function edit(AdminListing $adminListing): View
    {
        return view('admin.admin-listings.edit', [
            'listing'         => $adminListing,
            'countries'       => Country::where('is_active', true)->orderBy('name_en')->get(),
            'warehouses'      => Warehouse::where('type', 'platform_fbn')->where('is_active', true)->orderBy('name')->get(),
            'shippingMethods' => ShippingMethod::where('is_active', true)->orderBy('name')->get(),
            'selectedVariant' => $adminListing->productVariant()->with('product')->first(),
            'breadcrumbs'     => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.admin_listings.title'), 'url' => route('admin.admin-listings.index')],
                ['label' => __('admin.admin_listings.edit_listing_title')],
            ],
        ]);
    }

    public function update(Request $request, AdminListing $adminListing): RedirectResponse
    {
        $data = $request->validate($this->updateRules());

        if (!empty($data['warehouse_id'])) {
            $warehouse = Warehouse::find($data['warehouse_id']);
            if (!$warehouse || $warehouse->type !== WarehouseType::PlatformFbn) {
                return back()->withErrors(['warehouse_id' => 'Only platform FBN warehouses are allowed.'])->withInput();
            }
        }

        $adminListing->update(array_merge($data, [
            'updated_by_admin_id' => auth('admin')->id(),
        ]));

        if (!empty($data['warehouse_id']) && !$adminListing->warehouseInventories()->exists()) {
            WarehouseInventory::create([
                'admin_listing_id'  => $adminListing->id,
                'vendor_listing_id' => null,
                'warehouse_id'      => $data['warehouse_id'],
                'quantity_on_hand'  => 0,
                'quantity_reserved' => 0,
                'quantity_inbound'  => 0,
                'quantity_damaged'  => 0,
            ]);
        }

        return redirect()
            ->route('admin.admin-listings.show', $adminListing)
            ->with('success', __('admin.admin_listings.updated_success'));
    }

    /** Toggles a listing between active and paused from the show/index quick actions. */
    public function toggleStatus(AdminListing $adminListing): RedirectResponse
    {
        $newStatus = $adminListing->status === AdminListingStatus::Active
            ? AdminListingStatus::Paused
            : AdminListingStatus::Active;

        $adminListing->update([
            'status'              => $newStatus,
            'updated_by_admin_id' => auth('admin')->id(),
        ]);

        return back()->with('success', 'Listing status updated.');
    }

    public function destroy(AdminListing $adminListing): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($adminListing) {
            $adminListing->warehouseInventory?->delete();
            $adminListing->delete();
        });

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.admin_listings.archived_msg')]);
        }

        return redirect()
            ->route('admin.admin-listings.index')
            ->with('success', __('admin.admin_listings.archived_msg'));
    }

    public function activate(AdminListing $adminListing): JsonResponse|RedirectResponse
    {
        $adminListing->status = AdminListingStatus::Active;
        $adminListing->save();

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => __('admin.admin_listings.activated_msg')]);
        }

        return redirect()
            ->route('admin.admin-listings.index')
            ->with('success', __('admin.admin_listings.activated_msg'));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Show (tabbed: listing details + confidential product reference)
    // ──────────────────────────────────────────────────────────────────────────

    public function show(AdminListing $adminListing): View
    {
        $adminListing->load([
            'productVariant.product.brand',
            'productVariant.product.category',
            'country',
            'warehouse',
            'primaryShippingMethod',
            'createdByAdmin',
            'warehouseInventories.warehouse',
            'reviews.customer',
            'marketerCampaigns',
            'flashSaleSubmissions.flashSale',
            'marketplaceShippingRule',
            'productCostReferences',
        ]);

        $productId = $adminListing->productVariant->product_id;
        $canViewCost = auth('admin')->user()?->hasPermissionTo('products.cost_data.view') ?? false;
        $costReference = $canViewCost ? ProductCostReference::where('product_id', $productId)->first() : null;

        $availableShippingMethods = app(ShippingMethodResolverService::class)
            ->getAvailableForListing($adminListing->id, 'admin_listing', $adminListing->country_id);

        return view('admin.admin-listings.show', [
            'listing' => $adminListing,
            'costReference' => $costReference,
            'canViewCost' => $canViewCost,
            'availableShippingMethods' => $availableShippingMethods,
            'categoryDefaultShippingMethod' => $adminListing->productVariant->product->category
                ?->defaultShippingMethod()->first(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'statuses' => collect(AdminListingStatus::cases())
                ->mapWithKeys(fn($status) => [$status->value => Str::headline($status->value)]),
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.admin_listings.title'), 'url' => route('admin.admin-listings.index')],
                ['label' => e($adminListing->productVariant->product->name_en)],
            ],
        ]);
    }

    /** Quick AJAX status change from the show page sidebar. */
    public function updateStatus(Request $request, AdminListing $adminListing): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(AdminListingStatus::class)],
        ]);

        $adminListing->update(['status' => $data['status']]);

        return response()->json([
            'success' => true,
            'message' => __('admin.admin_listings.status_updated'),
            'status' => $adminListing->status->value,
        ]);
    }

    /**
     * Adjust warehouse stock for this listing. Finds or creates the
     * warehouse_inventories row scoped to this admin listing + warehouse and
     * applies a delta to quantity_on_hand. quantity_available is a MySQL
     * GENERATED VIRTUAL column and is never written directly.
     */
    public function adjustStock(Request $request, AdminListing $adminListing): JsonResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'adjustment' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $inventory = WarehouseInventory::firstOrNew([
            'admin_listing_id' => $adminListing->id,
            'warehouse_id' => $data['warehouse_id'],
        ]);

        $inventory->quantity_on_hand = max(0, ($inventory->quantity_on_hand ?? 0) + $data['adjustment']);
        $inventory->save();

        return response()->json([
            'success' => true,
            'message' => __('admin.admin_listings.stock_adjusted'),
            'inventory' => $inventory->fresh('warehouse'),
        ]);
    }

    /**
     * Clear all API caches related to this admin listing:
     * - CachedListingResolver (buy-box resolution cache)
     * - Any page_block that references this listing directly
     * - Product listing caches (none stored — ProductQueryService is uncached)
     *
     * POST /admin/admin-listings/{adminListing}/clear-cache
     */
    public function clearCache(AdminListing $adminListing, PageCacheService $pageCache): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('admin_listings.edit'), 403);

        // Bust buy-box resolution cache
        app(\App\Services\CachedListingResolver::class)->bustAdminListing($adminListing);

        // Bust any page builder blocks that reference this listing
        $pageCache->bustAdminListingBlocks($adminListing);

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared for this listing.',
        ]);
    }

    /** Create or update the marketplace_shipping_rules row for this listing. */
    public function saveShippingRule(Request $request, AdminListing $adminListing): JsonResponse
    {
        $data = $request->validate([
            'requires_special_vehicle' => ['boolean'],
            'requires_refrigeration' => ['boolean'],
            'max_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'max_dimensions_cm' => ['nullable', 'string', 'max:100'],
            'special_handling_notes' => ['nullable', 'string', 'max:1000'],
            'commission_type' => ['required', Rule::enum(MarketplaceShippingRuleCommissionType::class)],
            'commission_value' => ['required', 'numeric', 'min:0'],
            'extra_delivery_fee' => ['nullable', 'integer', 'min:0'],
        ]);

        $rule = MarketplaceShippingRule::updateOrCreate(
            ['admin_listing_id' => $adminListing->id],
            $data
        );

        return response()->json([
            'success' => true,
            'message' => __('admin.admin_listings.shipping_rule_saved'),
            'rule' => $rule,
        ]);
    }

    /**
     * Save (create or update) the confidential product-cost reference for this
     * listing's underlying product. Independent of the main listing form.
     */
    public function saveReference(Request $request, AdminListing $adminListing): JsonResponse
    {
        $productId = $adminListing->productVariant->product_id;

        $data = $request->validate([
            'manufacturer_name' => ['nullable', 'string', 'max:255'],
            'manufacturer_url' => ['nullable', 'url', 'max:500'],
            'manufacturer_sku' => ['nullable', 'string', 'max:100'],
            'manufacturer_cost' => ['nullable', 'integer', 'min:0'],
            'shipping_cost' => ['nullable', 'integer', 'min:0'],
            'landed_cost' => ['nullable', 'integer', 'min:0'],
            'competitor_links' => ['nullable', 'array'],
            'competitor_links.*.name' => ['required_with:competitor_links', 'string', 'max:255'],
            'competitor_links.*.url' => ['required_with:competitor_links', 'url', 'max:500'],
            'competitor_links.*.price' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $adminId = Auth::guard('admin')->id();

        $landed = $data['landed_cost']
            ?? (($data['manufacturer_cost'] ?? 0) + ($data['shipping_cost'] ?? 0));
        $marginPct = ($landed > 0 && $adminListing->price > 0)
            ? round(($adminListing->price - $landed) / $adminListing->price * 100, 2)
            : null;

        $ref = ProductCostReference::where('product_id', $productId)->first();

        if ($ref) {
            $ref->fill(array_merge($data, [
                'platform_margin_pct' => $marginPct,
                'updated_by_admin_id' => $adminId,
            ]));
            $ref->save();
            $message = __('admin.admin_listings.product_reference_updated');
        } else {
            $ref = ProductCostReference::create(array_merge($data, [
                'product_id' => $productId,
                'platform_margin_pct' => $marginPct,
                'is_confidential' => 1,
                'created_by_admin_id' => $adminId,
            ]));
            $message = __('admin.admin_listings.product_reference_created');
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'ref' => $this->serializeRef($ref->fresh()),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Cost References tab (admin-only, confidential — products.cost_data.*)
    // ──────────────────────────────────────────────────────────────────────────

    /** DataTable feed of cost references linked directly to this admin listing. */
    public function costReferences(Request $request, AdminListing $adminListing): JsonResponse
    {
        abort_unless(
            auth('admin')->user()?->hasPermissionTo('products.cost_data.view'),
            403
        );

        $query = ProductCostReference::query()
            ->where('admin_listing_id', $adminListing->id);

        $columns = [
            ['searchable_columns' => ['manufacturer_name'], 'orderable_column' => 'manufacturer_name'],
            ['orderable_column' => 'manufacturer_cost'],
            ['orderable_column' => 'shipping_cost'],
            ['orderable_column' => 'landed_cost'],
            ['orderable_column' => 'platform_margin_pct'],
            ['orderable_column' => 'competitor_last_checked'],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (ProductCostReference $ref) {
            return array_merge($this->serializeRef($ref), [
                'competitor_count' => count($ref->competitorLinksNormalized()),
                'last_checked' => $ref->competitor_last_checked?->toISOString(),
            ]);
        });
    }

    /** Create a cost reference scoped to this admin listing. */
    public function storeCostReference(Request $request, AdminListing $adminListing): JsonResponse
    {
        abort_unless(
            auth('admin')->user()?->hasPermissionTo('products.cost_data.view'),
            403
        );

        $data = $this->validateCostReference($request);

        $ref = ProductCostReference::create(array_merge($data, [
            'product_id' => $adminListing->productVariant->product_id,
            'admin_listing_id' => $adminListing->id,
            'vendor_listing_id' => null,
            'is_confidential' => 1,
            'created_by_admin_id' => Auth::guard('admin')->id(),
        ]));

        return response()->json([
            'success' => true,
            'message' => __('admin.admin_listings.cost_reference_created'),
            'ref' => $this->serializeRef($ref->fresh()),
        ]);
    }

    /** Update a cost reference belonging to this admin listing. */
    public function updateCostReference(Request $request, AdminListing $adminListing, ProductCostReference $costReference): JsonResponse
    {
        abort_unless(
            auth('admin')->user()?->hasPermissionTo('products.cost_data.view'),
            403
        );
        abort_unless($costReference->admin_listing_id === $adminListing->id, 404);

        $data = $this->validateCostReference($request);

        $costReference->fill(array_merge($data, [
            'updated_by_admin_id' => Auth::guard('admin')->id(),
        ]));
        $costReference->save();

        return response()->json([
            'success' => true,
            'message' => __('admin.admin_listings.cost_reference_updated'),
            'ref' => $this->serializeRef($costReference->fresh()),
        ]);
    }

    /** Delete a cost reference belonging to this admin listing. */
    public function destroyCostReference(AdminListing $adminListing, ProductCostReference $costReference): JsonResponse
    {
        abort_unless(
            auth('admin')->user()?->hasPermissionTo('products.cost_data.view'),
            403
        );
        abort_unless($costReference->admin_listing_id === $adminListing->id, 404);

        $costReference->delete();

        return response()->json([
            'success' => true,
            'message' => __('admin.admin_listings.cost_reference_deleted'),
        ]);
    }

    private function validateCostReference(Request $request): array
    {
        return $request->validate([
            'manufacturer_name' => ['nullable', 'string', 'max:255'],
            'manufacturer_url' => ['nullable', 'url', 'max:500'],
            'manufacturer_sku' => ['nullable', 'string', 'max:100'],
            'manufacturer_cost' => ['nullable', 'integer', 'min:0'],
            'shipping_cost' => ['nullable', 'integer', 'min:0'],
            'landed_cost' => ['nullable', 'integer', 'min:0'],
            'platform_margin_pct' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'competitor_links' => ['nullable', 'array'],
            'competitor_links.*.name' => ['required_with:competitor_links', 'string', 'max:255'],
            'competitor_links.*.url' => ['required_with:competitor_links', 'url', 'max:500'],
            'competitor_links.*.price' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Select2 search endpoints
    // ──────────────────────────────────────────────────────────────────────────

    public function searchVariants(Request $request): JsonResponse
    {
        $term = $request->input('q', '');

        $variants = ProductVariant::query()
            ->join('products as p', 'p.id', '=', 'product_variants.product_id')
            ->whereNull('product_variants.deleted_at')
            ->whereNull('p.deleted_at')
            ->where(function ($q) use ($term) {
                $q->where('p.name_en', 'like', "%{$term}%")
                    ->orWhere('product_variants.sku', 'like', "%{$term}%");
            })
            ->orderBy('p.name_en')
            ->limit(30)
            ->get(['product_variants.id', 'product_variants.product_id', 'p.name_en', 'product_variants.sku', 'product_variants.variant_name', 'product_variants.slug']);

        return response()->json([
            'results' => $variants->map(fn($v) => [
                'id' => $v->id,
                'product_id' => $v->product_id,
                'text' => $v->name_en . ($v->variant_name ? " ({$v->variant_name})" : '') . " [{$v->slug}] — {$v->sku}",
            ]),
        ]);
    }

    private function serializeRef(ProductCostReference $ref): array
    {
        $currency = $ref->adminListing?->currency
            ?? $ref->vendorListing?->currency
            ?? '';

        return [
            'id' => $ref->id,
            'product_id' => $ref->product_id,
            'manufacturer_name' => $ref->manufacturer_name,
            'manufacturer_url' => $ref->manufacturer_url,
            'manufacturer_sku' => $ref->manufacturer_sku,
            'manufacturer_cost' => $ref->manufacturer_cost,
            'shipping_cost' => $ref->shipping_cost,
            'landed_cost' => $ref->landed_cost,
            'currency' => $currency,
            'platform_margin_pct' => $ref->platform_margin_pct,
            'competitor_links' => $ref->competitorLinksNormalized(),
            'competitor_last_checked' => $ref->competitor_last_checked?->toISOString(),
            'notes' => $ref->notes,
            'created_by' => $ref->createdByAdmin?->name ?? 'System',
            'updated_by' => $ref->updatedByAdmin?->name ?? null,
            'updated_at' => $ref->updated_at?->toISOString(),
            'manufacturer_cost_formatted' => $ref->manufacturerCostFormatted($currency),
            'shipping_cost_formatted' => $ref->shippingCostFormatted($currency),
            'landed_cost_formatted' => $ref->landedCostFormatted($currency),
        ];
    }

    private function storeRules(): array
    {
        return [
            'product_variant_id'         => ['required', 'uuid', 'exists:product_variants,id'],
            'country_id'                 => ['required', 'exists:countries,id'],
            'warehouse_id'               => ['nullable', 'exists:warehouses,id'],
            'price'                      => ['required', 'integer', 'min:0'],
            'compare_at_price'           => ['nullable', 'integer', 'min:0'],
            'cost_price'                 => ['nullable', 'integer', 'min:0'],
            'condition'                  => ['required', 'in:new,like_new,good,acceptable,refurbished'],
            'condition_notes'            => ['nullable', 'string', 'max:500'],
            'primary_shipping_method_id' => ['nullable', 'exists:shipping_methods,id'],
            'vendor_covers_delivery'     => ['boolean'],
            'max_order_quantity'         => ['nullable', 'integer', 'min:1'],
            'low_stock_threshold'        => ['required', 'integer', 'min:0'],
            'status'                     => ['required', 'in:active,paused,out_of_stock,archived'],
            'campaign_enabled'           => ['boolean'],
            'sold_by_label_en'           => ['nullable', 'string', 'max:150'],
            'sold_by_label_ar'           => ['nullable', 'string', 'max:150'],
            'express_badge_label_en'     => ['nullable', 'string', 'max:100'],
            'express_badge_label_ar'     => ['nullable', 'string', 'max:100'],
            'search_boost'               => ['integer', 'min:0', 'max:20'],
            'is_daily_deal'              => ['boolean'],
            'daily_deal_ends_at'         => ['nullable', 'date', 'after:now'],
            'weight_class'               => ['nullable', 'in:light,medium,heavy'],
            'handling_class'             => ['required', 'in:standard,refrigerated,fragile,special_tech'],
            'declared_weight_grams'      => ['nullable', 'integer', 'min:1'],
            'declared_length_cm'         => ['nullable', 'numeric', 'min:0'],
            'declared_width_cm'          => ['nullable', 'numeric', 'min:0'],
            'declared_height_cm'         => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * product_variant_id and country_id are locked after creation and submitted
     * as hidden inputs; excluded here so a validation failure elsewhere never
     * wipes them via withInput() (the async variant select isn't restored by old()).
     */
    private function updateRules(): array
    {
        return array_diff_key($this->storeRules(), [
            'product_variant_id' => true,
            'country_id'         => true,
        ]);
    }
}
