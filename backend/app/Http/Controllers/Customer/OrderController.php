<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Order\OrderCancelRequest;
use App\Http\Requests\Customer\Order\OrderListRequest;
use App\Http\Resources\Customer\OrderResource;
use App\Http\Responses\ApiResponse;
use App\Services\Customer\OrderService;
use App\Services\Customer\OrderTrackingService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderTrackingService $orderTrackingService,
    ) {}

    public function index(OrderListRequest $request, string $country): JsonResponse
    {
        $customer = auth('customer')->user();
        $paginator = $this->orderService->listForCustomer($customer, $request->validated());

        return ApiResponse::paginated($paginator, OrderResource::class);
    }

    public function show(string $country, string $orderNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->orderService->findForCustomer($customer, $orderNumber);

        if (!$order) {
            return ApiResponse::error(__('common.exceptions.order.not_found'), [], 404);
        }

        return ApiResponse::success($this->orderTrackingService->getOrderDetail($order));
    }

    public function cancel(OrderCancelRequest $request, string $country, string $orderNumber): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->orderService->findForCustomer($customer, $orderNumber);

        if (!$order) {
            return ApiResponse::error(__('common.exceptions.order.not_found'), [], 404);
        }

        if (!$this->orderService->canCancel($order)) {
            return ApiResponse::error(__('common.exceptions.order.cannot_cancel'), [], 422);
        }

        $this->orderService->cancel($order, $request->validated('reason'));

        return ApiResponse::success(null, __('common.exceptions.order.cancelled'));
    }

    public function trackSubOrder(string $country, string $subOrderId): JsonResponse
    {
        $customer = auth('customer')->user();
        $tracking = $this->orderTrackingService->getSubOrderTracking($subOrderId, $customer);

        if (!$tracking) {
            return ApiResponse::error(__('common.exceptions.order.suborder_not_found'), [], 404);
        }

        return ApiResponse::success($tracking);
    }
}
