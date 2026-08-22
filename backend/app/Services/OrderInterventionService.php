<?php

namespace App\Services;

use App\Enums\PaymentTransactionType;
use App\Models\Admin;
use App\Models\Dispute;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Refund;
use App\Models\SubOrder;
use App\Models\WarehouseInventory;
use App\Notifications\Admin\FraudPatternDetected;
use App\Notifications\Customer\OrderCancelled as CustomerOrderCancelled;
use App\Notifications\Customer\OrderConfirmed;
use App\Notifications\Customer\OrderDelivered;
use App\Notifications\Customer\OrderOutForDelivery;
use App\Notifications\Customer\OrderRefunded;
use App\Notifications\Customer\OrderShipped as CustomerOrderShipped;
use App\Notifications\Vendor\OrderCancelledByAdmin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OrderInterventionService
{
    // State machine constants are canonical on SubOrder (via HasStateMachine).
    // These aliases allow the service to be used as an authoritative reference
    // by controllers that already type-hint this class.
    public const STATUS_TRANSITIONS    = SubOrder::STATUS_TRANSITIONS;
    public const STATUS_LABELS         = SubOrder::STATUS_LABELS;
    public const BLOCK_CANCEL_STATUSES = SubOrder::BLOCK_CANCEL_STATUSES;

    // ─────────────────────────────────────────────────────────────────────────
    // State machine helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return valid next statuses for a sub-order.
     *
     * @return Collection<int, array{value: string, label: string}>
     */
    public function getNextStatuses(SubOrder $subOrder): Collection
    {
        return $subOrder->getNextStatuses();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update order-level status (admin override)
    // ─────────────────────────────────────────────────────────────────────────

    public const ORDER_STATUS_TRANSITIONS = [
        'placed'              => ['confirmed', 'cancelled', 'disputed'],
        'confirmed'           => ['partially_shipped', 'shipped', 'cancelled', 'disputed'],
        'partially_shipped'   => ['shipped', 'partially_delivered', 'cancelled', 'disputed'],
        'shipped'             => ['partially_delivered', 'delivered', 'disputed'],
        'partially_delivered' => ['delivered', 'cancelled', 'disputed'],
        'delivered'           => ['completed', 'refunded', 'disputed'],
        'completed'           => ['refunded', 'disputed'],
        'cancelled'           => ['refunded'],
        'refunded'            => [],
        'disputed'            => ['delivered', 'cancelled', 'refunded', 'completed'],
    ];

    /**
     * Transition an order's own status field, enforcing the admin-level state machine.
     *
     * @throws ValidationException if the transition is not permitted
     */
    public function updateOrderStatus(
        Order $order,
        string $newStatus,
        string $reason,
        string $adminId
    ): void {
        $allowed = self::ORDER_STATUS_TRANSITIONS[$order->status->value] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'new_status' => ["Transition from '{$order->status->value}' to '{$newStatus}' is not allowed."],
            ]);
        }

        DB::transaction(function () use ($order, $newStatus, $reason, $adminId) {
            $old = $order->status->value;

            $updates = ['status' => $newStatus];
            if ($newStatus === 'completed') {
                $updates['completed_at'] = now();
            } elseif ($newStatus === 'cancelled') {
                $updates['cancelled_at'] = now();
            }

            $order->update($updates);

            OrderStatusHistory::create([
                'order_id'            => $order->id,
                'sub_order_id'        => null,
                'from_status'         => $old,
                'to_status'           => $newStatus,
                'changed_by_admin_id' => $adminId,
                'reason'              => $reason ?: '[Admin] Status updated.',
                'metadata'            => ['action' => 'admin_status_override'],
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update sub-order status
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Transition a sub-order to a new status and record the history.
     *
     * @throws ValidationException if the transition is not valid
     */
    public function updateSubOrderStatus(
        SubOrder $subOrder,
        string $newStatus,
        string $reason,
        string $adminId
    ): void {
        if (!$subOrder->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'new_status' => ["Status transition from '{$subOrder->status->value}' to '{$newStatus}' is not allowed."],
            ]);
        }

        DB::transaction(function () use ($subOrder, $newStatus, $reason, $adminId) {
            $old = $subOrder->status->value;

            $updates = ['status' => $newStatus];

            if ($newStatus === 'shipped') {
                $updates['shipped_at'] = now();
            } elseif ($newStatus === 'delivered') {
                $updates['delivered_at'] = now();
            } elseif ($newStatus === 'cancelled') {
                $updates['cancelled_at'] = now();
            }

            $subOrder->update($updates);

            OrderStatusHistory::create([
                'order_id' => $subOrder->order_id,
                'sub_order_id' => $subOrder->id,
                'from_status' => $old,
                'to_status' => $newStatus,
                'changed_by_admin_id' => $adminId,
                'reason' => $reason,
            ]);

            // If all sub-orders of the parent order are now completed/cancelled,
            // auto-advance the parent order status.
            $order = Order::find($subOrder->order_id);
            $order?->syncStatusFromSubOrders(changedByAdminId: $adminId);
        });

        // Notify the customer outside the transaction so a notification failure
        // cannot roll back the status change.
        $subOrder->loadMissing('order.customer');
        $customer = $subOrder->order?->customer;

        if ($customer) {
            $notification = match ($newStatus) {
                'confirmed'        => new OrderConfirmed($subOrder),
                'shipped'          => new CustomerOrderShipped($subOrder),
                'out_for_delivery' => new OrderOutForDelivery(
                    $subOrder,
                    $subOrder->deliveryAssignments()->whereNotNull('delivery_otp')->latest()->first()?->delivery_otp,
                ),
                'delivered'        => new OrderDelivered($subOrder),
                'cancelled'        => new CustomerOrderCancelled($subOrder, $reason),
                'refunded'         => new OrderRefunded($subOrder),
                default            => null,
            };

            if ($notification) {
                $customer->notify($notification);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Force cancel
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Force-cancel an order and all its sub-orders.
     *
     * @param  bool  $force  Override block on shipped/delivered sub-orders
     * @throws \RuntimeException if blocked sub-orders exist and $force is false
     */
    public function forceCancel(
        Order $order,
        string $reason,
        bool $force,
        string $adminId
    ): void {
        $order->loadMissing('subOrders');

        $blocked = $order->subOrders->filter(fn ($so) => $so->blocksCancellation());

        if ($blocked->isNotEmpty() && !$force) {
            throw new \RuntimeException(
                'blocked:' . $blocked->pluck('sub_order_number')->implode(',')
            );
        }

        DB::transaction(function () use ($order, $reason, $adminId) {
            foreach ($order->subOrders as $subOrder) {
                if (in_array($subOrder->status->value, ['cancelled', 'refunded'], true)) {
                    continue;
                }

                $old = $subOrder->status->value;
                $subOrder->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'sub_order_id' => $subOrder->id,
                    'from_status' => $old,
                    'to_status' => 'cancelled',
                    'changed_by_admin_id' => $adminId,
                    'reason' => '[Force Cancel] ' . $reason,
                ]);

                Notification::send($subOrder->vendor->vendorAdmins, new OrderCancelledByAdmin($subOrder, $reason));
            }

            $originalStatus = $order->status->value;
            $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'sub_order_id' => null,
                'from_status' => $originalStatus,
                'to_status' => 'cancelled',
                'changed_by_admin_id' => $adminId,
                'reason' => '[Force Cancel] ' . $reason,
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cancel specific order items (partial cancellation)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Cancel a subset of an order's items, releasing their reserved inventory
     * back to stock and issuing a partial refund for the cancelled value only.
     * Remaining (non-cancelled) items continue their normal fulfillment.
     *
     * @param  Collection<int, \App\Models\OrderItem>  $items
     */
    public function cancelItems(
        Order $order,
        Collection $items,
        string $reason,
        string $adminId
    ): Refund {
        return DB::transaction(function () use ($order, $items, $reason, $adminId) {
            foreach ($items as $item) {
                $item->update(['fulfillment_status' => 'cancelled']);

                $inventory = WarehouseInventory::where('vendor_listing_id', $item->vendor_listing_id)->first();

                if ($inventory) {
                    $inventory->increment('quantity_on_hand', $item->quantity);

                    InventoryMovement::create([
                        'warehouse_inventory_id' => $inventory->id,
                        'movement_type' => 'return',
                        'quantity_delta' => $item->quantity,
                        'quantity_after' => $inventory->fresh()->quantity_on_hand,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'reason' => '[Partial Cancel] ' . $reason,
                        'created_by_user_id' => $adminId,
                    ]);
                }
            }

            $refundAmountCents = (int) $items->sum('line_total');

            $refund = $this->processRefund(
                $order,
                'partial',
                $refundAmountCents / 100,
                'customer_request',
                $reason,
                $items->first()?->sub_order_id,
                false,
                $adminId
            );

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'sub_order_id' => null,
                'from_status' => $order->status->value,
                'to_status' => $order->status->value,
                'changed_by_admin_id' => $adminId,
                'reason' => '[Partial Cancel] ' . $reason,
                'metadata' => [
                    'action' => 'partial_item_cancellation',
                    'item_ids' => $items->pluck('id')->all(),
                    'refund_id' => $refund->id,
                ],
            ]);

            $order->loadMissing('subOrders.vendor.vendorAdmins', 'customer');

            $vendorIds = $items->pluck('vendor_id')->unique()->filter();
            foreach ($vendorIds as $vendorId) {
                $subOrder = $order->subOrders->firstWhere('vendor_id', $vendorId);

                if ($subOrder) {
                    Notification::send($subOrder->vendor->vendorAdmins, new OrderCancelledByAdmin($subOrder, $reason));
                    $order->customer?->notify(new OrderRefunded($subOrder));
                }
            }

            return $refund;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Process refund
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record an admin-initiated refund against an order.
     *
     * @param  string  $refundType  full|partial|shipping_only
     * @param  float|null  $amount  Required when $refundType === 'partial'
     * @throws ValidationException
     */
    public function processRefund(
        Order $order,
        string $refundType,
        ?float $amount,
        string $reason,
        ?string $reasonNotes,
        ?string $subOrderId,
        bool $vendorChargedBack,
        string $adminId
    ): Refund {
        $order->loadMissing('transactions');

        // COD orders never produce a gateway capture; treat as manual refund.
        $isCod = $order->payment_method === 'cod';

        $capturedTx = $order->transactions->firstWhere('type', PaymentTransactionType::Capture)
            ?? $order->transactions->firstWhere('type', PaymentTransactionType::Sale);

        if (!$capturedTx && !$isCod) {
            throw ValidationException::withMessages([
                'order_id' => ['No captured payment transaction found to refund against.'],
            ]);
        }

        $amountCents = match ($refundType) {
            'full'          => $order->total,
            'shipping_only' => $order->shipping,
            default         => (int) round(($amount ?? 0) * 100),
        };

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Refund amount must be greater than zero.'],
            ]);
        }

        return DB::transaction(function () use (
            $order, $subOrderId, $capturedTx, $amountCents, $refundType,
            $reason, $reasonNotes, $vendorChargedBack, $adminId
        ) {
            $refund = Refund::create([
                'order_id'                 => $order->id,
                'sub_order_id'             => $subOrderId,
                'original_transaction_id'  => $capturedTx?->id,
                'refund_transaction_id'    => null,
                'amount'                   => $amountCents,
                'currency'                 => $order->currency,
                'reason'                   => $reason,
                'reason_notes'             => $reasonNotes,
                'refund_type'              => $refundType,
                'initiated_by_customer_id' => $order->customer_id,
                'approved_by_admin_id'     => $adminId,
                'vendor_charged_back'      => $vendorChargedBack,
                'status'                   => 'approved',
            ]);

            // Update payment_status on the order to reflect the refund.
            $newPayStatus = ($amountCents >= $order->total) ? 'refunded' : 'partially_refunded';
            $order->update(['payment_status' => $newPayStatus]);

            OrderStatusHistory::create([
                'order_id'            => $order->id,
                'sub_order_id'        => $subOrderId,
                'from_status'         => $order->status->value,
                'to_status'           => $order->status->value,
                'changed_by_admin_id' => $adminId,
                'reason'              => "[Refund] {$reason}" . ($reasonNotes ? " — {$reasonNotes}" : ''),
                'metadata'            => [
                    'action'       => 'refund_created',
                    'refund_id'    => $refund->id,
                    'amount' => $amountCents,
                    'refund_type'  => $refundType,
                ],
            ]);

            return $refund;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Escalate dispute
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a dispute record for an order/sub-order.
     */
    public function escalateDispute(
        Order $order,
        string $subOrderId,
        string $reason,
        string $description,
        string $adminId
    ): Dispute {
        $subOrder = SubOrder::findOrFail($subOrderId);

        return DB::transaction(function () use ($order, $subOrder, $reason, $description, $adminId) {
            $dispute = Dispute::create([
                'dispute_number'       => 'DSP-' . strtoupper(substr(str_replace('-', '', (string) \Illuminate\Support\Str::uuid()), 0, 10)),
                'order_id'             => $order->id,
                'sub_order_id'         => $subOrder->id,
                'customer_id'          => $order->customer_id,
                'vendor_id'            => $subOrder->vendor_id,
                'reason'               => $reason,
                'description'          => $description,
                'status'               => 'escalated',
                'assigned_to_admin_id' => $adminId,
            ]);

            $previousStatus = $order->status->value;
            if ($previousStatus !== 'disputed') {
                $order->update(['status' => 'disputed']);

                OrderStatusHistory::create([
                    'order_id'            => $order->id,
                    'sub_order_id'        => null,
                    'from_status'         => $previousStatus,
                    'to_status'           => 'disputed',
                    'changed_by_admin_id' => $adminId,
                    'reason'              => "[Dispute] {$reason} — {$dispute->dispute_number}",
                    'metadata'            => ['action' => 'dispute_escalated', 'dispute_id' => $dispute->id],
                ]);
            }

            return $dispute;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Flag fraud
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Set risk score to 100 and log a fraud-flag status history entry.
     */
    public function flagFraud(Order $order, string $reason, string $adminId): void
    {
        DB::transaction(function () use ($order, $reason, $adminId) {
            $order->updateQuietly(['risk_score' => 100]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'sub_order_id' => null,
                'from_status' => $order->status->value,
                'to_status' => $order->status->value,
                'changed_by_admin_id' => $adminId,
                'reason' => '[Fraud Flagged] ' . $reason,
                'metadata' => ['action' => 'fraud_flagged'],
            ]);
        });

        Notification::send(
            Admin::permission('orders.flag_fraud')->get(),
            new FraudPatternDetected($order, $reason),
        );
    }

}
