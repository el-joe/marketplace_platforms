<?php

namespace App\Http\Controllers\Partner\Api;

use App\Enums\CancelActor;
use App\Enums\SubOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\SubOrderDetailResource;
use App\Http\Resources\Vendor\SubOrderListResource;
use App\Http\Responses\ApiResponse;
use App\Models\OrderStatusHistory;
use App\Models\SubOrder;
use App\Services\Inventory\InventoryService;
use App\Services\OrderCancellationService;
use App\Services\OrderStateMachine;
use App\Services\Shipping\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine = new OrderStateMachine,
    ) {}

    private function vendorId(): string
    {
        return Auth::guard('vendor_api')->user()->vendor_id;
    }

    private function vendorSubOrder(string $subOrderNumber): SubOrder
    {
        return SubOrder::where('vendor_id', $this->vendorId())
            ->where('sub_order_number', $subOrderNumber)
            ->firstOrFail();
    }

    private function adminId(): string
    {
        return Auth::guard('vendor_api')->user()->id;
    }

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
            ->when($request->filled('search'), fn ($q) => $q->where('sub_order_number', 'like', '%'.$request->search.'%'))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
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

    /** POST /api/partner/v1/orders/{subOrderNumber}/confirm */
    public function confirm(string $subOrderNumber): JsonResponse
    {
        $subOrder = $this->vendorSubOrder($subOrderNumber);

        if ($subOrder->status !== SubOrderStatus::Placed) {
            return response()->json(['success' => false, 'message' => 'Order cannot be confirmed in its current state.'], 422);
        }

        $subOrder->update(['status' => 'confirmed']);

        OrderStatusHistory::create([
            'order_id' => $subOrder->order_id,
            'sub_order_id' => $subOrder->id,
            'from_status' => 'placed',
            'to_status' => 'confirmed',
            'changed_by_admin_id' => null,
            'metadata' => json_encode([
                'vendor_id' => $this->vendorId(),
                'vendor_admin' => $this->adminId(),
                'action' => 'confirmed',
            ]),
        ]);

        return response()->json(['success' => true, 'message' => 'Order confirmed successfully.']);
    }

    /** POST /api/partner/v1/orders/{subOrderNumber}/ship */
    public function ship(Request $request, string $subOrderNumber): JsonResponse
    {
        $request->validate(['tracking_number' => ['required', 'string', 'max:100']]);

        $subOrder = $this->vendorSubOrder($subOrderNumber);
        $subOrder->loadMissing(['shippingMethod', 'warehouse.country', 'items.allocations']);

        if (! in_array($subOrder->status->value, ['placed', 'confirmed', 'processing', 'packed'])) {
            return response()->json(['success' => false, 'message' => 'Order cannot be shipped in its current state.'], 422);
        }

        if (is_null($subOrder->shipping_method_id)) {
            return response()->json(['success' => false, 'message' => 'No shipping method assigned. Contact support.'], 422);
        }

        if (is_null($subOrder->carrier_id)) {
            return response()->json(['success' => false, 'message' => 'No shipping carrier assigned. Contact support.'], 422);
        }

        $vendorId = $this->vendorId();
        $adminId = $this->adminId();

        DB::transaction(function () use ($request, $subOrder, $vendorId, $adminId) {
            $method = $subOrder->shippingMethod;
            $timezone = $subOrder->warehouse?->country?->timezone ?? 'Asia/Dubai';

            $subOrder->update([
                'tracking_number' => $request->input('tracking_number'),
                'estimated_delivery_date' => $method
                    ? $method->computeEstimatedDeliveryDate($timezone)->toDateString()
                    : now()->addDays(5)->toDateString(),
            ]);

            app(ShipmentService::class)->createForSubOrder(
                $subOrder,
                $request->input('tracking_number'),
                CancelActor::Vendor,
                [
                    'vendor_id' => $vendorId,
                    'vendor_admin' => $adminId,
                ],
                // The vendor ships straight from placed/confirmed: walk the intermediate
                // processing/packed states the state machine requires (as the admin ship does).
                advance: true,
            );

            $inventoryService = app(InventoryService::class);
            foreach ($subOrder->items as $item) {
                $allocations = $item->allocations()->where('status', 'reserved')->get();
                if ($allocations->isEmpty()) {
                    continue;
                }
                $inventoryService->commit(
                    $allocations,
                    'sub_order',
                    $subOrder->id,
                    actorType: 'vendor',
                    actorId: $adminId,
                    reason: 'order_shipped',
                );
            }

            Log::info('SubOrder shipped via API', [
                'sub_order' => $subOrder->sub_order_number,
                'vendor_id' => $vendorId,
                'tracking' => $request->input('tracking_number'),
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Order shipped successfully.']);
    }

    /** POST /api/partner/v1/orders/{subOrderNumber}/out-for-delivery */
    public function markOutForDelivery(string $subOrderNumber): JsonResponse
    {
        $subOrder = $this->vendorSubOrder($subOrderNumber);

        if ($subOrder->status !== SubOrderStatus::Shipped) {
            return response()->json(['success' => false, 'message' => 'Order cannot be updated in its current state.'], 422);
        }

        // Through the state machine: validated transition, history, order rollup and the
        // customer-facing tracking event.
        $this->stateMachine->transition($subOrder, 'out_for_delivery', CancelActor::Vendor, [
            'vendor_id' => $this->vendorId(),
            'vendor_admin' => $this->adminId(),
            'action' => 'marked_out_for_delivery',
        ]);

        return response()->json(['success' => true, 'message' => 'Order marked as out for delivery.']);
    }

    /** POST /api/partner/v1/orders/{subOrderNumber}/deliver */
    public function markDelivered(string $subOrderNumber): JsonResponse
    {
        $subOrder = $this->vendorSubOrder($subOrderNumber);

        if (! in_array($subOrder->status->value, ['shipped', 'out_for_delivery'])) {
            return response()->json(['success' => false, 'message' => 'Order cannot be updated in its current state.'], 422);
        }

        $this->stateMachine->transition(
            $subOrder,
            'delivered',
            CancelActor::Vendor,
            [
                'vendor_id' => $this->vendorId(),
                'vendor_admin' => $this->adminId(),
                'action' => 'marked_delivered',
            ],
        );

        return response()->json(['success' => true, 'message' => 'Order marked as delivered.']);
    }

    /** POST /api/partner/v1/orders/{subOrderNumber}/cancel */
    public function cancel(Request $request, string $subOrderNumber): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:100'],
            'reason_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $subOrder = $this->vendorSubOrder($subOrderNumber);

        $nonCancellableStatuses = ['shipped', 'delivered', 'completed', 'cancelled', 'return_requested', 'returned'];
        if (in_array($subOrder->status->value, $nonCancellableStatuses)) {
            return response()->json(['success' => false, 'message' => 'Order cannot be cancelled in its current state.'], 422);
        }

        $reason = trim($request->input('reason').' '.($request->input('reason_notes') ?? ''));

        // The single cancellation engine: state-machine transition + history, stock released from the
        // reserved allocations, order roll-up and any payment/coupon/loyalty reversal.
        app(OrderCancellationService::class)->cancel($subOrder, CancelActor::Vendor, $reason);

        return response()->json(['success' => true, 'message' => 'Order cancelled successfully.']);
    }
}
