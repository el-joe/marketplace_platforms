<?php

namespace App\Services;

use App\Enums\CancelActor;
use App\Events\OrderCompleted;
use App\Events\SubOrderDelivered;
use App\Events\SubOrderReturned;
use App\Events\SubOrderShipped;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\SubOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * enhancement.md P-08: the single place every sub-order status change goes
 * through. Replaces the ad-hoc `$subOrder->update(['status' => ...])` calls
 * scattered across Delivery/AssignmentController,
 * Services/Delivery/AssignmentService and Partner/OrderController.
 *
 * transition() validates the move is legal (SubOrder::STATUS_TRANSITIONS,
 * via the existing HasStateMachine trait), writes order_status_histories,
 * updates the item fulfillment statuses, rolls up the parent order's
 * status, and fires the domain event for the target status.
 */
class OrderStateMachine
{
    /** Sub-order status => order_items.fulfillment_status it maps to. */
    private const ITEM_STATUS_MAP = [
        'shipped'   => 'shipped',
        'delivered' => 'delivered',
        'returned'  => 'returned',
        'cancelled' => 'cancelled',
    ];

    /**
     * @throws InvalidOrderTransitionException
     */
    public function transition(SubOrder $subOrder, string $to, CancelActor $actor, array $meta = []): SubOrder
    {
        $from = $subOrder->status instanceof \BackedEnum ? $subOrder->status->value : $subOrder->status;

        if ($from === $to) {
            return $subOrder;
        }

        if (! $subOrder->canTransitionTo($to)) {
            throw InvalidOrderTransitionException::forSubOrder($from, $to);
        }

        DB::transaction(function () use ($subOrder, $from, $to, $actor, $meta) {
            $timestamps = match ($to) {
                'shipped'   => ['shipped_at' => now()],
                'delivered' => ['delivered_at' => now()],
                'cancelled' => ['cancelled_at' => now()],
                default     => [],
            };

            $subOrder->update(array_merge(['status' => $to], $timestamps));

            if (isset(self::ITEM_STATUS_MAP[$to])) {
                $subOrder->items()->update(['fulfillment_status' => self::ITEM_STATUS_MAP[$to]]);
            }

            OrderStatusHistory::create([
                'order_id'     => $subOrder->order_id,
                'sub_order_id' => $subOrder->id,
                'from_status'  => $from,
                'to_status'    => $to,
                'reason'       => $meta['reason'] ?? null,
                'metadata'     => array_merge($meta, ['actor' => $actor->value]),
            ]);

            $subOrder->refresh();
            $this->rollupOrder($subOrder->order ?? $subOrder->order()->first());
        });

        $subOrder->refresh();

        match ($to) {
            'shipped'   => Event::dispatch(new SubOrderShipped($subOrder)),
            'delivered' => Event::dispatch(new SubOrderDelivered($subOrder)),
            'returned'  => Event::dispatch(new SubOrderReturned($subOrder)),
            default     => null,
        };

        return $subOrder;
    }

    /**
     * Pure function: given an Order (with its sub-orders loaded/loadable),
     * compute what the order-level status should be. Does not persist
     * anything — callers decide whether/how to apply it.
     *
     * Rules:
     *  - all sub-orders 'completed'                       -> completed
     *  - all sub-orders in {cancelled, refunded}           -> cancelled
     *  - all sub-orders in {delivered, completed}          -> delivered
     *  - some delivered, rest in {cancelled, refunded, delivered, completed}
     *    (i.e. every sub-order is terminal and at least one delivered)     -> delivered
     *  - some delivered/completed but not all terminal      -> partially_delivered
     *  - all sub-orders in {shipped, out_for_delivery, delivered, completed} -> shipped
     *  - some shipped/out_for_delivery                     -> partially_shipped
     *  - otherwise                                          -> unchanged (null)
     */
    public function rollupOrderStatus(Order $order): ?string
    {
        $order->loadMissing('subOrders');

        $statuses = $order->subOrders->pluck('status')->map(
            fn ($s) => $s instanceof \BackedEnum ? $s->value : $s
        );

        if ($statuses->isEmpty()) {
            return null;
        }

        $deliveredLike = ['delivered', 'completed'];
        $terminal      = ['delivered', 'completed', 'cancelled', 'refunded'];

        if ($statuses->every(fn ($s) => $s === 'completed')) {
            return 'completed';
        }

        if ($statuses->every(fn ($s) => in_array($s, ['cancelled', 'refunded'], true))) {
            return 'cancelled';
        }

        if ($statuses->every(fn ($s) => in_array($s, $deliveredLike, true))) {
            return 'delivered';
        }

        if ($statuses->contains(fn ($s) => in_array($s, $deliveredLike, true))
            && $statuses->every(fn ($s) => in_array($s, $terminal, true))) {
            // mix of delivered/completed + cancelled/refunded, all terminal.
            return 'delivered';
        }

        if ($statuses->contains(fn ($s) => in_array($s, $deliveredLike, true))) {
            return 'partially_delivered';
        }

        if ($statuses->every(fn ($s) => in_array($s, ['shipped', 'out_for_delivery', 'delivered', 'completed'], true))) {
            return 'shipped';
        }

        if ($statuses->contains(fn ($s) => in_array($s, ['shipped', 'out_for_delivery'], true))) {
            return 'partially_shipped';
        }

        return null;
    }

    private function rollupOrder(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $order->refresh();
        $newStatus = $this->rollupOrderStatus($order);

        $currentStatus = $order->status instanceof \BackedEnum ? $order->status->value : $order->status;

        if (! $newStatus || $newStatus === $currentStatus) {
            return;
        }

        $order->update([
            'status'       => $newStatus,
            'completed_at' => $newStatus === 'completed' ? now() : $order->completed_at,
            'cancelled_at' => $newStatus === 'cancelled' ? now() : $order->cancelled_at,
        ]);

        OrderStatusHistory::create([
            'order_id'     => $order->id,
            'sub_order_id' => null,
            'from_status'  => $currentStatus,
            'to_status'    => $newStatus,
            'reason'       => '[Auto] Order status rolled up from sub-order changes.',
        ]);

        if ($newStatus === 'completed') {
            Event::dispatch(new OrderCompleted($order));
        }
    }
}
