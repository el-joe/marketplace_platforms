<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\Order;
use App\Models\SubOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderService
{
    private const PRE_SHIPMENT_STATUSES = ['placed', 'confirmed'];

    public function listForCustomer(Customer $customer, array $filters): LengthAwarePaginator
    {
        $query = Order::where('customer_id', $customer->id)
            ->with(['subOrders.items.vendorListing', 'subOrders.vendor'])
            ->orderByDesc('placed_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('placed_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('placed_at', '<=', $filters['date_to']);
        }

        return $query->paginate(20);
    }

    public function findForCustomer(Customer $customer, string $orderNumber): ?Order
    {
        return Order::where('order_number', $orderNumber)
            ->where('customer_id', $customer->id)
            ->with([
                'subOrders.items.productVariant',
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

    public function cancel(Order $order, string $reason): void
    {
        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $order->subOrders()
            ->whereIn('status', self::PRE_SHIPMENT_STATUSES)
            ->each(function (SubOrder $subOrder) use ($reason): void {
                $subOrder->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);
            });

        $order->statusHistories()->create([
            'from_status' => $order->getOriginal('status'),
            'to_status' => 'cancelled',
            'reason' => $reason,
        ]);
    }

    public function trackSubOrder(Customer $customer, string $subOrderId): ?SubOrder
    {
        return SubOrder::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
            ->with(['carrier', 'shipments.trackingEvents'])
            ->find($subOrderId);
    }
}
