<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\ReturnRequestMessageRequest;
use App\Http\Requests\Api\Customer\ReturnRequestStoreRequest;
use App\Http\Resources\Customer\ReturnRequestMessageResource;
use App\Http\Resources\Customer\ReturnRequestResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\ReturnRequest;
use App\Notifications\Vendor\ReturnRequestSubmitted;
use App\Services\ReturnRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class ReturnRequestController extends Controller
{
    public function __construct(private readonly ReturnRequestService $returnRequestService) {}

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

        // enhancement.md P-10: create() enforces every eligibility rule
        // (delivered, within window, returnable category, quantity not
        // already returned) and splits a mixed-sub-order item list into
        // one ReturnRequest per sub-order rather than rejecting it.
        $returnRequests = $this->returnRequestService->create(
            customer: $customer,
            orderItemIds: $request->validated('order_item_ids'),
            reason: $request->validated('reason'),
            returnType: $request->validated('return_type'),
            reasonDescription: $request->validated('reason_description'),
            pickupAddressId: $request->validated('pickup_address_id'),
        );

        foreach ($returnRequests as $returnRequest) {
            $returnRequest->loadMissing('vendor.vendorAdmins');
            if ($returnRequest->vendor?->vendorAdmins->isNotEmpty()) {
                Notification::send($returnRequest->vendor->vendorAdmins, new ReturnRequestSubmitted($returnRequest));
            }
        }

        $primary = $returnRequests->first()->load(['order', 'items.orderItem']);

        return ApiResponse::success(
            new ReturnRequestResource($primary),
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
