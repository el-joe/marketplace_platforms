<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\SubOrderDetailResource;
use App\Http\Resources\Vendor\SubOrderListResource;
use App\Http\Responses\ApiResponse;
use App\Models\SubOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;
        $issueStatuses = ['cancelled', 'returned', 'refunded'];

        $query = SubOrder::where('vendor_id', $vendorId)
            ->with(['order:id,shipping_address_snapshot,currency,placed_at'])
            ->withCount('items')
            ->when($request->boolean('issues'), fn ($q) => $q->where(
                fn ($q) => $q->where('sla_breached', true)->orWhereIn('status', $issueStatuses)
            ))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $q->where('sub_order_number', 'like', '%' . $request->search . '%'))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest();

        return ApiResponse::paginated($query->paginate((int) ($request->per_page ?? 20)), SubOrderListResource::class);
    }

    public function show(string $subOrderNumber): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $subOrder = SubOrder::where('sub_order_number', $subOrderNumber)
            ->where('vendor_id', $vendorId)
            ->with([
                'items',
                'order:id,order_number,shipping_address_snapshot,currency,payment_method,placed_at',
                'carrier:id,name',
                'shipments.trackingEvents',
            ])
            ->firstOrFail();

        return ApiResponse::success(new SubOrderDetailResource($subOrder));
    }
}
