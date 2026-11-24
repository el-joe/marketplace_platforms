<?php

namespace App\Services;

use App\DTOs\Refund\RefundScope;
use App\Enums\CancelActor;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Exceptions\OrderNotCancellableException;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\DeliveryAssignment;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\SubOrder;
use App\Models\WarrantyPurchase;
use App\Notifications\Admin\OrderCancelledByCustomer as AdminOrderCancelledByCustomer;
use App\Notifications\Customer\OrderCancelled as CustomerOrderCancelled;
use App\Notifications\Customer\OrderRefundProcessed;
use App\Notifications\Vendor\OrderCancelledByAdmin;
use App\Notifications\Vendor\SubOrderCancelledByCustomer;
use App\Services\Checkout\CouponUsageService;
use App\Services\Customer\CheckoutWalletService;
use App\Services\Customer\LoyaltyService;
use App\Services\Inventory\InventoryService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * enhancement.md P-06: the single cancellation engine for a
 * POST-CAPTURE (already placed, possibly already paid) order. Every
 * existing cancel path (customer API, vendor reject, admin intervention,
 * RTO, fraud) must delegate here instead of reimplementing stock
 * release / refund / coupon / loyalty / warranty / marketer / ledger
 * reversal on its own.
 *
 * This is deliberately NOT the same thing as P-05's CheckoutRollbackService,
 * which undoes a checkout attempt that never successfully placed/captured
 * (gateway decline before capture, expiry of a pending redirect, etc).
 * OrderCancellationService instead reverses money and stock that were
 * already committed at place-order/capture time.
 *
 * ── Cancellable-status boundary (documented decision) ──────────────────────
 *  - Customer / Vendor actors: every targeted sub-order must be strictly
 *    before 'shipped' (i.e. in placed/confirmed/processing/packed). Once a
 *    parcel has left the warehouse, self-service cancellation is refused —
 *    the customer must use the return flow instead.
 *  - Admin actor: same boundary by default. Passing $force = true allows
 *    cancelling up to and including 'delivered' (covers "customer changed
 *    their mind after delivery" cases handled manually by support). Admin
 *    can never cancel a sub-order that is already 'completed', 'cancelled',
 *    'refunded' — nothing left to reverse.
 *  - System actor (RTO / fraud): always allowed on any non-terminal status,
 *    since these paths are triggered by the lifecycle itself, never by a
 *    human request for an exception.
 *
 * ── Partial-scope coupon rule (documented decision) ─────────────────────────
 * Coupon usage (`coupons.times_used` / `coupon_usages`) is only released via
 * CouponUsageService::releaseForOrder() when the WHOLE order ends up
 * cancelled (every sub-order cancelled). A partial cancellation (one
 * sub-order or a subset of items) leaves the coupon usage exactly as it
 * was — the customer already received and keeps the discount on the part
 * of the order that is NOT cancelled, and re-litigating whether the
 * discounted remainder still meets the coupon's minimum-order rule after
 * the fact would let customers game min-order coupons by placing a large
 * order and then partially cancelling it back below the threshold. This is
 * the simpler, defensible rule: coupon eligibility is decided once, at
 * place-order time, and not re-evaluated afterwards.
 *
 * ── Partial-scope ledger rule (documented decision) ─────────────────────────
 * LedgerService::reverseOrderCapture() mirrors the ENTIRE order_capture
 * group and is only invoked here when the cancellation scope covers the
 * whole order (every sub-order ends up cancelled). For a partial-scope
 * cancellation (one sub-order / one item) the capture-time ledger entries
 * are left as posted — a scoped/partial ledger reversal is out of scope
 * for this pass (P-11 owns the ledger/payout reconciliation phase) and is
 * not required by this prompt's acceptance criteria, which only requires
 * the ledger to net to 0 for a FULL cancellation.
 */
class OrderCancellationService
{
    public function __construct(
        private readonly CouponUsageService $couponUsageService = new CouponUsageService,
        private readonly CheckoutWalletService $checkoutWalletService = new CheckoutWalletService,
        private readonly LoyaltyService $loyaltyService = new LoyaltyService,
        private readonly LedgerService $ledgerService = new LedgerService,
        private readonly PaymentService $paymentService = new PaymentService,
        private readonly RefundService $refundService = new RefundService,
        private readonly MarketerConversionReversalService $marketerConversionReversalService = new MarketerConversionReversalService,
    ) {}

    /** Sub-order statuses that are final: nothing is left to cancel or reverse. */
    private const TERMINAL_SUB_ORDER_STATUSES = ['cancelled', 'refunded', 'completed', 'returned'];

    /**
     * Cancel an order, one sub-order or a set of items. Safe to call repeatedly: items already cancelled
     * are skipped, so stock, refunds, COD and delivery side effects happen exactly once.
     *
     * Cascade rules: an item scope that leaves a sub-order with no live item cancels that sub-order; a
     * cancellation that leaves no live sub-order cancels the master order. A sub-order left open with
     * every item already cancelled (state written before this cascade existed) is closed as well.
     *
     * @param  Order|SubOrder|Collection<int, OrderItem>|array<int, OrderItem>  $scope
     *
     * @throws OrderNotCancellableException when a targeted sub-order is past the actor's cancellation boundary
     */
    public function cancel(
        Order|SubOrder|Collection|array $scope,
        CancelActor $actor,
        string $reason,
        bool $force = false,
        ?string $changedByAdminId = null,
    ): Order {
        [$order, $targetItems] = $this->resolveScope($scope);

        $outcome = DB::transaction(function () use ($order, $targetItems, $actor, $reason, $force, $changedByAdminId) {
            $order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $order->load('subOrders.items', 'customer');
            $statusBefore = $order->status?->value;

            // Only items not already cancelled — makes the whole call idempotent.
            $itemIds = $targetItems->pluck('id')->all();
            $scopeSubOrderIds = $targetItems->pluck('sub_order_id')->unique()->values();
            $items = $order->items()->whereIn('id', $itemIds)
                ->where('fulfillment_status', '!=', 'cancelled')
                ->get();

            if ($items->isEmpty()) {
                // Nothing left to reverse; only close sub-orders/order whose items were all cancelled
                // without the status cascading (e.g. by the pre-WS2 admin "cancel selected items").
                $closedSubOrders = $this->closeFullyCancelledSubOrders($order, $scopeSubOrderIds, $reason, $actor, $changedByAdminId);

                if ($closedSubOrders->isEmpty()) {
                    return null;
                }

                $isFullOrderCancel = $order->subOrders()->whereNotIn('status', ['cancelled', 'refunded'])->doesntExist();
                $this->settlePendingCodTransactions($order, 0, $isFullOrderCancel);
                $this->cancelOpenDeliveryAssignments($closedSubOrders, $actor);

                if ($isFullOrderCancel) {
                    $this->couponUsageService->releaseForOrder($order);
                    $this->reverseLedgerIfCaptured($order, $reason);
                }

                $this->finaliseOrderStatus($order, $statusBefore, $isFullOrderCancel, $reason, $actor, $itemIds, $changedByAdminId);

                return ['order' => $order, 'items' => collect(), 'closed' => $closedSubOrders, 'refund' => null];
            }

            $this->assertCancellable($order, $items, $actor, $force);

            $this->releaseStock($order, $items, $reason, $actor, $changedByAdminId);
            $this->cancelWarrantyPurchases($items);
            $this->voidMarketerConversions($items);

            $cancelledAmount = Money::number(round((float) $items->sum('line_total'), 4));
            $orderTotal = max(0.01, (float) $order->total);
            $isFullOrderCancel = $order->items()->where('fulfillment_status', '!=', 'cancelled')->count() === $items->count();

            OrderItem::whereIn('id', $items->pluck('id'))->update(['fulfillment_status' => 'cancelled']);
            $closedSubOrders = $this->closeFullyCancelledSubOrders($order, $items->pluck('sub_order_id')->unique(), $reason, $actor, $changedByAdminId);

            $refund = $this->refundTenders($order, $cancelledAmount, $orderTotal, $reason, $actor, $isFullOrderCancel);
            $this->restoreLoyalty($order, $cancelledAmount, $orderTotal);
            $this->settlePendingCodTransactions($order, $cancelledAmount, $isFullOrderCancel);
            $this->cancelOpenDeliveryAssignments($closedSubOrders, $actor);

            if ($isFullOrderCancel) {
                $this->couponUsageService->releaseForOrder($order);
                $this->reverseLedgerIfCaptured($order, $reason);
            }

            $this->finaliseOrderStatus($order, $statusBefore, $isFullOrderCancel, $reason, $actor, $itemIds, $changedByAdminId);

            return ['order' => $order, 'items' => $items, 'closed' => $closedSubOrders, 'refund' => $refund];
        });

        if ($outcome !== null) {
            $this->notify($outcome['order'], $outcome['items'], $outcome['closed'], $outcome['refund'], $reason, $actor);
        }

        return $order->fresh(['subOrders.items', 'statusHistories']);
    }

    /**
     * Cancel one whole sub-order on behalf of a person (vendor or admin) and guarantee the request had an
     * effect: an already-cancelled sub-order, or one past the actor's boundary, is refused with a localized
     * message instead of reporting success for a no-op.
     *
     * @throws OrderNotCancellableException
     */
    public function cancelSubOrder(SubOrder $subOrder, CancelActor $actor, string $reason, bool $force = false, ?string $changedByAdminId = null): SubOrder
    {
        $status = $subOrder->status instanceof \BackedEnum ? $subOrder->status->value : $subOrder->status;

        if (in_array($status, ['cancelled', 'refunded'], true)) {
            throw OrderNotCancellableException::alreadyCancelled();
        }

        $this->cancel($subOrder, $actor, $reason, $force, $changedByAdminId);

        $subOrder->refresh();

        if (($subOrder->status instanceof \BackedEnum ? $subOrder->status->value : $subOrder->status) !== 'cancelled') {
            throw OrderNotCancellableException::noEffect();
        }

        return $subOrder;
    }

    /**
     * Admin "cancel the whole order": every sub-order still open is cancelled (and with it its items,
     * stock, COD collection, delivery and refunds). Without $force, a sub-order already shipped refuses
     * the whole request before anything is changed.
     *
     * @throws OrderNotCancellableException
     */
    public function cancelOrder(Order $order, CancelActor $actor, string $reason, bool $force = false, ?string $changedByAdminId = null): Order
    {
        return DB::transaction(function () use ($order, $actor, $reason, $force, $changedByAdminId) {
            $order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $openSubOrders = $order->subOrders()->with('items')->get()
                ->reject(fn (SubOrder $subOrder) => in_array($subOrder->status->value, ['cancelled', 'refunded'], true));

            if ($openSubOrders->isEmpty() && $order->status?->value === 'cancelled') {
                throw OrderNotCancellableException::alreadyCancelled();
            }

            foreach ($openSubOrders as $subOrder) {
                $this->cancel($subOrder, $actor, $reason, $force, $changedByAdminId);
            }

            $order->refresh();
            if ($order->status?->value !== 'cancelled'
                && $order->subOrders()->whereNotIn('status', ['cancelled', 'refunded'])->doesntExist()) {
                $order->update(['status' => 'cancelled', 'cancelled_at' => $order->cancelled_at ?? now()]);
            }

            return $order->fresh(['subOrders.items', 'statusHistories']);
        });
    }

    /**
     * @return array{0: Order, 1: Collection<int, OrderItem>}
     */
    private function resolveScope(Order|SubOrder|Collection|array $scope): array
    {
        if ($scope instanceof Order) {
            $scope->loadMissing('subOrders.items');
            $items = $scope->subOrders->flatMap(fn (SubOrder $so) => $so->items);

            return [$scope, $items];
        }

        if ($scope instanceof SubOrder) {
            $scope->loadMissing('items', 'order');

            return [$scope->order, $scope->items];
        }

        $items = $scope instanceof Collection ? $scope : collect($scope);
        /** @var OrderItem $first */
        $first = $items->first();
        if (! $first) {
            throw new \InvalidArgumentException('Cannot cancel an empty item scope.');
        }
        $first->loadMissing('order');

        return [$first->order, $items];
    }

    private function assertCancellable(Order $order, Collection $items, CancelActor $actor, bool $force): void
    {
        if ($actor === CancelActor::System) {
            return;
        }

        $blockStatuses = ($actor === CancelActor::Admin && $force)
            ? ['delivered_and_later' => ['completed', 'cancelled', 'refunded']]
            : ['default' => SubOrder::BLOCK_CANCEL_STATUSES];

        $subOrderIds = $items->pluck('sub_order_id')->unique();
        $subOrders = $order->subOrders->whereIn('id', $subOrderIds);

        foreach ($subOrders as $subOrder) {
            $status = $subOrder->status instanceof \BackedEnum ? $subOrder->status->value : $subOrder->status;

            $blocked = ($actor === CancelActor::Admin && $force)
                ? in_array($status, ['completed', 'cancelled', 'refunded'], true)
                : in_array($status, SubOrder::BLOCK_CANCEL_STATUSES, true);

            if ($blocked) {
                throw OrderNotCancellableException::pastCancellationBoundary($subOrder->sub_order_number, $status);
            }
        }
    }

    /**
     * enhancement.md P-13: consolidated onto InventoryService, operating
     * on the exact order_item_allocations rows (never "first row of the
     * listing" / "the sub-order's warehouse_id" — the sub-order's items
     * can now be reserved across more than one warehouse row).
     */
    private function releaseStock(Order $order, Collection $items, string $reason, CancelActor $actor, ?string $changedByAdminId): void
    {
        $items->loadMissing('allocations');
        $inventoryService = app(InventoryService::class);
        $actorType = $actor->value;
        $actorId = $changedByAdminId ?? ($actor === CancelActor::Customer ? $order->customer_id : null);

        foreach ($items as $item) {
            $subOrder = $order->subOrders->firstWhere('id', $item->sub_order_id);
            $status = $subOrder?->status instanceof \BackedEnum ? $subOrder->status->value : $subOrder?->status;
            // Cancellation is blocked past 'packed' for non-force actors,
            // but a force-cancel after shipment must restock on_hand, not
            // just release the reservation.
            $wasShipped = in_array($status, ['shipped', 'out_for_delivery', 'delivered'], true);

            $reservedAllocations = $item->allocations->where('status', 'reserved');
            $committedAllocations = $item->allocations->where('status', 'committed');

            if ($reservedAllocations->isNotEmpty()) {
                $inventoryService->release($reservedAllocations, 'order', $order->id, actorType: $actorType, actorId: $actorId, reason: $reason);
            }

            if ($wasShipped && $committedAllocations->isNotEmpty()) {
                foreach ($committedAllocations as $allocation) {
                    $inventoryService->restock($allocation->warehouse_inventory_id, (int) $allocation->quantity, 'order', $order->id, actorType: $actorType, actorId: $actorId, reason: $reason, allocation: $allocation);
                }
            }
        }
    }

    private function cancelWarrantyPurchases(Collection $items): void
    {
        $itemIds = $items->pluck('id')->all();

        WarrantyPurchase::whereIn('order_item_id', $itemIds)
            ->whereIn('status', ['pending', 'active'])
            ->update(['status' => 'cancelled']);
    }

    /**
     * enhancement.md P-06 task 1: "void marketer conversions for the
     * cancelled items". marketer_campaign_conversions has no explicit
     * status column — `commissioned` (bool, defaults false until the
     * payout job marks it paid) is the closest thing. Voiding here means
     * flipping `commissioned` back to false and clearing `paid_at` so a
     * conversion tied to a cancelled item is never paid out, without
     * deleting the audit row. If the conversion had already been paid
     * (commissioned = true with a paid_at in the past — a payout run
     * happened before the cancellation), we leave it alone and only log:
     * clawing back an already-disbursed marketer payout is P-11's job.
     */
    private function voidMarketerConversions(Collection $items): void
    {
        // enhancement.md P-12 task 3: reverse (not just un-flag) any
        // pending/approved conversion earned on a now-cancelled item,
        // clawing back whatever was already credited to the marketer's
        // wallet. A conversion already 'paid' (in a settled payout) is left
        // alone — see MarketerConversionReversalService's class doc.
        $this->marketerConversionReversalService->reverseForOrderItemIds($items->pluck('id')->all());
    }

    /**
     * Cancel every sub-order (among $subOrderIds) that has no live item left. Returns the sub-orders closed now.
     *
     * @param  Collection<int, string>  $subOrderIds
     * @return Collection<int, SubOrder>
     */
    private function closeFullyCancelledSubOrders(Order $order, Collection $subOrderIds, string $reason, CancelActor $actor, ?string $changedByAdminId): Collection
    {
        $closed = collect();

        foreach ($subOrderIds as $subOrderId) {
            $subOrder = SubOrder::where('id', $subOrderId)->where('order_id', $order->id)->lockForUpdate()->first();
            if (! $subOrder) {
                continue;
            }

            $previousStatus = $subOrder->status instanceof \BackedEnum ? $subOrder->status->value : $subOrder->status;
            if (in_array($previousStatus, self::TERMINAL_SUB_ORDER_STATUSES, true)) {
                continue;
            }

            if ($subOrder->items()->where('fulfillment_status', '!=', 'cancelled')->exists()) {
                // Partial cancellation within the sub-order — it keeps its status; only items are cancelled.
                continue;
            }

            $subOrder->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'sub_order_id' => $subOrder->id,
                'from_status' => $previousStatus,
                'to_status' => 'cancelled',
                'changed_by_admin_id' => $changedByAdminId,
                'reason' => "[{$actor->value}] {$reason}",
            ]);

            $closed->push($subOrder);
        }

        return $closed;
    }

    /**
     * Roll the master order up from its sub-orders and record the cancellation in its history.
     *
     * @param  array<int, string>  $itemIds
     */
    private function finaliseOrderStatus(Order $order, ?string $statusBefore, bool $isFullOrderCancel, string $reason, CancelActor $actor, array $itemIds, ?string $changedByAdminId): void
    {
        $order->unsetRelation('subOrders');
        $order->syncStatusFromSubOrders($changedByAdminId);
        $order->refresh();

        if ($isFullOrderCancel && $order->status?->value !== 'cancelled') {
            // Every item is cancelled but the roll-up left the order elsewhere (e.g. a refunded sub-order).
            $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'sub_order_id' => null,
            'from_status' => $statusBefore,
            'to_status' => $order->status?->value,
            'changed_by_admin_id' => $changedByAdminId,
            'reason' => "[{$actor->value}] {$reason}",
            'metadata' => [
                'action' => 'order_cancellation',
                'actor' => $actor->value,
                'item_ids' => $itemIds,
                'full_order' => $isFullOrderCancel,
            ],
        ]);
    }

    /**
     * COD money is only collected on delivery, so cancelling never refunds it: the still-pending COD
     * transaction is cancelled for a full cancel, or reduced by the cancelled value for a partial one.
     */
    private function settlePendingCodTransactions(Order $order, int|float $cancelledAmount, bool $isFullOrderCancel): void
    {
        if ($order->payment_method !== 'cod') {
            return;
        }

        $pending = PaymentTransaction::where('order_id', $order->id)
            ->where('gateway', 'cod')
            ->where('status', PaymentTransactionStatus::Pending)
            ->lockForUpdate()
            ->get();

        foreach ($pending as $transaction) {
            if ($isFullOrderCancel) {
                $transaction->update([
                    'status' => PaymentTransactionStatus::Cancelled,
                    'processed_at' => now(),
                    'note' => trim(($transaction->note ?? '')."\nCancelled with the order; nothing to collect."),
                ]);

                continue;
            }

            if ($cancelledAmount <= 0) {
                continue;
            }

            $remaining = Money::number(max(0, round((float) $transaction->amount - (float) $cancelledAmount, 4)));
            $transaction->update([
                'amount' => $remaining,
                'note' => trim(($transaction->note ?? '')."\nReduced by ".Money::format($cancelledAmount).' for cancelled items.'),
            ]);
        }
    }

    /**
     * Close open rider assignments of the sub-orders that were just cancelled. A system cancel comes from
     * the delivery lifecycle itself (RTO after a refused COD delivery) whose return-to-origin assignment
     * must stay open so the parcel travels back, so it is left alone.
     *
     * @param  Collection<int, SubOrder>  $subOrders
     */
    private function cancelOpenDeliveryAssignments(Collection $subOrders, CancelActor $actor): void
    {
        if ($subOrders->isEmpty() || $actor === CancelActor::System) {
            return;
        }

        DeliveryAssignment::whereIn('sub_order_id', $subOrders->pluck('id'))
            ->whereIn('status', DeliveryAssignment::OPEN_STATUSES)
            ->update([
                'status' => DeliveryAssignment::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Refund per tender, in reverse order of use: gift card -> wallet -> collected payment.
     * Pro-rated for a partial scope by the cancelled-items' share of the order total. Only money
     * that was actually taken is given back: an unpaid COD order or an authorised-but-uncaptured
     * card refunds nothing (and so gets no refund record and no refund notification).
     *
     * @return Refund|null the refund record created for the collected-payment share, if any
     */
    private function refundTenders(Order $order, int|float $cancelledAmount, int|float $orderTotal, string $reason, CancelActor $actor, bool $isFullOrderCancel): ?Refund
    {
        $fraction = $cancelledAmount / $orderTotal;

        // Gift card: enhancement.md P-05 flagged that gift-card debit is not
        // actually implemented anywhere in checkout (no
        // CheckoutGiftCardService / no gift_card_transactions row written
        // at place-order). There is nothing to re-credit here, so this step
        // is intentionally a no-op — inventing a gift-card refund path
        // would refund money that was never actually taken.

        $walletUsed = (float) $order->wallet_amount_used;
        $walletPortion = $walletUsed > 0 ? Money::number(round($walletUsed * $fraction, 4)) : 0;

        $initiatedBy = ['type' => $actor->value, 'id' => $actor === CancelActor::Customer ? $order->customer_id : null];
        $paymentStatusBefore = $order->payment_status;
        $walletRefund = null;

        if ($walletPortion > 0) {
            // R2: recorded as a refund (refunds row + refund transaction + payment_status), not a silent re-credit.
            $walletRefund = $this->refundService->refundWalletTender($order, $walletPortion, null, $reason, $initiatedBy, $isFullOrderCancel);
            $order->refresh();
        }

        $collectedPortion = Money::number(round($cancelledAmount - $walletPortion, 4));

        if ($collectedPortion <= 0) {
            return $walletRefund;
        }

        if (! in_array($paymentStatusBefore, [OrderPaymentStatus::Captured, OrderPaymentStatus::PartiallyRefunded], true)) {
            // Unpaid COD (cash is only collected on delivery) or a card that
            // was never captured: nothing was charged, so nothing is refunded.
            return $walletRefund;
        }

        // enhancement.md P-07 task 5: route through the single RefundService.
        // A card order refunds through the gateway; a COD order already
        // collected on delivery refunds to the wallet ('original' resolves
        // to the wallet when there is no gateway transaction). A whole-order
        // cancel posts its own full reverseOrderCapture() afterwards, so the
        // refund must not also reverse the ledger; a partial cancel lets the
        // refund post its proportional reversal.
        try {
            $refund = $this->refundService->refund(
                order: $order,
                scope: RefundScope::amount($collectedPortion),
                reason: 'customer_request',
                liability: 'customer',
                destination: 'original',
                initiatedBy: $initiatedBy,
                reasonNotes: $reason,
                reverseLedger: ! $isFullOrderCancel,
            );

            if ($isFullOrderCancel && $refund->status?->value === 'completed') {
                $order->update(['payment_status' => 'refunded']);
            }

            return $refund;
        } catch (\Throwable $e) {
            Log::error('OrderCancellationService: refund via RefundService failed.', [
                'order_id' => $order->id,
                'exception' => $e->getMessage(),
            ]);

            return $walletRefund;
        }
    }

    private function restoreLoyalty(Order $order, int|float $cancelledAmount, int|float $orderTotal): void
    {
        $pointsUsed = (float) $order->loyalty_points_used;
        if ($pointsUsed <= 0) {
            return;
        }

        $fraction = $cancelledAmount / $orderTotal;
        $pointsToRestore = round($pointsUsed * $fraction, 2);

        if ($pointsToRestore <= 0) {
            return;
        }

        try {
            // Pro-rata restore — LoyaltyService::creditPointsBackForOrder()
            // always restores the FULL loyalty_points_used, which is only
            // correct for a whole-order cancellation. For a partial scope we
            // credit the pro-rated share directly here instead.
            Customer::where('id', $order->customer_id)
                ->lockForUpdate()
                ->first()
                ?->increment('loyalty_points', $pointsToRestore);
        } catch (\Throwable $e) {
            Log::error('OrderCancellationService: failed to restore loyalty points.', [
                'order_id' => $order->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function reverseLedgerIfCaptured(Order $order, string $reason): void
    {
        $hasCapture = LedgerEntry::where('transaction_group_id', $order->id)
            ->where('reference_type', 'order_capture')
            ->exists();

        if (! $hasCapture) {
            return;
        }

        // Idempotency: don't reverse twice if cancel() is somehow re-entered
        // for an order whose capture was already reversed.
        $alreadyReversed = LedgerEntry::where('reference_type', 'order_capture_reversal')
            ->where('reference_id', (string) $order->id)
            ->exists();

        if ($alreadyReversed) {
            return;
        }

        $this->ledgerService->reverseOrderCapture($order, $reason);
    }

    /**
     * Customer: one "cancelled" notice per touched sub-order (no money promise), plus a refund notice
     * only when a refund record was created and settled. Vendor: told unless the vendor cancelled it.
     * Sent after commit so a rolled-back cancellation never notifies anyone.
     *
     * @param  Collection<int, OrderItem>  $items
     * @param  Collection<int, SubOrder>  $closedSubOrders
     */
    private function notify(Order $order, Collection $items, Collection $closedSubOrders, ?Refund $refund, string $reason, CancelActor $actor): void
    {
        DB::afterCommit(function () use ($order, $items, $closedSubOrders, $refund, $reason, $actor) {
            try {
                $order->refresh()->load('customer', 'subOrders.vendor.vendorAdmins');
                $closedIds = $closedSubOrders->pluck('id');
                $touchedIds = $items->pluck('sub_order_id')->merge($closedIds)->unique();

                foreach ($touchedIds as $subOrderId) {
                    $subOrder = $order->subOrders->firstWhere('id', $subOrderId);
                    if (! $subOrder) {
                        continue;
                    }

                    $isWholeSubOrder = $subOrder->status?->value === 'cancelled';
                    $order->customer?->notify(new CustomerOrderCancelled(
                        $subOrder,
                        $reason,
                        $isWholeSubOrder ? 0 : $items->where('sub_order_id', $subOrderId)->count(),
                    ));

                    if ($actor !== CancelActor::Vendor && $subOrder->vendor) {
                        $vendorNotification = $actor === CancelActor::Customer
                            ? new SubOrderCancelledByCustomer($subOrder, $reason)
                            : new OrderCancelledByAdmin($subOrder, $reason);

                        Notification::send($subOrder->vendor->vendorAdmins, $vendorNotification);
                    }
                }

                if ($refund && $refund->status?->value === 'completed' && $order->customer) {
                    $order->customer->notify(new OrderRefundProcessed($refund));
                }

                if ($actor === CancelActor::Customer) {
                    Notification::send(Admin::permission('orders.view')->get(), new AdminOrderCancelledByCustomer($order, $reason));
                }
            } catch (\Throwable $e) {
                Log::warning('OrderCancellationService: notification failed.', [
                    'order_id' => $order->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        });
    }
}
