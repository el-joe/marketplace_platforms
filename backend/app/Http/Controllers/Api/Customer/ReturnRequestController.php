<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\ReturnRequestMessageRequest;
use App\Http\Requests\Api\Customer\ReturnRequestStoreRequest;
use App\Http\Resources\Customer\ReturnRequestMessageResource;
use App\Http\Resources\Customer\ReturnRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Notifications\Vendor\ReturnRequestSubmitted;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ReturnRequestController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $paginator = ReturnRequest::where('customer_id', $customer->id)
            ->with(['order', 'items.orderItem'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated($paginator, ReturnRequestResource::class);
    }

    public function store(ReturnRequestStoreRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $items = OrderItem::with('order')->whereIn('id', $request->validated('order_item_ids'))->get();
        $firstItem = $items->first();

        $returnRequest = DB::transaction(function () use ($request, $customer, $items, $firstItem) {
            $returnRequest = ReturnRequest::create([
                'return_number' => 'RET-'.strtoupper(Str::random(10)),
                'order_id' => $firstItem->order_id,
                'sub_order_id' => $firstItem->sub_order_id,
                'customer_id' => $customer->id,
                'vendor_id' => $firstItem->vendor_id,
                'reason' => $request->validated('reason'),
                'reason_description' => $request->validated('reason_description'),
                'return_type' => $request->validated('return_type'),
                'status' => 'requested',
                'pickup_address_id' => $request->validated('pickup_address_id'),
            ]);

            foreach ($items as $item) {
                $returnRequest->items()->create([
                    'order_item_id' => $item->id,
                    'quantity' => $item->quantity,
                ]);
            }

            return $returnRequest;
        });

        $returnRequest->vendor?->loadMissing('vendorAdmins');
        Notification::send($returnRequest->vendor?->vendorAdmins, new ReturnRequestSubmitted($returnRequest));

        return ApiResponse::success(
            new ReturnRequestResource($returnRequest->load(['order', 'items.orderItem'])),
            __('customer_api.return_request.submitted'),
            201,
        );
    }

    public function show(string $returnNumber): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $returnRequest = ReturnRequest::where('return_number', $returnNumber)
            ->where('customer_id', $customer->id)
            ->with([
                'order',
                'items.orderItem',
                'messages' => fn ($q) => $q->where('is_internal_note', false)->orderBy('created_at'),
            ])
            ->first();

        if (! $returnRequest) {
            return ApiResponse::error(__('customer_api.return_request.not_found'), [], 404);
        }

        return ApiResponse::success(new ReturnRequestResource($returnRequest));
    }

    public function addMessage(ReturnRequestMessageRequest $request, string $returnNumber): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $returnRequest = ReturnRequest::where('return_number', $returnNumber)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $returnRequest) {
            return ApiResponse::error(__('customer_api.return_request.not_found'), [], 404);
        }

        $message = $returnRequest->messages()->create([
            'return_request_id' => $returnRequest->id,
            'sender_user_id' => $customer->id,
            'sender_role' => 'customer',
            'message' => $request->validated('message'),
            'is_internal_note' => false,
            'created_at' => now(),
        ]);

        return ApiResponse::success(new ReturnRequestMessageResource($message), __('customer_api.return_request.message_sent'), 201);
    }
}
