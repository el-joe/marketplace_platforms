<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\WarehouseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\AdjustInventoryRequest;
use App\Http\Requests\Vendor\CreateTransferRequest;
use App\Http\Requests\Vendor\InventoryIndexRequest;
use App\Http\Requests\Vendor\ShipTransferRequest;
use App\Http\Resources\Vendor\InventoryMovementResource;
use App\Http\Resources\Vendor\InventoryResource;
use App\Http\Resources\Vendor\InventoryTransferResource;
use App\Http\Responses\ApiResponse;
use App\Models\InventoryTransfer;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\VendorWarehouseService;
use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    public function __construct(private readonly VendorWarehouseService $warehouseService) {}

    // ─── Inventory ────────────────────────────────────────────────────────────

    public function index(InventoryIndexRequest $request): \Illuminate\Http\JsonResponse
    {
        $vendorId = auth('vendor')->user()->vendor_id;

        // Include vendor stock in both their own warehouses and any FBN warehouses
        $query = WarehouseInventory::with(['warehouse', 'vendorListing.productVariant'])
            ->whereHas('vendorListing', fn ($q) => $q->where('vendor_id', $vendorId))
            ->when($request->warehouse_id, fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->listing_id,   fn ($q) => $q->where('vendor_listing_id', $request->listing_id))
            ->when($request->boolean('low_stock'), fn ($q) => $q->lowStock());

        $inventory = $query->paginate((int) ($request->per_page ?? 20));

        return ApiResponse::paginated($inventory, InventoryResource::class);
    }

    public function adjust(AdjustInventoryRequest $request, string $id): \Illuminate\Http\JsonResponse
    {
        $inventory = WarehouseInventory::findOrFail($id);

        Gate::authorize('adjust', $inventory);

        $updated = $this->warehouseService->adjustInventory(
            $inventory,
            $request->adjustment,
            $request->reason,
            'adjustment',
            auth('vendor')->user()
        );

        return ApiResponse::success(new InventoryResource($updated->load('warehouse')), 'Inventory adjusted.');
    }

    public function movements(string $id): \Illuminate\Http\JsonResponse
    {
        $inventory = WarehouseInventory::findOrFail($id);

        Gate::authorize('viewMovements', $inventory);

        $movements = $inventory->inventoryMovements()
            ->latest('created_at')
            ->paginate(50);

        return ApiResponse::paginated($movements, InventoryMovementResource::class);
    }

    // ─── Transfers ────────────────────────────────────────────────────────────

    public function transferIndex(): \Illuminate\Http\JsonResponse
    {
        $vendorId = auth('vendor')->user()->vendor_id;

        $transfers = InventoryTransfer::where('vendor_id', $vendorId)
            ->with(['sourceWarehouse', 'destinationWarehouse', 'items'])
            ->latest()
            ->paginate(20);

        return ApiResponse::paginated($transfers, InventoryTransferResource::class);
    }

    public function transferShow(string $transferNumber): \Illuminate\Http\JsonResponse
    {
        $transfer = $this->resolveOwnedTransfer($transferNumber);

        return ApiResponse::success(
            new InventoryTransferResource($transfer->load(['sourceWarehouse', 'destinationWarehouse', 'items']))
        );
    }

    public function transferStore(CreateTransferRequest $request): \Illuminate\Http\JsonResponse
    {
        $vendorAdmin = auth('vendor')->user();
        $vendor      = $vendorAdmin->vendor;

        // Destination must be a platform FBN warehouse
        $destination = Warehouse::findOrFail($request->destination_warehouse_id);
        if ($destination->type !== WarehouseType::PlatformFbn || ! $destination->is_active) {
            return ApiResponse::error('Destination must be an active platform FBN warehouse.', [], 422);
        }

        // Validate all listings belong to this vendor
        $listingIds = collect($request->items)->pluck('vendor_listing_id');
        $ownedCount = \App\Models\VendorListing::whereIn('id', $listingIds)
            ->where('vendor_id', $vendor->id)
            ->count();

        if ($ownedCount !== $listingIds->count()) {
            return ApiResponse::error('One or more listings do not belong to your account.', [], 422);
        }

        // Soft availability check before acquiring locks — real lock happens in shipTransfer
        foreach ($request->items as $item) {
            $available = WarehouseInventory::where('warehouse_id', $request->source_warehouse_id)
                ->where('vendor_listing_id', $item['vendor_listing_id'])
                ->value('quantity_available');

            if (is_null($available) || $available < $item['quantity']) {
                return ApiResponse::error('Insufficient available stock for one or more items.', [], 422);
            }
        }

        $transfer = $this->warehouseService->createTransfer($request->validated(), $vendor, $vendorAdmin);

        return ApiResponse::success(
            new InventoryTransferResource($transfer->load(['sourceWarehouse', 'destinationWarehouse', 'items'])),
            'Transfer draft created.',
            201
        );
    }

    public function transferShip(ShipTransferRequest $request, string $transferNumber): \Illuminate\Http\JsonResponse
    {
        $transfer = $this->resolveOwnedTransfer($transferNumber);
        $vendor   = auth('vendor')->user()->vendor;

        $updated = $this->warehouseService->shipTransfer($transfer, $request->validated(), $vendor);

        // Receipt is confirmed by platform admin at the FBN warehouse — vendor API stops at in_transit
        return ApiResponse::success(
            new InventoryTransferResource($updated->load(['sourceWarehouse', 'destinationWarehouse', 'items'])),
            'Transfer marked as in transit. Platform staff will confirm receipt at the FBN warehouse.'
        );
    }

    public function transferCancel(string $transferNumber): \Illuminate\Http\JsonResponse
    {
        $transfer = $this->resolveOwnedTransfer($transferNumber);
        $vendor   = auth('vendor')->user()->vendor;

        $updated = $this->warehouseService->cancelTransfer($transfer, $vendor);

        return ApiResponse::success(
            new InventoryTransferResource($updated),
            'Transfer cancelled.'
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function resolveOwnedTransfer(string $transferNumber): InventoryTransfer
    {
        $vendorId = auth('vendor')->user()->vendor_id;

        return InventoryTransfer::where('transfer_number', $transferNumber)
            ->where('vendor_id', $vendorId)
            ->firstOrFail();
    }
}
