<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\WarehouseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class WarehouseController extends Controller
{
    public function index(): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $warehouses = Warehouse::where('owner_vendor_id', $vendorId)
            ->where('type', 'seller_owned')
            ->with(['country', 'address'])
            ->get();

        return ApiResponse::success(WarehouseResource::collection($warehouses)->resolve());
    }
}
