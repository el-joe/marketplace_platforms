<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\CreateWarehouseRequest;
use App\Http\Requests\Vendor\UpdateWarehouseRequest;
use App\Http\Resources\Vendor\WarehouseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Warehouse;
use App\Services\VendorWarehouseService;

class WarehouseController extends Controller
{
    public function __construct(private readonly VendorWarehouseService $warehouseService) {}

    public function index(): \Illuminate\Http\JsonResponse
    {
        $vendorId = auth('vendor')->user()->vendor_id;

        $warehouses = Warehouse::where('owner_vendor_id', $vendorId)
            ->where('type', 'seller_owned')
            ->with(['country', 'address'])
            ->get();

        return ApiResponse::success(WarehouseResource::collection($warehouses)->resolve());
    }

    public function store(CreateWarehouseRequest $request): \Illuminate\Http\JsonResponse
    {
        $vendor    = auth('vendor')->user()->vendor;
        $warehouse = $this->warehouseService->registerWarehouse($request->validated(), $vendor);

        return ApiResponse::success(
            new WarehouseResource($warehouse->load(['country', 'address'])),
            'Warehouse registered.',
            201
        );
    }

    public function update(UpdateWarehouseRequest $request, string $id): \Illuminate\Http\JsonResponse
    {
        $warehouse = $this->resolveOwnedWarehouse($id);
        $vendor    = auth('vendor')->user()->vendor;

        $updated = $this->warehouseService->updateWarehouse($warehouse, $request->validated(), $vendor);

        return ApiResponse::success(new WarehouseResource($updated), 'Warehouse updated.');
    }

    public function destroy(string $id): \Illuminate\Http\JsonResponse
    {
        $warehouse = $this->resolveOwnedWarehouse($id);
        $vendor    = auth('vendor')->user()->vendor;

        $this->warehouseService->deactivate($warehouse, $vendor);

        return ApiResponse::success(null, 'Warehouse deactivated.');
    }

    private function resolveOwnedWarehouse(string $id): Warehouse
    {
        $vendorId = auth('vendor')->user()->vendor_id;

        return Warehouse::where('id', $id)
            ->where('owner_vendor_id', $vendorId)
            ->where('type', 'seller_owned')
            ->firstOrFail();
    }
}
