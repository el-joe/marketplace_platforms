<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Dispute\DisputeMessageRequest;
use App\Http\Requests\Customer\Dispute\DisputeStoreRequest;
use App\Http\Resources\Customer\DisputeMessageResource;
use App\Http\Resources\Customer\DisputeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Dispute;
use App\Models\Order;
use App\Notifications\Admin\DisputeOpened;
use App\Notifications\Vendor\DisputeOpenedAgainstYou;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class DisputeController extends Controller
{
    public function store(DisputeStoreRequest $request, string $country, string $orderNumber): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $order = Order::where('order_number', $orderNumber)
            ->where('customer_id', $customer->id)
            ->with('subOrders')
            ->first();

        if (!$order) {
            return ApiResponse::error(__('common.exceptions.order.not_found'), [], 404);
        }

        $subOrder = $order->subOrders->first();

        if (!$subOrder) {
            return ApiResponse::error(__('common.exceptions.dispute.no_suborders'), [], 422);
        }

        $dispute = Dispute::create([
            'dispute_number'   => 'DSP-' . strtoupper(Str::random(8)),
            'order_id'         => $order->id,
            'sub_order_id'     => $subOrder->id,
            'customer_id'      => $customer->id,
            'vendor_id'        => $subOrder->vendor_id,
            'reason'           => $request->validated('reason'),
            'description'      => $request->validated('description'),
            'status'           => 'open',
        ]);

        $dispute->messages()->create([
            'sender_user_id' => $customer->id,
            'sender_role'    => 'customer',
            'message'        => $request->validated('description'),
            'is_internal_note' => false,
        ]);

        $subOrder->vendor->load('vendorAdmins');
        Notification::send($subOrder->vendor->vendorAdmins, new DisputeOpenedAgainstYou($dispute));

        Notification::send(
            Admin::permission('disputes.resolve')->get(),
            new DisputeOpened($dispute),
        );

        return ApiResponse::success(new DisputeResource($dispute->load('messages')), __('common.exceptions.dispute.opened'), 201);
    }

    public function index(string $country): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $paginator = Dispute::where('customer_id', $customer->id)
            ->with('order')
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated($paginator, DisputeResource::class);
    }

    public function show(string $country, string $disputeNumber): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $dispute = Dispute::where('dispute_number', $disputeNumber)
            ->where('customer_id', $customer->id)
            ->with(['messages' => fn ($q) => $q->where('is_internal_note', false)->with('files')])
            ->first();

        if (!$dispute) {
            return ApiResponse::error(__('common.exceptions.dispute.not_found'), [], 404);
        }

        return ApiResponse::success(new DisputeResource($dispute));
    }

    public function addMessage(DisputeMessageRequest $request, string $country, string $disputeNumber): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $dispute = Dispute::where('dispute_number', $disputeNumber)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$dispute) {
            return ApiResponse::error(__('common.exceptions.dispute.not_found'), [], 404);
        }

        $message = $dispute->messages()->create([
            'sender_user_id'   => $customer->id,
            'sender_role'      => 'customer',
            'message'          => $request->validated('message'),
            'is_internal_note' => false,
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('disputes', 'public');
            $message->files()->create([
                'path'          => $path,
                'disk'          => 'public',
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
                'original_name' => $file->getClientOriginalName(),
            ]);
        }

        return ApiResponse::success(new DisputeMessageResource($message), __('common.exceptions.dispute.message_sent'), 201);
    }
}
