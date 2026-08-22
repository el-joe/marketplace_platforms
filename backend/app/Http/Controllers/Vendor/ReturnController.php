<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\ReturnListRequest;
use App\Http\Requests\Vendor\ReturnMessageRequest;
use App\Http\Resources\Vendor\ReturnRequestDetailResource;
use App\Http\Resources\Vendor\ReturnRequestListResource;
use App\Http\Resources\Vendor\ReturnRequestMessageResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\NotifyAdminReturnCommentJob;
use App\Models\ReturnRequest;
use App\Services\Vendor\ReturnService;

class ReturnController extends Controller
{
    public function __construct(private readonly ReturnService $returnService) {}

    public function index(ReturnListRequest $request): \Illuminate\Http\JsonResponse
    {
        $vendorId = auth('vendor')->user()->vendor_id;

        $filters = $request->validated();

        $query = ReturnRequest::where('vendor_id', $vendorId)
            ->with(['order:id,order_number', 'customer:id,first_name,last_name'])
            ->when($filters['status'] ?? null,    fn ($q, $v) => $q->where('status', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null,   fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest();

        return ApiResponse::paginated($query->paginate(20), ReturnRequestListResource::class);
    }

    public function show(string $returnNumber): \Illuminate\Http\JsonResponse
    {
        $return = $this->resolveReturn($returnNumber);

        $return->load([
            'order:id,order_number',
            'subOrder:id,sub_order_number',
            'customer:id,first_name,last_name',
            'items.orderItem:id,product_snapshot',
            'messages' => fn ($q) => $q->visibleToVendor()->oldest()->with('attachments'),
        ]);

        $detail = $this->returnService->getDetail($return, auth('vendor')->user()->vendor_id);

        return ApiResponse::success(new ReturnRequestDetailResource($detail, $return));
    }

    public function addMessage(ReturnMessageRequest $request, string $returnNumber): \Illuminate\Http\JsonResponse
    {
        $return = $this->resolveReturn($returnNumber);
        $actor  = auth('vendor')->user();

        $message = $this->returnService->addComment(
            $return,
            $actor,
            $request->message,
            $request->file('attachments') ?? []
        );

        NotifyAdminReturnCommentJob::dispatch($message);

        return ApiResponse::success(
            new ReturnRequestMessageResource($message),
            'Message sent.',
            201
        );
    }

    private function resolveReturn(string $returnNumber): ReturnRequest
    {
        $vendorId = auth('vendor')->user()->vendor_id;

        return ReturnRequest::where('return_number', $returnNumber)
            ->where('vendor_id', $vendorId)
            ->firstOrFail();
    }
}
