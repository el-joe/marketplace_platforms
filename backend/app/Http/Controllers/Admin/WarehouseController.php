<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InventoryTransferStatus;
use App\Enums\WarehouseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTransferRequest;
use App\Http\Requests\Admin\StoreWarehouseRequest;
use App\Http\Requests\Admin\UpdateWarehouseRequest;
use App\Models\Admin;
use App\Models\Country;
use App\Models\Currency;
use App\Models\FbnDailyOverageFee;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\PlatformShippingSubsidy;
use App\Models\ShippingZone;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\Warehouse;
use App\Models\WarehouseExceptionalZone;
use App\Models\WarehouseInventory;
use App\Models\WarehouseVendorLimit;
use App\Services\WarehouseService;
use App\Services\WarehouseVendorLimitService;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    use HasDataTable;

    public function __construct(
        private readonly WarehouseService $warehouseService,
        private readonly WarehouseVendorLimitService $limitService,
    ) {
    }

    // ─── Index ───────────────────────────────────────────────────────────────

    public function index(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $stats = [
            'platform' => Warehouse::where('type', WarehouseType::PlatformFbn->value)->count(),
            'seller_owned' => Warehouse::where('type', WarehouseType::SellerOwned->value)->count(),
            'third_party' => Warehouse::where('type', WarehouseType::ThirdParty->value)->count(),
            'total_capacity' => (float) Warehouse::sum('total_capacity_m3'),
            'used_capacity' => (float) Warehouse::sum('used_capacity_m3'),
            'active' => Warehouse::where('is_active', true)->count(),
        ];

        return view('admin.warehouses.index', compact('stats'));
    }

    // ─── Datatable ───────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $query = Warehouse::query()
            ->leftJoin('countries', 'countries.id', '=', 'warehouses.country_id')
            ->leftJoin('vendors', 'vendors.id', '=', 'warehouses.owner_vendor_id')
            ->leftJoin('admins', 'admins.id', '=', 'warehouses.manager_admin_id')
            ->select(
                'warehouses.*',
                'countries.name_en as country_name',
                'vendors.store_name as vendor_name',
                'admins.name as manager_name'
            );

        $query = $this->applyFilters($query, $request, [
            'type' => fn($q, $v) => $q->where('warehouses.type', $v),
            'country_id' => fn($q, $v) => $q->where('warehouses.country_id', $v),
            'is_active' => fn($q, $v) => $q->where('warehouses.is_active', (bool) $v),
        ]);

        $columns = [
            0 => ['searchable_columns' => ['warehouses.name', 'warehouses.code'], 'orderable_column' => 'warehouses.name'],
            1 => ['orderable_column' => 'warehouses.code'],
            2 => ['orderable_column' => 'warehouses.type'],
            3 => ['searchable_columns' => ['countries.name_en'], 'orderable_column' => 'countries.name_en'],
            4 => ['searchable_columns' => ['vendors.store_name']],
            5 => ['searchable_columns' => ['admins.name']],
            6 => [],
            7 => ['orderable_column' => 'warehouses.is_active'],
            8 => [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (Warehouse $wh) {
            $typeBadge = match ($wh->type) {
                WarehouseType::PlatformFbn => 'bg-indigo-100 text-indigo-700',
                WarehouseType::SellerOwned => 'bg-orange-100 text-orange-700',
                WarehouseType::ThirdParty => 'bg-gray-100 text-gray-600',
                default => 'bg-gray-100 text-gray-700',
            };

            $usedPct = $wh->total_capacity_m3 > 0
                ? round($wh->used_capacity_m3 / $wh->total_capacity_m3 * 100)
                : 0;
            $barColor = $usedPct >= 90 ? 'bg-red-500' : ($usedPct >= 70 ? 'bg-warning-500' : 'bg-green-500');

            $showUrl = route('admin.warehouses.show', $wh->id);
            $editUrl = route('admin.warehouses.edit', $wh->id);
            $toggleUrl = route('admin.warehouses.toggle-active', $wh->id);

            return [
                'DT_RowId' => 'wh-' . $wh->id,
                'name' => '<a href="' . $showUrl . '" class="font-medium text-primary-600 hover:underline">' . e($wh->name) . '</a>',
                'code' => '<span class="font-mono text-xs text-gray-600">' . e($wh->code) . '</span>',
                'type' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $typeBadge . '">' . e($wh->type->label()) . '</span>',
                'country' => '<span class="text-sm">' . e($wh->country_name ?? '—') . '</span>',
                'vendor' => $wh->owner_vendor_id ? '<span class="text-sm text-gray-700">' . e($wh->vendor_name) . '</span>' : '<span class="text-gray-300">—</span>',
                'manager' => $wh->manager_admin_id ? '<span class="text-sm text-gray-700">' . e($wh->manager_name) . '</span>' : '<span class="text-gray-300">—</span>',
                'capacity' => '<div class="flex items-center gap-2"><div class="flex-1 bg-gray-100 rounded-full h-2"><div class="h-2 rounded-full ' . $barColor . '" style="width:' . $usedPct . '%"></div></div><span class="text-xs tabular-nums text-gray-500 whitespace-nowrap">' . $usedPct . '%</span></div>',
                'is_active' => $wh->is_active
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Active</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>',
                'actions' => '<div class="flex items-center gap-1">'
                    . '<a href="' . $showUrl . '" class="btn btn-xs btn-ghost">View</a>'
                    . '<a href="' . $editUrl . '" class="btn btn-xs btn-secondary">Edit</a>'
                    . '<button class="btn btn-xs btn-ghost js-toggle-active" data-url="' . $toggleUrl . '" data-active="' . (int) $wh->is_active . '">' . ($wh->is_active ? 'Disable' : 'Enable') . '</button>'
                    . '</div>',
            ];
        });
    }

    // ─── Show ────────────────────────────────────────────────────────────────

    public function show(Warehouse $warehouse): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $warehouse->load(['country', 'ownerVendor', 'managerAdmin']);

        $inventoryStats = [
            'total_skus' => WarehouseInventory::forWarehouse($warehouse->id)->count(),
            'total_units' => WarehouseInventory::forWarehouse($warehouse->id)->sum('quantity_on_hand'),
            'low_stock' => WarehouseInventory::forWarehouse($warehouse->id)->lowStock()->count(),
            'out_of_stock' => WarehouseInventory::forWarehouse($warehouse->id)->outOfStock()->count(),
        ];

        $recentMovements = InventoryMovement::query()
            ->whereHas('warehouseInventory', fn($q) => $q->where('warehouse_id', $warehouse->id))
            ->with('warehouseInventory.vendorListing')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $transfers = InventoryTransfer::query()
            ->where(
                fn($q) => $q
                    ->where('source_warehouse_id', $warehouse->id)
                    ->orWhere('destination_warehouse_id', $warehouse->id)
            )
            ->with(['sourceWarehouse', 'destinationWarehouse'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $usedPct = $warehouse->capacity_used_percent;

        $vendorLimits = collect();
        $vendors = collect();
        if ($warehouse->owner_vendor_id === null) {
            $vendorLimits = WarehouseVendorLimit::where('warehouse_id', $warehouse->id)
                ->with('vendor')
                ->get()
                ->map(function (WarehouseVendorLimit $limit) use ($warehouse) {
                    $usage = $limit->isQuantityBased()
                        ? $this->limitService->currentQuantityUsage($warehouse->id, $limit->vendor_id)
                        : $this->limitService->currentCapacityUsage($warehouse->id, $limit->vendor_id);

                    return [
                        'id' => $limit->id,
                        'vendor_id' => $limit->vendor_id,
                        'vendor_name' => $limit->vendor?->store_name ?? '—',
                        'limit_type' => $limit->limit_type,
                        'max_quantity' => $limit->max_quantity,
                        'max_capacity_m3' => $limit->max_capacity_m3,
                        'current_usage' => $usage,
                    ];
                });

            $vendors = Vendor::/*where('global_status', 'approved')->*/orderBy('store_name')->pluck('store_name', 'id');
        }

        $allZonesInCountry = ShippingZone::where('country_id', $warehouse->country_id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->filter(fn(ShippingZone $zone) => $zone->hasGapRates())
            ->values();

        $exceptionalZones = WarehouseExceptionalZone::where('warehouse_id', $warehouse->id)
            ->pluck('is_active', 'destination_zone_id');

        $subsidyRules = PlatformShippingSubsidy::where('is_active', 1)
            ->whereIn('shipping_zone_id', $allZonesInCountry->pluck('id'))
            ->where(function ($query) use ($warehouse) {
                $query->where('warehouse_id', $warehouse->id)
                    ->orWhereNull('warehouse_id');
            })
            ->with('shippingMethod')
            ->get()
            ->groupBy('shipping_zone_id');

        return view('admin.warehouses.show', compact(
            'warehouse',
            'inventoryStats',
            'recentMovements',
            'transfers',
            'usedPct',
            'vendorLimits',
            'vendors',
            'allZonesInCountry',
            'exceptionalZones',
            'subsidyRules'
        ));
    }

    public function toggleExceptionalZone(Warehouse $warehouse, ShippingZone $zone): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $exceptionalZone = WarehouseExceptionalZone::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'destination_zone_id' => $zone->id],
            ['is_active' => true]
        );

        if (!$exceptionalZone->wasRecentlyCreated) {
            $exceptionalZone->update(['is_active' => !$exceptionalZone->is_active]);
        }

        return redirect()->route('admin.warehouses.show', $warehouse->id)
            ->with('success', 'Exceptional zone status updated.');
    }

    // ─── Daily Overage Fees (platform_fbn warehouses only) ──────────────────────

    public function overageFeesDatatable(Request $request, Warehouse $warehouse): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $query = FbnDailyOverageFee::query()
            ->where('fbn_daily_overage_fees.warehouse_id', $warehouse->id)
            ->leftJoin('vendors', 'vendors.id', '=', 'fbn_daily_overage_fees.vendor_id')
            ->leftJoin('warehouse_inventories', 'warehouse_inventories.id', '=', 'fbn_daily_overage_fees.warehouse_inventory_id')
            ->leftJoin('vendor_listings', 'vendor_listings.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'vendor_listings.product_variant_id')
            ->select(
                'fbn_daily_overage_fees.*',
                'vendors.store_name as vendor_name',
                'vendor_listings.vendor_sku as vendor_sku',
                'product_variants.sku as sku',
            );

        $columns = [
            0 => ['orderable_column' => 'fbn_daily_overage_fees.fee_date'],
            1 => ['searchable_columns' => ['vendors.store_name']],
            2 => ['searchable_columns' => ['vendor_listings.vendor_sku', 'product_variants.sku']],
            3 => ['orderable_column' => 'fbn_daily_overage_fees.units'],
            4 => ['orderable_column' => 'fbn_daily_overage_fees.fee_per_unit'],
            5 => ['orderable_column' => 'fbn_daily_overage_fees.total_fee'],
            6 => [],
            7 => ['orderable_column' => 'fbn_daily_overage_fees.status'],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (FbnDailyOverageFee $fee) {
            $statusBadge = match ($fee->status->value) {
                'invoiced' => 'bg-primary-100 text-primary-700',
                'paid' => 'bg-green-100 text-green-700',
                default => 'bg-yellow-100 text-yellow-700',
            };

            return [
                'DT_RowId' => 'ovg-' . $fee->id,
                'fee_date' => $fee->fee_date?->format('Y-m-d'),
                'vendor' => e($fee->vendor_name ?? '—'),
                'sku' => '<span class="font-mono text-xs">' . e($fee->vendor_sku ?? $fee->sku ?? '—') . '</span>',
                'units' => number_format($fee->units),
                'fee_per_unit' => number_format($fee->fee_per_unit) . ' ' . $fee->currency,
                'total_fee' => number_format($fee->total_fee) . ' ' . $fee->currency,
                'currency' => $fee->currency,
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $statusBadge . '">' . e($fee->status->value) . '</span>',
            ];
        });
    }

    // ─── Vendor Limits (FBN platform warehouses only) ───────────────────────────

    public function storeVendorLimit(Request $request, Warehouse $warehouse): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);
        abort_if($warehouse->owner_vendor_id !== null, 422, 'Vendor limits only apply to platform FBN warehouses.');

        $data = $request->validate([
            'vendor_id' => ['required', 'uuid', 'exists:vendors,id'],
            'limit_type' => ['required', Rule::in(['quantity', 'capacity'])],
            'max_quantity' => ['required_if:limit_type,quantity', 'nullable', 'integer', 'min:1'],
            'max_capacity_m3' => ['required_if:limit_type,capacity', 'nullable', 'numeric', 'min:0.01'],
        ]);

        $limit = WarehouseVendorLimit::updateOrCreate(
            ['warehouse_id' => $warehouse->id, 'vendor_id' => $data['vendor_id']],
            [
                'limit_type' => $data['limit_type'],
                'max_quantity' => $data['limit_type'] === 'quantity' ? $data['max_quantity'] : null,
                'max_capacity_m3' => $data['limit_type'] === 'capacity' ? $data['max_capacity_m3'] : null,
            ]
        );

        return response()->json(['message' => 'Vendor limit saved.', 'id' => $limit->id]);
    }

    public function destroyVendorLimit(Warehouse $warehouse, WarehouseVendorLimit $limit): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);
        abort_if($limit->warehouse_id !== $warehouse->id, 404);

        $limit->delete();

        return response()->json(['message' => 'Vendor limit removed.']);
    }

    public function applyDefaultLimitToAllVendors(Warehouse $warehouse): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);
        abort_if($warehouse->owner_vendor_id !== null, 422, 'Default limits only apply to platform-owned warehouses.');

        if (!$warehouse->default_limit_type) {
            return response()->json(['message' => 'Set and save a default limit for this warehouse first.'], 422);
        }

        $vendorIds = Vendor::/*where('global_status', 'approved')->*/pluck('id');

        $applied = 0;
        foreach ($vendorIds as $vendorId) {
            $limit = WarehouseVendorLimit::firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'vendor_id' => $vendorId],
                [
                    'limit_type' => $warehouse->default_limit_type,
                    'max_quantity' => $warehouse->default_max_quantity,
                    'max_capacity_m3' => $warehouse->default_max_capacity_m3,
                ]
            );

            if ($limit->wasRecentlyCreated) {
                $applied++;
            }
        }

        return response()->json([
            'message' => "Default limit applied to {$applied} vendor(s) without a custom limit ({$vendorIds->count()} vendor(s) checked).",
        ]);
    }

    // ─── Create / Store ──────────────────────────────────────────────────────

    public function create(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $countries = Country::where('is_active', true)->orderBy('name_en')->pluck('name_en', 'id');
        $vendors = Vendor::where('global_status', 'approved')->orderBy('store_name')->pluck('store_name', 'id');
        $admins = Admin::orderBy('name')->pluck('name', 'id');
        $currencies = Currency::where('is_active', true)->orderBy('code')->pluck('code', 'code');

        return view('admin.warehouses.create', compact('countries', 'vendors', 'admins', 'currencies'));
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $warehouse = $this->warehouseService->create($request->validated());

        return redirect()->route('admin.warehouses.show', $warehouse->id)
            ->with('success', 'Warehouse created successfully.');
    }

    // ─── Edit / Update ───────────────────────────────────────────────────────

    public function edit(Warehouse $warehouse): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $countries = Country::where('is_active', true)->orderBy('name_en')->pluck('name_en', 'id');
        $vendors = Vendor::where('global_status', 'approved')->orderBy('store_name')->pluck('store_name', 'id');
        $admins = Admin::orderBy('name')->pluck('name', 'id');
        $currencies = Currency::where('is_active', true)->orderBy('code')->pluck('code', 'code');

        return view('admin.warehouses.edit', compact('warehouse', 'countries', 'vendors', 'admins', 'currencies'));
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $this->warehouseService->update($warehouse, $request->validated());

        return redirect()->route('admin.warehouses.show', $warehouse->id)
            ->with('success', 'Warehouse updated successfully.');
    }

    // ─── Toggle Active ───────────────────────────────────────────────────────

    public function toggleActive(Warehouse $warehouse): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $warehouse->update(['is_active' => !$warehouse->is_active]);

        return response()->json([
            'message' => 'Warehouse ' . ($warehouse->is_active ? 'activated' : 'deactivated') . '.',
            'is_active' => $warehouse->is_active,
        ]);
    }

    // ─── Inventory ───────────────────────────────────────────────────────────

    public function inventoryDatatable(Request $request, Warehouse $warehouse): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $query = WarehouseInventory::forWarehouse($warehouse->id)
            ->leftJoin('vendor_listings', 'vendor_listings.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'vendor_listings.product_variant_id')
            ->leftJoin('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('vendors', 'vendors.id', '=', 'vendor_listings.vendor_id')
            ->select(
                'warehouse_inventories.*',
                'products.name_en as product_name',
                'vendors.store_name as vendor_name',
                'product_variants.sku as sku',
            );

        if ($request->filled('filter_status')) {
            match ($request->filter_status) {
                'low_stock' => $query->lowStock(),
                'out_of_stock' => $query->outOfStock(),
                default => null,
            };
        }

        $columns = [
            0 => ['searchable_columns' => ['products.name_en', 'product_variants.sku'], 'orderable_column' => 'products.name_en'],
            1 => ['searchable_columns' => ['vendors.store_name'], 'orderable_column' => 'vendors.store_name'],
            2 => ['orderable_column' => 'warehouse_inventories.quantity_on_hand'],
            3 => ['orderable_column' => 'warehouse_inventories.quantity_available'],
            4 => ['orderable_column' => 'warehouse_inventories.quantity_reserved'],
            5 => ['orderable_column' => 'warehouse_inventories.quantity_damaged'],
            6 => ['orderable_column' => 'warehouse_inventories.bin_location'],
            7 => [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (WarehouseInventory $inv) use ($warehouse) {
            $adjustUrl = route('admin.warehouses.inventory.adjust', [$warehouse->id, $inv->id]);
            $movementsUrl = route('admin.warehouses.inventory.movements', [$warehouse->id, $inv->id]);

            $qty = $inv->quantity_available ?? ($inv->quantity_on_hand - $inv->quantity_reserved);
            $isLow = $inv->reorder_point !== null && $inv->quantity_on_hand <= $inv->reorder_point;

            $qtyCell = $isLow
                ? '<span class="text-orange-600 font-medium">' . $qty . '</span> <span class="text-xs text-orange-400">Low</span>'
                : '<span>' . $qty . '</span>';

            return [
                'DT_RowId' => 'inv-' . $inv->id,
                'product' => '<div class="text-sm font-medium text-gray-800">' . e($inv->product_name) . '</div><div class="text-xs text-gray-400 font-mono">' . e($inv->sku ?? '') . '</div>',
                'vendor' => e($inv->vendor_name ?? '—'),
                'on_hand' => number_format($inv->quantity_on_hand),
                'available' => $qtyCell,
                'reserved' => number_format($inv->quantity_reserved),
                'damaged' => $inv->quantity_damaged > 0
                    ? '<span class="text-red-500">' . number_format($inv->quantity_damaged) . '</span>'
                    : '0',
                'bin' => $inv->bin_location
                    ? '<span class="font-mono text-xs">' . e($inv->bin_location) . '</span>'
                    : '<span class="text-gray-300">—</span>',
                'actions' => '<div class="flex items-center gap-1">'
                    . '<button class="btn btn-xs btn-secondary js-adjust-inventory" data-url="' . $adjustUrl . '" data-id="' . $inv->id . '">Adjust</button>'
                    . '<button class="btn btn-xs btn-ghost js-view-movements" data-url="' . $movementsUrl . '">Movements</button>'
                    . '</div>',
            ];
        });
    }

    public function adjustInventory(Request $request, Warehouse $warehouse, WarehouseInventory $inventory): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $request->validate([
            'delta' => ['required', 'integer', 'not_in:0'],
            'movement_type' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\InventoryMovementType::class)],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $updated = $this->warehouseService->adjustInventory(
                warehouseInventoryId: $inventory->id,
                delta: (int) $request->delta,
                movementType: $request->movement_type,
                reason: $request->reason,
                createdByUserId: $admin->id,
            );

            return response()->json([
                'message' => 'Inventory adjusted successfully.',
                'quantity_on_hand' => $updated->quantity_on_hand,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function movements(Request $request, Warehouse $warehouse, WarehouseInventory $inventory): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $movements = $inventory->inventoryMovements()
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($movements);
    }

    // ─── Transfers ───────────────────────────────────────────────────────────

    public function transfersIndex(Request $request): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->pluck('name', 'id');

        $stats = [
            'pending' => InventoryTransfer::where('status', InventoryTransferStatus::Draft->value)->count(),
            'in_transit' => InventoryTransfer::where('status', InventoryTransferStatus::InTransit->value)->count(),
            'received' => InventoryTransfer::where('status', InventoryTransferStatus::Received->value)->count(),
            'cancelled' => InventoryTransfer::where('status', InventoryTransferStatus::Cancelled->value)->count(),
        ];

        return view('admin.warehouses.transfers.index', compact('warehouses', 'stats'));
    }

    public function transfersDatatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $query = InventoryTransfer::query()
            ->leftJoin('warehouses as sw', 'sw.id', '=', 'inventory_transfers.source_warehouse_id')
            ->leftJoin('warehouses as dw', 'dw.id', '=', 'inventory_transfers.destination_warehouse_id')
            ->leftJoin('vendors', 'vendors.id', '=', 'inventory_transfers.vendor_id')
            ->select(
                'inventory_transfers.*',
                'sw.name as source_name',
                'dw.name as destination_name',
                'vendors.store_name as vendor_name',
            );

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('inventory_transfers.status', $v),
            'source_warehouse_id' => fn($q, $v) => $q->where('inventory_transfers.source_warehouse_id', $v),
            'dest_warehouse_id' => fn($q, $v) => $q->where('inventory_transfers.destination_warehouse_id', $v),
        ]);

        $columns = [
            0 => ['searchable_columns' => ['inventory_transfers.transfer_number'], 'orderable_column' => 'inventory_transfers.transfer_number'],
            1 => ['orderable_column' => 'sw.name'],
            2 => ['orderable_column' => 'dw.name'],
            3 => [],
            4 => ['orderable_column' => 'inventory_transfers.status'],
            5 => ['orderable_column' => 'inventory_transfers.created_at'],
            6 => [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (InventoryTransfer $transfer) {
            $showUrl = route('admin.warehouses.transfers.show', $transfer->id);

            $statusBadge = match ($transfer->status) {
                InventoryTransferStatus::InTransit => 'bg-blue-100 text-blue-700',
                InventoryTransferStatus::Received => 'bg-green-100 text-green-700',
                InventoryTransferStatus::Cancelled => 'bg-gray-100 text-gray-500',
                default => 'bg-yellow-100 text-yellow-700', // draft / others
            };

            return [
                'DT_RowId' => 'tf-' . $transfer->id,
                'number' => '<a href="' . $showUrl . '" class="font-mono text-primary-600 hover:underline">' . e($transfer->transfer_number) . '</a>',
                'source' => e($transfer->source_name ?? '—'),
                'destination' => e($transfer->destination_name ?? '—'),
                'vendor' => e($transfer->vendor_name ?? '—'),
                'status' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $statusBadge . '">' . e($transfer->status->label()) . '</span>',
                'created_at' => $transfer->created_at?->format('Y-m-d H:i'),
                'actions' => '<a href="' . $showUrl . '" class="btn btn-xs btn-ghost">View</a>',
            ];
        });
    }

    public function transferCreate(): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->pluck('name', 'id');
        $vendors = Vendor::where('global_status', 'approved')->orderBy('store_name')->pluck('store_name', 'id');

        return view('admin.warehouses.transfers.create', compact('warehouses', 'vendors'));
    }

    public function transferStore(StoreTransferRequest $request): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        try {
            $transfer = $this->warehouseService->createTransfer(
                data: $request->safe()->except('items'),
                items: $request->validated('items'),
                initiatedByUserId: $admin->id,
            );

            return redirect()->route('admin.warehouses.transfers.show', $transfer->id)
                ->with('success', 'Transfer ' . $transfer->transfer_number . ' created.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function transferShow(InventoryTransfer $transfer): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $transfer->load([
            'sourceWarehouse',
            'destinationWarehouse',
            'vendor',
            'initiatedBy',
            'items.vendorListing',
        ]);

        return view('admin.warehouses.transfers.show', compact('transfer'));
    }

    public function transferShip(Request $request, InventoryTransfer $transfer): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $request->validate([
            'carrier' => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $this->warehouseService->shipTransfer($transfer, $request->only('carrier', 'tracking_number'), $admin->id);

            return back()->with('success', 'Transfer marked as shipped.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function transferReceive(Request $request, InventoryTransfer $transfer): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        $request->validate([
            'items' => ['required', 'array'],
            'items.*.inventory_transfer_item_id' => ['required', 'uuid', 'exists:inventory_transfer_items,id'],
            'items.*.quantity_received' => ['required', 'integer', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'items.*.condition_notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->warehouseService->receiveTransfer($transfer, $request->items, $admin->id);

            return back()->with('success', 'Transfer received and inventory updated.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function transferCancel(InventoryTransfer $transfer): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('warehouses.view'), 403);

        try {
            $this->warehouseService->cancelTransfer($transfer);

            return back()->with('success', 'Transfer cancelled.');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
