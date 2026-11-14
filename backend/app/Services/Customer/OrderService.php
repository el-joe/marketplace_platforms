<?php

namespace App\Services\Customer;

use App\Enums\CancelActor;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SubOrder;
use App\Services\OrderCancellationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderService
{
    private const PRE_SHIPMENT_STATUSES = ['placed', 'confirmed'];

    public function __construct(
        private readonly OrderCancellationService $cancellation,
    ) {}

    public function listForCustomer(Customer $customer, array $filters): LengthAwarePaginator
    {
        $query = Order::where('customer_id', $customer->id)
            ->with(['subOrders.items.vendorListing', 'subOrders.vendor'])
            ->orderByDesc('placed_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('placed_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('placed_at', '<=', $filters['date_to']);
        }

        return $query->paginate(20);
    }

    public function findForCustomer(Customer $customer, string $orderNumber): ?Order
    {
        return Order::where('order_number', $orderNumber)
            ->where('customer_id', $customer->id)
            ->with([
                'subOrders.items.productVariant' => fn ($q) => $q->withTrashed(),
                'subOrders.items.vendorListing',
                'subOrders.vendor',
                'subOrders.carrier',
                'subOrders.statusHistories',
                'statusHistories',
                'transactions',
            ])
            ->first();
    }

    public function canCancel(Order $order): bool
    {
        return in_array($order->status->value, self::PRE_SHIPMENT_STATUSES, true);
    }

    /**
     * Customer self-service cancellation of a pre-shipment order, through the single cancellation engine:
     * state-machine transitions and history, reserved stock released, wallet/gateway tenders refunded and
     * coupon/loyalty usage reversed.
     *
     * @throws \DomainException when a sub-order is already past the customer cancellation boundary
     */
    public function cancel(Order $order, string $reason): void
    {
        $this->cancellation->cancel($order, CancelActor::Customer, $reason);
    }

    public function trackSubOrder(Customer $customer, string $subOrderId): ?SubOrder
    {
        return SubOrder::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
            ->with(['carrier', 'shipments.trackingEvents'])
            ->find($subOrderId);
    }
}
