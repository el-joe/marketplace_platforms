<?php

namespace App\Http\Controllers\Partner\Api;

use App\Enums\CancelActor;
use App\Enums\SubOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\SubOrderDetailResource;
use App\Http\Resources\Vendor\SubOrderListResource;
use App\Http\Responses\ApiResponse;
use App\Models\InventoryMovement;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\ShippingCompanySupervisor;
use App\Models\SubOrder;
use App\Models\VendorListing;
use App\Models\WarehouseInventory;
use App\Notifications\Carrier\NewUnassignedShipmentArrived;
use App\Services\Inventory\InventoryService;
use App\Services\OrderStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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

            $this->stateMachine->transition(
                $subOrder,
                'shipped',
                CancelActor::Vendor,
                [
                    'vendor_id' => $vendorId,
                    'vendor_admin' => $adminId,
                    'tracking_number' => $request->input('tracking_number'),
                    'carrier_id' => $subOrder->carrier_id,
                    'action' => 'shipped',
                ],
            );

            $variantIds = $subOrder->items->pluck('product_variant_id');
            $variantWeights = ProductVariant::whereIn('id', $variantIds)->pluck('weight_grams', 'id');
            $weightGrams = $subOrder->items->sum(
                fn ($item) => ($variantWeights[$item->product_variant_id] ?? 0) * $item->quantity
            );

            $shipment = Shipment::create([
                'sub_order_id' => $subOrder->id,
                'carrier_id' => $subOrder->carrier_id,
                'tracking_number' => $request->input('tracking_number'),
                'weight_grams' => $weightGrams,
                'shipping_cost_actual' => $subOrder->shipping,
                'status' => 'label_created',
            ]);

            $supervisors = ShippingCompanySupervisor::receivingNotifications()->get();
            if ($supervisors->isNotEmpty()) {
                Notification::send($supervisors, new NewUnassignedShipmentArrived($shipment));
            }

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

        DB::transaction(function () use ($subOrder) {
            $fromStatus = $subOrder->status->value;
            $subOrder->update(['status' => 'out_for_delivery']);

            OrderStatusHistory::create([
                'order_id' => $subOrder->order_id,
                'sub_order_id' => $subOrder->id,
                'from_status' => $fromStatus,
                'to_status' => 'out_for_delivery',
                'changed_by_admin_id' => null,
                'metadata' => json_encode([
                    'vendor_id' => $this->vendorId(),
                    'vendor_admin' => $this->adminId(),
                    'action' => 'marked_out_for_delivery',
                ]),
            ]);
        });

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

        $vendorId = $this->vendorId();
        $adminId = $this->adminId();

        DB::transaction(function () use ($request, $subOrder, $vendorId, $adminId) {
            $fromStatus = $subOrder->status->value;

            $subOrder->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $request->input('reason'),
            ]);

            foreach ($subOrder->items as $item) {
                $vendorListing = VendorListing::where('product_variant_id', $item->product_variant_id)
                    ->where('vendor_id', $vendorId)
                    ->first();

                if (! $vendorListing) {
                    continue;
                }

                $inventory = WarehouseInventory::where('vendor_listing_id', $vendorListing->id)
                    ->where('warehouse_id', $subOrder->warehouse_id)
                    ->first();

                if (! $inventory) {
                    continue;
                }

                $newReserved = max(0, $inventory->quantity_reserved - $item->quantity);
                $inventory->update(['quantity_reserved' => $newReserved]);

                InventoryMovement::create([
                    'warehouse_inventory_id' => $inventory->id,
                    'movement_type' => 'reservation_release',
                    'quantity_delta' => $item->quantity,
                    'quantity_after' => $inventory->quantity_on_hand,
                    'reference_type' => 'order',
                    'reference_id' => $subOrder->id,
                    'reason' => 'order_cancelled_vendor',
                    'created_by_user_id' => $adminId,
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $subOrder->order_id,
                'sub_order_id' => $subOrder->id,
                'from_status' => $fromStatus,
                'to_status' => 'cancelled',
                'changed_by_admin_id' => null,
                'reason' => $request->input('reason'),
                'metadata' => json_encode([
                    'vendor_id' => $vendorId,
                    'vendor_admin' => $adminId,
                    'reason' => $request->input('reason'),
                    'notes' => $request->input('reason_notes'),
                    'action' => 'cancelled',
                ]),
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Order cancelled successfully.']);
    }
}
