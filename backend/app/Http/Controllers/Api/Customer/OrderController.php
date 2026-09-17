<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\OrderDetailResource;
use App\Http\Resources\Api\Customer\OrderInvoiceResource;
use App\Http\Resources\Api\Customer\OrderListItemResource;
use App\Http\Resources\Api\Customer\OrderTrackingResource;
use App\Http\Resources\Api\Customer\SubOrderDetailResource;
use App\Http\Responses\ApiResponse;
use App\Enums\CancelActor;
use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    private const CANCELLABLE_ORDER_STATUSES = ['placed', 'confirmed'];

    public function index(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $query = Order::where('customer_id', $customer->id)
            ->select(['id', 'customer_id', 'order_number', 'status', 'payment_status', 'currency', 'total', 'placed_at'])
            ->withCount('subOrders')
            ->with(['subOrders' => fn ($q) => $q->select(['id', 'order_id', 'sub_order_number', 'shipping_method_id']),
                'subOrders.shippingMethod:id,badge_label_en,badge_label_ar,badge_color_hex'])
            ->orderByDesc('placed_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $query->where('order_number', 'like', '%'.$request->input('search').'%');
        }

        $paginator = $query->paginate(15);

        return ApiResponse::success([
            'items' => OrderListItemResource::collection(collect($paginator->items())),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], __('customer_api.order.retrieved'));
    }

    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->findOrder($customer, $orderNumber);

        if (! $order) {
            return ApiResponse::error(__('customer_api.order.not_found'), [], 404);
        }

        $order->load([
            'subOrders.items.warrantyPurchase',
            'subOrders.items.shippingMethod',
            'subOrders.vendor:id,store_name',
            'subOrders.carrier',
            'subOrders.shippingMethod',
            'statusHistories',
        ]);

        return ApiResponse::success($this->buildOrderDetail($order), __('customer_api.order.single_retrieved'));
    }

    public function showSubOrder(Request $request, string $orderNumber, string $subOrderNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->findOrder($customer, $orderNumber);

        if (! $order) {
            return ApiResponse::error(__('customer_api.order.not_found'), [], 404);
        }

        $subOrder = SubOrder::where('order_id', $order->id)
            ->where('sub_order_number', $subOrderNumber)
            ->with(['vendor:id,store_name', 'carrier', 'shippingMethod', 'items.shippingMethod', 'shipments.trackingEvents'])
            ->first();

        if (! $subOrder) {
            return ApiResponse::error(__('customer_api.order.sub_order_not_found'), [], 404);
        }

        return ApiResponse::success((new SubOrderDetailResource($subOrder))->toArray($request), __('customer_api.order.sub_order_retrieved'));
    }

    public function tracking(Request $request, string $orderNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->findOrder($customer, $orderNumber);

        if (! $order) {
            return ApiResponse::error(__('customer_api.order.not_found'), [], 404);
        }

        $order->load(['subOrders.carrier', 'subOrders.shipments.trackingEvents']);

        return ApiResponse::success((new OrderTrackingResource($order))->toArray($request), __('customer_api.order.tracking_retrieved'));
    }

    /**
     * enhancement.md P-06: delegates to OrderCancellationService — the
     * single cancellation engine that correctly reverses wallet, card,
     * loyalty, coupon, stock, warranty and marketer state (the previous
     * inline implementation here looked up the wallet refund by a
     * wallet/source_type combination checkout never actually writes, never
     * refunded captured card orders, and released stock on the wrong
     * inventory row).
     *
     * Supports partial cancellation: pass `order_item_ids` (array of
     * order_items.id) to cancel only those items, or `sub_order_id` to
     * cancel one sub-order. With neither, the whole order is cancelled.
     */
    public function cancel(Request $request, string $orderNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->findOrder($customer, $orderNumber);

        if (! $order) {
            return ApiResponse::error(__('customer_api.order.not_found'), [], 404);
        }

        $order->loadMissing('subOrders.items');

        $scope = $order;

        if ($request->filled('order_item_ids')) {
            $itemIds = (array) $request->input('order_item_ids');
            $scope = $order->items()->whereIn('id', $itemIds)->get();

            if ($scope->isEmpty()) {
                return ApiResponse::error(__('customer_api.order.not_found'), [], 404);
            }
        } elseif ($request->filled('sub_order_id')) {
            $subOrder = $order->subOrders->firstWhere('id', $request->input('sub_order_id'));

            if (! $subOrder) {
                return ApiResponse::error(__('customer_api.order.sub_order_not_found'), [], 404);
            }

            $scope = $subOrder;
        } else {
            if (! in_array($order->status?->value, self::CANCELLABLE_ORDER_STATUSES, true)) {
                return ApiResponse::error(__('customer_api.order.cannot_cancel_status'), [], 422);
            }
        }

        try {
            $order = app(OrderCancellationService::class)->cancel(
                $scope,
                CancelActor::Customer,
                $request->input('reason', 'Cancelled by customer'),
            );
        } catch (\DomainException $e) {
            return ApiResponse::error(__('customer_api.order.cannot_cancel_status'), [], 422);
        }

        $order->load(['subOrders.items', 'statusHistories']);

        return ApiResponse::success($this->buildOrderDetail($order), __('customer_api.order.cancelled_successfully'));
    }

    public function invoice(Request $request, string $orderNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->findOrder($customer, $orderNumber);

        if (! $order) {
            return ApiResponse::error(__('customer_api.order.not_found'), [], 404);
        }

        $order->load(['subOrders.items', 'subOrders.vendor:id,store_name']);

        return ApiResponse::success((new OrderInvoiceResource($order))->toArray($request), __('customer_api.order.invoice_retrieved'));
    }

    private function findOrder(Customer $customer, string $orderNumber): ?Order
    {
        return Order::where('customer_id', $customer->id)
            ->where('order_number', $orderNumber)
            ->first();
    }

    private function buildOrderDetail(Order $order): array
    {
        return (new OrderDetailResource($order))->toArray(request());
    }
}
