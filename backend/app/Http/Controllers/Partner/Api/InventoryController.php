<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\InventoryMovementResource;
use App\Http\Resources\Vendor\InventoryResource;
use App\Http\Resources\Vendor\InventoryTransferResource;
use App\Http\Responses\ApiResponse;
use App\Models\InventoryTransfer;
use App\Models\WarehouseInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $query = WarehouseInventory::with(['warehouse', 'vendorListing.productVariant'])
            ->whereHas('vendorListing', fn ($q) => $q->where('vendor_id', $vendorId))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->filled('listing_id'),   fn ($q) => $q->where('vendor_listing_id', $request->listing_id))
            ->when($request->boolean('low_stock'),   fn ($q) => $q->lowStock());

        return ApiResponse::paginated($query->paginate((int) ($request->per_page ?? 20)), InventoryResource::class);
    }

    public function movements(string $id): JsonResponse
    {
        $inventory = WarehouseInventory::findOrFail($id);
        Gate::authorize('viewMovements', $inventory);

        $movements = $inventory->inventoryMovements()->latest('created_at')->paginate(50);

        return ApiResponse::paginated($movements, InventoryMovementResource::class);
    }

    public function transferIndex(): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $transfers = InventoryTransfer::where('vendor_id', $vendorId)
            ->with(['sourceWarehouse', 'destinationWarehouse', 'items'])
            ->latest()->paginate(20);

        return ApiResponse::paginated($transfers, InventoryTransferResource::class);
    }

    public function transferShow(string $transferNumber): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $transfer = InventoryTransfer::where('transfer_number', $transferNumber)
            ->where('vendor_id', $vendorId)
            ->with(['sourceWarehouse', 'destinationWarehouse', 'items'])
            ->firstOrFail();

        return ApiResponse::success(new InventoryTransferResource($transfer));
    }
}
