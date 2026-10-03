<?php

namespace App\Http\Controllers\Partner\Api;

use App\Enums\ReturnRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\ReturnRequestDetailResource;
use App\Http\Resources\Vendor\ReturnRequestListResource;
use App\Http\Responses\ApiResponse;
use App\Models\ReturnRequest;
use App\Notifications\Customer\ReturnApprovedNotification;
use App\Notifications\Customer\ReturnRejectedNotification;
use App\Services\Vendor\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ReturnController extends Controller
{
    public function __construct(private ReturnService $returnService) {}

    public function index(Request $request): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $query = ReturnRequest::where('vendor_id', $vendorId)
            ->with(['order:id,order_number', 'customer:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
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
                'customer:id,name',
                'items.orderItem:id,product_snapshot',
                'messages' => fn ($q) => $q->visibleToVendor()->oldest()->with('attachments'),
            ])
            ->firstOrFail();

        $detail = $this->returnService->getDetail($return, $vendorId);

        return ApiResponse::success(new ReturnRequestDetailResource($detail, $return));
    }

    /** POST /api/partner/v1/returns/{returnNumber}/approve */
    public function approve(string $returnNumber): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $returnRequest = ReturnRequest::where('return_number', $returnNumber)
            ->where('vendor_id', $vendorId)
            ->firstOrFail();

        if ($returnRequest->status !== ReturnRequestStatus::Requested) {
            return response()->json(['success' => false, 'message' => 'This return cannot be reviewed in its current state.'], 422);
        }

        DB::transaction(function () use ($returnRequest) {
            $returnRequest->update(['status' => ReturnRequestStatus::Approved]);
            Notification::send($returnRequest->customer, new ReturnApprovedNotification($returnRequest));
        });

        return response()->json(['success' => true, 'message' => 'Return approved successfully.']);
    }

    /** POST /api/partner/v1/returns/{returnNumber}/reject */
    public function reject(Request $request, string $returnNumber): JsonResponse
    {
        $vendorId = Auth::guard('vendor_api')->user()->vendor_id;

        $returnRequest = ReturnRequest::where('return_number', $returnNumber)
            ->where('vendor_id', $vendorId)
            ->firstOrFail();

        $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        if ($returnRequest->status !== ReturnRequestStatus::Requested) {
            return response()->json(['success' => false, 'message' => 'This return cannot be reviewed in its current state.'], 422);
        }

        DB::transaction(function () use ($returnRequest, $request) {
            $returnRequest->update([
                'status' => ReturnRequestStatus::Rejected,
                'rejection_reason' => $request->rejection_reason,
            ]);
            Notification::send($returnRequest->customer, new ReturnRejectedNotification($returnRequest));
        });

        return response()->json(['success' => true, 'message' => 'Return rejected successfully.']);
    }
}
