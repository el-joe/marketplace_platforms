<?php

namespace App\Http\Controllers\Partner;

use App\Enums\InventoryMovementType;
use App\Enums\WarehouseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\StoreTransferRequest;
use App\Http\Requests\Partner\StoreVendorWarehouseRequest;
use App\Http\Requests\Partner\UpdateVendorWarehouseRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\InventoryMovement;
use App\Models\InventoryTransfer;
use App\Models\PlatformShippingSubsidy;
use App\Models\ShippingZone;
use App\Models\Warehouse;
use App\Models\WarehouseExceptionalZone;
use App\Models\WarehouseInventory;
use App\Services\VendorWarehouseService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function __construct(private readonly VendorWarehouseService $service)
    {
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function vendorAdmin()
    {
        return auth()->guard('vendor')->user();
    }

    private function vendor()
    {
        return $this->vendorAdmin()->vendor;
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $vendor = $this->vendor();

        $myWarehouses = Warehouse::where('owner_vendor_id', $vendor->id)
            ->with(['country', 'warehouseInventories'])
            ->get();

        $platformWarehouses = Warehouse::where('type', WarehouseType::PlatformFbn->value)
            ->whereHas('warehouseInventories.vendorListing', fn($q) => $q->where('vendor_id', $vendor->id))
            ->with('country')
            ->get();

        $countries = Country::where('is_active', 1)->get();

        return view('partner.warehouses.index', compact('myWarehouses', 'platformWarehouses', 'countries'));
    }

    // ─── Seller-owned Warehouses Datatable ─────────────────────────────────────

    public function sellerDt(Request $request): JsonResponse
    {
        $vendorId = $this->vendor()->id;

        $base = Warehouse::query()
            ->leftJoin('warehouse_inventories', 'warehouse_inventories.warehouse_id', '=', 'warehouses.id')
            ->where('warehouses.owner_vendor_id', $vendorId)
            ->where('warehouses.type', WarehouseType::SellerOwned->value)
            ->groupBy('warehouses.id')
            ->selectRaw('warehouses.*, '
                . 'COALESCE(SUM(warehouse_inventories.quantity_on_hand), 0) as total_units, '
                . 'COUNT(DISTINCT warehouse_inventories.vendor_listing_id) as sku_count');

        if ($search = $request->input('search.value')) {
            $base->where(function ($q) use ($search) {
                $q->where('warehouses.name', 'like', "%{$search}%")
                    ->orWhere('warehouses.code', 'like', "%{$search}%");
            });
        }

        $total = Warehouse::where('owner_vendor_id', $vendorId)
            ->where('type', WarehouseType::SellerOwned->value)
            ->count();

        $filtered = (clone $base)->get()->count();

        $warehouses = $base->with('country')
            ->orderBy('warehouses.name')
            ->skip($request->input('start', 0))
            ->take($request->input('length', 15))
            ->get();

        $rows = $warehouses->map(fn(Warehouse $w) => [
            'DT_RowId' => 'wh-' . $w->id,
            'code' => '<span class="text-xs font-bold font-mono bg-gray-100 text-gray-600 px-2 py-0.5 rounded">' . e($w->code) . '</span>',
            'name' => e($w->name),
            'country' => e($w->country?->name_en ?? '—'),
            'total_units' => number_format((int) $w->total_units),
            'sku_count' => (int) $w->sku_count,
            'status' => $w->is_active
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">' . __('partner.warehouses.active') . '</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">' . __('partner.warehouses.inactive') . '</span>',
            'actions' => '<a href="' . route('partner.warehouses.show', $w->id) . '" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-primary-50 text-primary-700 hover:bg-primary-100 transition-colors">'
                . __('partner.warehouses.manage') . '</a>',
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }

    // ─── Platform FBN Warehouses Datatable (scoped to this vendor's stock) ─────

    public function fbnDt(Request $request): JsonResponse
    {
        $vendorId = $this->vendor()->id;

        $base = Warehouse::query()
            ->join('warehouse_inventories', 'warehouse_inventories.warehouse_id', '=', 'warehouses.id')
            ->join('vendor_listings', 'vendor_listings.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->where('warehouses.type', WarehouseType::PlatformFbn->value)
            ->where('vendor_listings.vendor_id', $vendorId)
            ->groupBy('warehouses.id')
            ->selectRaw('warehouses.*, '
                . 'COALESCE(SUM(warehouse_inventories.quantity_on_hand), 0) as my_units, '
                . 'COUNT(DISTINCT warehouse_inventories.vendor_listing_id) as my_sku_count');

        if ($search = $request->input('search.value')) {
            $base->where('warehouses.name', 'like', "%{$search}%");
        }

        $total = (clone $base)->get()->count();
        $filtered = $total;

        $warehouses = $base->with('country')
            ->orderBy('warehouses.name')
            ->skip($request->input('start', 0))
            ->take($request->input('length', 15))
            ->get();

        $rows = $warehouses->map(fn(Warehouse $w) => [
            'DT_RowId' => 'wh-' . $w->id,
            'name' => e($w->name),
            'country' => e($w->country?->name_en ?? '—'),
            'my_units' => number_format((int) $w->my_units),
            'my_sku_count' => (int) $w->my_sku_count,
            'actions' => '<a href="' . route('partner.warehouses.show', $w->id) . '" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 transition-colors">'
                . __('partner.warehouses.view_inventory_here') . '</a>',
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(): View
    {
        $vendor = $this->vendor();

        $cities = City::where('is_active', 1)
            ->where('country_id', $vendor->country_id)
            ->orderBy('name_en')
            ->get();

        return view('partner.warehouses.create', compact('cities'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(StoreVendorWarehouseRequest $request): JsonResponse
    {
        try {
            $warehouse = $this->service->registerWarehouse(
                $request->validated(),
                $this->vendor()
            );

            return response()->json([
                'success' => true,
                'data' => ['id' => $warehouse->id],
                'message' => __('partner.warehouses.messages.registered_success'),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(Warehouse $warehouse): View
    {
        // Vendors may view their own seller_owned warehouses OR platform FBN
        // warehouses where they have stock. Scope: the vendor must be the owner
        // (seller_owned) OR have inventory there.
        $vendorId = $this->vendorAdmin()->vendor_id;
        $isOwned = $warehouse->owner_vendor_id === $vendorId;

        if (!$isOwned) {
            // Allow read-only view of platform FBN if they have stock there
            $hasFbnInventory = $warehouse->type === WarehouseType::PlatformFbn
                && $warehouse->warehouseInventories()
                    ->whereHas('vendorListing', fn($q) => $q->where('vendor_id', $vendorId))
                    ->exists();

            if (!$hasFbnInventory) {
                abort(403);
            }
        }

        $warehouse->load(['country', 'address.country']);
        $usage = $this->service->getCapacityUsage($warehouse);

        $exceptionalRoutes = collect();
        if ($warehouse->type === WarehouseType::SellerOwned) {
            $exceptionalRoutes = $this->buildExceptionalRoutes($warehouse);
        }

        return view('partner.warehouses.show', compact('warehouse', 'usage', 'exceptionalRoutes'));
    }

    /**
     * For each destination zone in the warehouse's country that has a
     * recorded carrier/customer rate gap, resolve whether this warehouse
     * has opted in to serve it as an exceptional zone, and which subsidy
     * rules (warehouse-specific or global) apply.
     */
    private function buildExceptionalRoutes(Warehouse $warehouse)
    {
        $zones = ShippingZone::where('country_id', $warehouse->country_id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get()
            ->filter(fn(ShippingZone $zone) => $zone->hasGapRates())
            ->values();

        $optedIn = WarehouseExceptionalZone::where('warehouse_id', $warehouse->id)
            ->pluck('is_active', 'destination_zone_id');

        $subsidyRules = PlatformShippingSubsidy::where('is_active', 1)
            ->whereIn('shipping_zone_id', $zones->pluck('id'))
            ->where(function ($query) use ($warehouse) {
                $query->where('warehouse_id', $warehouse->id)
                    ->orWhereNull('warehouse_id');
            })
            ->with('shippingMethod')
            ->get()
            ->groupBy('shipping_zone_id');

        return $zones->map(fn(ShippingZone $zone) => [
            'zone' => $zone,
            'is_opted_in' => (bool) ($optedIn[$zone->id] ?? false),
            'subsidy_rules' => $subsidyRules->get($zone->id, collect()),
        ]);
    }

    // ─── Exceptional Zones (warehouse gap opt-in) ──────────────────────────────

    public function toggleExceptionalZone(Request $request, Warehouse $warehouse, ShippingZone $zone): JsonResponse
    {
        abort_unless($warehouse->type === WarehouseType::SellerOwned, 403);

        if ($warehouse->owner_vendor_id !== $this->vendorAdmin()->vendor_id) {
            abort(403);
        }

        $exceptionalZone = WarehouseExceptionalZone::firstOrCreate(
            ['warehouse_id' => $warehouse->id, 'destination_zone_id' => $zone->id],
            ['is_active' => true]
        );

        if (!$exceptionalZone->wasRecentlyCreated) {
            $exceptionalZone->update(['is_active' => !$exceptionalZone->is_active]);
        }

        return response()->json(['success' => true, 'is_active' => $exceptionalZone->is_active]);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(UpdateVendorWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        try {
            $this->service->updateWarehouse(
                $warehouse,
                $request->validated(),
                $this->vendor()
            );

            return response()->json(['success' => true, 'message' => __('partner.warehouses.messages.updated')]);
        } catch (AuthorizationException) {
            abort(403);
        }
    }

    // ─── Deactivate ───────────────────────────────────────────────────────────

    public function deactivate(Warehouse $warehouse): JsonResponse
    {
        try {
            $this->service->deactivate($warehouse, $this->vendor());

            return response()->json(['success' => true]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (AuthorizationException) {
            abort(403);
        }
    }

    // ─── Get Inventory (server-side paginated) ────────────────────────────────

    public function getInventory(Request $request, Warehouse $warehouse): JsonResponse
    {
        $vendorId = $this->vendorAdmin()->vendor_id;
        $isOwned = $warehouse->owner_vendor_id === $vendorId;

        if (!$isOwned) {
            // Platform FBN warehouses aren't owned by the vendor, but the vendor
            // may still view (read-only) the slice of inventory that is theirs.
            $hasFbnInventory = $warehouse->type === WarehouseType::PlatformFbn
                && $warehouse->warehouseInventories()
                    ->whereHas('vendorListing', fn($q) => $q->where('vendor_id', $vendorId))
                    ->exists();

            if (!$hasFbnInventory) {
                abort(403);
            }
        }

        $query = WarehouseInventory::where('warehouse_id', $warehouse->id)
            ->whereHas('vendorListing', fn($q) => $q->where('vendor_id', $vendorId))
            ->with(['vendorListing.productVariant.product.images']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('vendorListing.productVariant.product', fn($p) => $p
                    ->where('name_en', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%"))
                    ->orWhereHas('vendorListing', fn($vl) => $vl->where('vendor_sku', 'like', "%{$search}%"))
                    ->orWhere('bin_location', 'like', "%{$search}%");
            });
        }

        if ($request->input('low_stock')) {
            $query->lowStock();
        }

        if ($request->input('out_of_stock')) {
            $query->outOfStock();
        }

        $paginated = $query->paginate($request->input('per_page', 20));

        $rows = $paginated->getCollection()->map(function (WarehouseInventory $inv) use ($warehouse) {
            $variant = $inv->vendorListing?->productVariant;
            $product = $variant?->product;
            $img = $product?->images->firstWhere('is_primary', 1)
                ?? $product?->images->first();

            $isLowStock = $inv->reorder_point !== null && $inv->quantity_on_hand <= $inv->reorder_point;
            $isOutOfStock = $inv->quantity_available <= 0;

            return [
                'id' => $inv->id,
                'product_name' => $product?->name_en ?? '—',
                'variant_name' => $variant?->variant_name ?? '—',
                'vendor_sku' => $inv->vendorListing?->vendor_sku ?? '—',
                'image_url' => $img ? Storage::disk($img->disk)->url($img->path) : null,
                'quantity_on_hand' => $inv->quantity_on_hand,
                'quantity_reserved' => $inv->quantity_reserved,
                'quantity_available' => $inv->quantity_available, // read VIRTUAL, never write
                'quantity_inbound' => $inv->quantity_inbound,
                'quantity_damaged' => $inv->quantity_damaged,
                'bin_location' => $inv->bin_location,
                'reorder_point' => $inv->reorder_point,
                'is_low_stock' => $isLowStock,
                'is_out_of_stock' => $isOutOfStock,
                'last_counted_at_human' => $inv->last_counted_at?->diffForHumans(),
                'is_owned_warehouse' => (bool) $warehouse->owner_vendor_id,
            ];
        });

        return response()->json([
            'data' => $rows,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
        ]);
    }

    // ─── Adjust Inventory ─────────────────────────────────────────────────────

    public function adjustInventory(Request $request, WarehouseInventory $inventory): JsonResponse
    {
        $request->validate([
            'adjustment' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
            'movement_type' => ['required', \Illuminate\Validation\Rule::enum(InventoryMovementType::class)],
        ]);

        try {
            $updated = $this->service->adjustInventory(
                $inventory,
                (int) $request->adjustment,
                $request->reason,
                $request->movement_type,
                $this->vendorAdmin()
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'quantity_on_hand' => $updated->quantity_on_hand,
                    'quantity_available' => $updated->quantity_available,
                ],
                'message' => __('partner.warehouses.messages.stock_adjusted'),
            ]);
        } catch (AuthorizationException) {
            abort(403);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }
    }

    // ─── Stock Count ──────────────────────────────────────────────────────────

    public function stockCount(Request $request, WarehouseInventory $inventory): JsonResponse
    {
        $request->validate([
            'actual_count' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $updated = $this->service->stockCount(
                $inventory,
                (int) $request->actual_count,
                $this->vendorAdmin()
            );

            return response()->json([
                'success' => true,
                'data' => ['inventory' => $updated],
            ]);
        } catch (AuthorizationException) {
            abort(403);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }
    }

    // ─── Movements ────────────────────────────────────────────────────────────

    public function getMovements(WarehouseInventory $inventory): JsonResponse
    {
        // Cross-tenant check via the inventory's warehouse
        $vendorId = $this->vendorAdmin()->vendor_id;
        if ($inventory->warehouse->owner_vendor_id !== $vendorId) {
            // Also allow read on FBN if vendor's listing
            if ($inventory->vendorListing?->vendor_id !== $vendorId) {
                abort(403);
            }
        }

        $movements = InventoryMovement::where('warehouse_inventory_id', $inventory->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($movements);
    }

    // ─── Transfers Index ──────────────────────────────────────────────────────

    public function transfersIndex(): View
    {
        $vendor = $this->vendor();

        $myWarehouseIds = Warehouse::where('owner_vendor_id', $vendor->id)->pluck('id');

        $platformWarehouses = Warehouse::where('type', WarehouseType::PlatformFbn->value)
            ->where('is_active', 1)
            ->get();

        return view('partner.warehouses.transfers.index', compact('myWarehouseIds', 'platformWarehouses'));
    }

    // ─── Transfers Datatable ──────────────────────────────────────────────────

    public function transfersDatatable(Request $request): JsonResponse
    {
        $vendorId = $this->vendorAdmin()->vendor_id;

        $query = InventoryTransfer::where('vendor_id', $vendorId)
            ->with(['sourceWarehouse', 'destinationWarehouse'])
            ->orderByDesc('created_at');

        if ($search = $request->input('search.value')) {
            $query->where('transfer_number', 'like', "%{$search}%");
        }

        $total = (clone $query)->count();
        $transfers = $query
            ->skip($request->input('start', 0))
            ->take($request->input('length', 15))
            ->get();

        $rows = $transfers->map(fn(InventoryTransfer $t) => [
            'DT_RowId' => 'tr-' . $t->id,
            'number' => '<a href="' . route('partner.warehouses.transfers.show', $t->id) . '" class="font-mono text-sm text-primary-600 hover:underline">' . e($t->transfer_number) . '</a>',
            'source' => e($t->sourceWarehouse?->name ?? '—'),
            'destination' => e($t->destinationWarehouse?->name ?? '—'),
            'status' => $this->transferStatusBadge($t->status->value),
            'date' => $t->created_at->format('d M Y'),
            'actions' => '<a href="' . route('partner.warehouses.transfers.show', $t->id) . '" class="inline-flex items-center px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">View</a>',
        ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $rows,
        ]);
    }

    // ─── Transfer Create ──────────────────────────────────────────────────────

    public function transferCreate(Request $request): View
    {
        $vendor = $this->vendor();

        $myWarehouses = Warehouse::where('owner_vendor_id', $vendor->id)->get();

        $destinationOptions = Warehouse::where('is_active', 1)
            ->where(fn($q) => $q
                ->where('owner_vendor_id', $vendor->id)
                ->orWhere('type', WarehouseType::PlatformFbn->value))
            ->get();

        $sourceWarehouseId = $request->query('source');

        return view('partner.warehouses.transfers.create', compact(
            'myWarehouses',
            'destinationOptions',
            'sourceWarehouseId'
        ));
    }

    // ─── Transfer Item Search ─────────────────────────────────────────────────

    public function transferItemSearch(Request $request): JsonResponse
    {
        $request->validate([
            'source_warehouse_id' => ['required', 'exists:warehouses,id'],
            'search'              => ['nullable', 'string', 'max:100'],
        ]);

        $vendor = $this->vendor();
        $search = trim((string) $request->query('search', ''));
        $warehouseId = $request->query('source_warehouse_id');

        $query = WarehouseInventory::query()
            ->join('vendor_listings', 'vendor_listings.id', '=', 'warehouse_inventories.vendor_listing_id')
            ->join('product_variants as pv', 'pv.id', '=', 'vendor_listings.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('vendor_listings.vendor_id', $vendor->id)
            ->whereRaw('warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved > 0')
            ->where('warehouse_inventories.warehouse_id', $warehouseId)
            ->selectRaw(
                'vendor_listings.id, '
                . 'vendor_listings.vendor_sku, '
                . 'p.name_en, '
                . 'p.name_ar, '
                . 'pv.sku, '
                . 'warehouse_inventories.quantity_on_hand, '
                . 'warehouse_inventories.quantity_reserved, '
                . '(warehouse_inventories.quantity_on_hand - warehouse_inventories.quantity_reserved) as available_stock'
            );

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('p.name_en', 'like', "%{$search}%")
                    ->orWhere('p.name_ar', 'like', "%{$search}%")
                    ->orWhere('pv.sku', 'like', "%{$search}%")
                    ->orWhere('vendor_listings.vendor_sku', 'like', "%{$search}%");
            });
        }

        $items = $query->limit(20)->get()->map(fn($row) => [
            'id' => $row->id,
            'name_en' => $row->name_en,
            'name_ar' => $row->name_ar,
            'vendor_sku' => $row->vendor_sku ?: $row->sku,
            'available_stock' => max(0, (int) $row->available_stock),
        ]);

        return response()->json(['data' => $items]);
    }

    // ─── Transfer Store ───────────────────────────────────────────────────────

    public function transferStore(StoreTransferRequest $request): JsonResponse
    {
        try {
            $transfer = $this->service->createTransfer(
                $request->validated(),
                $this->vendor(),
                $this->vendorAdmin()
            );

            return response()->json([
                'success' => true,
                'data' => ['id' => $transfer->id],
                'message' => __('partner.warehouses.messages.transfer_created'),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }

    // ─── Transfer Show ────────────────────────────────────────────────────────

    public function transferShow(InventoryTransfer $transfer): View
    {
        if ($transfer->vendor_id !== $this->vendorAdmin()->vendor_id) {
            abort(403);
        }

        $transfer->load([
            'sourceWarehouse',
            'destinationWarehouse',
            'items.vendorListing.productVariant.product',
        ]);

        return view('partner.warehouses.transfers.show', compact('transfer'));
    }

    // ─── Transfer Ship ────────────────────────────────────────────────────────

    public function transferShip(Request $request, InventoryTransfer $transfer): JsonResponse
    {
        $request->validate([
            'carrier' => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $this->service->shipTransfer($transfer, $request->validated(), $this->vendor());

            return response()->json(['success' => true, 'message' => __('partner.warehouses.messages.transfer_shipped')]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ─── Transfer Cancel ──────────────────────────────────────────────────────

    public function transferCancel(InventoryTransfer $transfer): JsonResponse
    {
        try {
            $this->service->cancelTransfer($transfer, $this->vendor());

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function transferStatusBadge(string $status): string
    {
        $classes = match ($status) {
            'draft' => 'bg-gray-100 text-gray-600',
            'in_transit' => 'bg-blue-100 text-blue-700',
            'received' => 'bg-green-100 text-green-700',
            'cancelled' => 'bg-red-100 text-red-600',
            default => 'bg-gray-100 text-gray-600',
        };

        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $classes . '">'
            . ucfirst(str_replace('_', ' ', $status)) . '</span>';
    }
}
