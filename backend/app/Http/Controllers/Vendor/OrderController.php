<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\CancelOrderRequest;
use App\Http\Requests\Vendor\OrderListRequest;
use App\Http\Requests\Vendor\ShipOrderRequest;
use App\Http\Resources\Vendor\SubOrderDetailResource;
use App\Http\Resources\Vendor\SubOrderListResource;
use App\Http\Responses\ApiResponse;
use App\Models\SubOrder;
use App\Services\Vendor\OrderFulfillmentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function __construct(private readonly OrderFulfillmentService $fulfillmentService) {}

    public function index(OrderListRequest $request): \Illuminate\Http\JsonResponse
    {
        $vendorId = auth('vendor')->user()->vendor_id;

        $issueStatuses = ['cancelled', 'returned', 'refunded'];

        $query = SubOrder::where('vendor_id', $vendorId)
            ->with(['order:id,shipping_address_snapshot,currency,placed_at'])
            ->withCount('items')
            ->when($request->boolean('issues'), fn ($q) => $q->where(
                fn ($q) => $q->where('sla_breached', true)->orWhereIn('status', $issueStatuses)
            ))
            ->when(! $request->boolean('issues') && $request->statuses(), fn ($q) => $q->whereIn('status', $request->statuses()))
            ->when($request->filled('search'), fn ($q) => $q->where('sub_order_number', 'like', '%'.$request->string('search').'%'))
            ->when($request->date_from, fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,   fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest();

        $perPage = (int) ($request->per_page ?? 20);
        $orders  = $query->paginate($perPage);

        return ApiResponse::paginated($orders, SubOrderListResource::class);
    }

    public function show(string $subOrderNumber): \Illuminate\Http\JsonResponse
    {
        $subOrder = $this->resolveSubOrder($subOrderNumber);

        Gate::authorize('view', $subOrder);

        $subOrder->load([
            'items',
            'order:id,order_number,shipping_address_snapshot,currency,payment_method,placed_at',
            'carrier:id,name',
            'shipments.trackingEvents',
        ]);

        return ApiResponse::success(new SubOrderDetailResource($subOrder));
    }

    public function ship(ShipOrderRequest $request, string $subOrderNumber): \Illuminate\Http\JsonResponse
    {
        $subOrder = $this->resolveSubOrder($subOrderNumber);

        Gate::authorize('ship', $subOrder);

        $updated = $this->fulfillmentService->ship($subOrder, $request->validated());

        return ApiResponse::success(
            new SubOrderDetailResource($updated->load(['items', 'order:id,order_number,shipping_address_snapshot,currency,payment_method,placed_at', 'shipments.trackingEvents'])),
            'Order marked as shipped.'
        );
    }

    public function cancel(CancelOrderRequest $request, string $subOrderNumber): \Illuminate\Http\JsonResponse
    {
        $subOrder = $this->resolveSubOrder($subOrderNumber);

        Gate::authorize('cancel', $subOrder);

        $updated = $this->fulfillmentService->cancel($subOrder, $request->reason);

        return ApiResponse::success(
            new SubOrderDetailResource($updated->load(['items', 'order:id,order_number,shipping_address_snapshot,currency,payment_method,placed_at', 'shipments.trackingEvents'])),
            'Order cancelled.'
        );
    }

    public function packingSlip(string $subOrderNumber): \Illuminate\Http\JsonResponse
    {
        $subOrder = $this->resolveSubOrder($subOrderNumber);

        Gate::authorize('packingSlip', $subOrder);

        // Signed URL expires in 15 minutes; PDF generation handled by storage layer or separate service
        $url = Storage::temporaryUrl(
            "packing-slips/{$subOrder->id}.pdf",
            now()->addMinutes(15)
        );

        return ApiResponse::success(['url' => $url], 'Packing slip URL generated.');
    }

    private function resolveSubOrder(string $subOrderNumber): SubOrder
    {
        return SubOrder::where('sub_order_number', $subOrderNumber)->firstOrFail();
    }
}
