<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\ReturnRequestDetailResource;
use App\Http\Resources\Vendor\ReturnRequestListResource;
use App\Http\Responses\ApiResponse;
use App\Models\ReturnRequest;
use App\Services\Vendor\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReturnController extends Controller
{
    public function __construct(private ReturnService $returnService) {}

    public function index(Request $request): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $query = ReturnRequest::where('vendor_id', $vendorId)
            ->with(['order:id,order_number', 'customer:id,first_name,last_name'])
            ->when($request->filled('status'),    fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest();

        return ApiResponse::paginated($query->paginate(20), ReturnRequestListResource::class);
    }

    public function show(string $returnNumber): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $return = ReturnRequest::where('return_number', $returnNumber)
            ->where('vendor_id', $vendorId)
            ->with([
                'order:id,order_number',
                'subOrder:id,sub_order_number',
                'customer:id,first_name,last_name',
                'items.orderItem:id,product_snapshot',
                'messages' => fn ($q) => $q->visibleToVendor()->oldest()->with('attachments'),
            ])
            ->firstOrFail();

        $detail = $this->returnService->getDetail($return, $vendorId);

        return ApiResponse::success(new ReturnRequestDetailResource($detail, $return));
    }
}
