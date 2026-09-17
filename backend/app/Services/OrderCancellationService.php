<?php

namespace App\Services;

use App\DTOs\Refund\RefundScope;
use App\Enums\CancelActor;
use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\LedgerEntry;
use App\Models\MarketerCampaignConversion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\PaymentTransaction;
use App\Models\SubOrder;
use App\Models\WarehouseInventory;
use App\Models\WarrantyPurchase;
use App\Notifications\Customer\OrderCancelled as CustomerOrderCancelled;
use App\Notifications\Vendor\OrderCancelledByAdmin;
use App\Services\Checkout\CouponUsageService;
use App\Services\Customer\CheckoutWalletService;
use App\Services\Customer\LoyaltyService;
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
        private readonly CouponUsageService $couponUsageService = new CouponUsageService(),
        private readonly CheckoutWalletService $checkoutWalletService = new CheckoutWalletService(),
        private readonly LoyaltyService $loyaltyService = new LoyaltyService(),
        private readonly LedgerService $ledgerService = new LedgerService(),
        private readonly PaymentService $paymentService = new PaymentService(),
        private readonly RefundService $refundService = new RefundService(),
    ) {}

    /**
     * @param  Order|SubOrder|Collection<int, OrderItem>|array<int, OrderItem>  $scope
     */
    public function cancel(
        Order|SubOrder|Collection|array $scope,
        CancelActor $actor,
        string $reason,
        bool $force = false,
    ): Order {
        [$order, $targetItems] = $this->resolveScope($scope);

        return DB::transaction(function () use ($order, $targetItems, $actor, $reason, $force) {
            $order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $order->loadMissing('subOrders.items', 'customer');

            // Only items not already cancelled — makes the whole call idempotent.
            $itemIds = $targetItems->pluck('id')->all();
            $items = $order->items()->whereIn('id', $itemIds)
                ->where('fulfillment_status', '!=', 'cancelled')
                ->get();

            if ($items->isEmpty()) {
                // Nothing left to do — either already cancelled or an empty scope.
                return $order->fresh(['subOrders.items', 'statusHistories']);
            }

            $this->assertCancellable($order, $items, $actor, $force);

            $this->releaseStock($order, $items, $reason);
            $this->cancelWarrantyPurchases($items);
            $this->voidMarketerConversions($items);

            $cancelledAmount = (int) $items->sum('line_total');
            $orderTotal = max(1, (int) $order->total);
            $isFullOrderCancel = $order->items()->where('fulfillment_status', '!=', 'cancelled')->count() === $items->count();

            $this->markItemsAndSubOrdersCancelled($order, $items, $reason, $actor);

            $this->refundTenders($order, $cancelledAmount, $orderTotal, $reason, $actor);
            $this->restoreLoyalty($order, $cancelledAmount, $orderTotal);

            if ($isFullOrderCancel) {
                $this->couponUsageService->releaseForOrder($order);
                $this->reverseLedgerIfCaptured($order, $reason);
            }

            $order->syncStatusFromSubOrders();
            $order->refresh();

            if ($isFullOrderCancel && $order->status?->value !== 'cancelled') {
                // syncStatusFromSubOrders only flips to 'cancelled' when every
                // sub-order is cancelled/refunded — true here since $isFullOrderCancel.
                $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'sub_order_id' => null,
                'from_status' => $order->getOriginal('status'),
                'to_status' => $order->status?->value ?? $order->status,
                'reason' => "[{$actor->value}] {$reason}",
                'metadata' => [
                    'action' => 'order_cancellation',
                    'actor' => $actor->value,
                    'item_ids' => $itemIds,
                    'full_order' => $isFullOrderCancel,
                ],
            ]);

            $this->notify($order, $items, $reason);

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
                throw new \DomainException(
                    "Sub-order {$subOrder->sub_order_number} cannot be cancelled by {$actor->value} from status '{$status}'."
                );
            }
        }
    }

    private function releaseStock(Order $order, Collection $items, string $reason): void
    {
        $itemsBySubOrder = $items->groupBy('sub_order_id');

        foreach ($itemsBySubOrder as $subOrderId => $subOrderItems) {
            $subOrder = $order->subOrders->firstWhere('id', $subOrderId);
            if (! $subOrder || ! $subOrder->warehouse_id) {
                continue;
            }

            foreach ($subOrderItems as $item) {
                $query = $item->vendor_listing_id
                    ? WarehouseInventory::where('vendor_listing_id', $item->vendor_listing_id)
                    : ($item->admin_listing_id
                        ? WarehouseInventory::where('admin_listing_id', $item->admin_listing_id)
                        : null);

                if (! $query) {
                    continue;
                }

                // Exact reserved/committed row: the sub-order's own warehouse,
                // never "first row of the listing" (enhancement.md P-06 bug).
                $inventory = $query->where('warehouse_id', $subOrder->warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    continue;
                }

                $releaseQty = min((int) $item->quantity, (int) $inventory->quantity_reserved);
                if ($releaseQty > 0) {
                    $inventory->decrement('quantity_reserved', $releaseQty);
                }

                // If the sub-order had already shipped-equivalent on_hand
                // decrement (shouldn't happen — cancellation is blocked past
                // 'packed' for non-force actors), restock on_hand too so a
                // force-cancelled-after-shipped scope doesn't lose stock.
                $status = $subOrder->status instanceof \BackedEnum ? $subOrder->status->value : $subOrder->status;
                $restockOnHand = in_array($status, ['shipped', 'out_for_delivery', 'delivered'], true);
                if ($restockOnHand) {
                    $inventory->increment('quantity_on_hand', (int) $item->quantity);
                }

                $inventory->refresh();

                InventoryMovement::create([
                    'warehouse_inventory_id' => $inventory->id,
                    'movement_type' => InventoryMovementType::Release->value,
                    'quantity_delta' => -$releaseQty,
                    'quantity_after' => $inventory->quantity_on_hand,
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                    'reason' => $reason,
                    'created_by_user_id' => $order->customer_id,
                ]);
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
        $itemIds = $items->pluck('id')->all();

        $conversions = MarketerCampaignConversion::whereIn('order_item_id', $itemIds)->get();

        foreach ($conversions as $conversion) {
            if ($conversion->commissioned && $conversion->paid_at) {
                Log::info('OrderCancellationService: cancelled item had an already-paid marketer conversion; not clawed back.', [
                    'conversion_id' => $conversion->id,
                ]);

                continue;
            }

            $conversion->update(['commissioned' => false, 'paid_at' => null]);
        }
    }

    private function markItemsAndSubOrdersCancelled(Order $order, Collection $items, string $reason, CancelActor $actor): void
    {
        OrderItem::whereIn('id', $items->pluck('id'))->update(['fulfillment_status' => 'cancelled']);

        $subOrderIds = $items->pluck('sub_order_id')->unique();

        foreach ($subOrderIds as $subOrderId) {
            $subOrder = $order->subOrders->firstWhere('id', $subOrderId);
            if (! $subOrder) {
                continue;
            }

            $remaining = $subOrder->items()->where('fulfillment_status', '!=', 'cancelled')->count();
            if ($remaining > 0) {
                // Partial cancellation within the sub-order — leave its own
                // status alone; only the items are cancelled.
                continue;
            }

            $previousStatus = $subOrder->status instanceof \BackedEnum ? $subOrder->status->value : $subOrder->status;
            if ($previousStatus === 'cancelled') {
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
                'reason' => "[{$actor->value}] {$reason}",
            ]);
        }
    }

    /**
     * Refund per tender, in reverse order of use: gift card -> wallet -> card.
     * Pro-rated for a partial scope by the cancelled-items' share of the
     * order total.
     */
    private function refundTenders(Order $order, int $cancelledAmount, int $orderTotal, string $reason, CancelActor $actor): void
    {
        $fraction = $cancelledAmount / $orderTotal;

        // Gift card: enhancement.md P-05 flagged that gift-card debit is not
        // actually implemented anywhere in checkout (no
        // CheckoutGiftCardService / no gift_card_transactions row written
        // at place-order). There is nothing to re-credit here, so this step
        // is intentionally a no-op — inventing a gift-card refund path
        // would refund money that was never actually taken.

        $walletUsed = (int) $order->wallet_amount_used;
        $walletPortion = $walletUsed > 0 ? (int) round($walletUsed * $fraction) : 0;

        if ($walletPortion > 0) {
            $this->checkoutWalletService->refundToWallet($order->customer, $order, $walletPortion);
        }

        $cardPortion = $cancelledAmount - $walletPortion;

        if ($cardPortion <= 0) {
            return;
        }

        $isCod = $order->payment_method === 'cod';
        if ($isCod) {
            // Nothing was ever captured for the COD share — no gateway
            // refund transaction exists to reverse and nothing was
            // collected, so there's nothing to refund back.
            return;
        }

        if ($order->payment_status?->value !== 'captured' && $order->payment_status?->value !== 'partially_refunded') {
            // Card authorized but never captured — nothing was actually
            // charged, so there's no refund to issue.
            return;
        }

        $originalTransaction = PaymentTransaction::where('order_id', $order->id)
            ->whereIn('type', ['authorization', 'capture', 'sale'])
            ->where('status', 'succeeded')
            ->where('gateway', '!=', 'cod')
            ->latest('created_at')
            ->first();

        if (! $originalTransaction) {
            Log::warning('OrderCancellationService: no captured transaction found to refund against.', ['order_id' => $order->id]);

            return;
        }

        // enhancement.md P-07 task 5: route through the single RefundService
        // instead of calling PaymentService::refund() directly (P-06's
        // stopgap, kept only long enough to avoid P-07's now-fixed
        // double-refund bug in RefundProcessingJob). $reverseLedger: false
        // because this cancellation, being a full-order cancel (only path
        // that reaches here with a non-zero $cardPortion — COD/uncaptured
        // orders return earlier above), posts its own full
        // reverseOrderCapture() right after this via reverseLedgerIfCaptured().
        try {
            $this->refundService->refund(
                order: $order,
                scope: RefundScope::amount($cardPortion),
                reason: 'customer_request',
                liability: 'customer',
                destination: 'original',
                initiatedBy: ['type' => $actor->value === 'customer' ? 'customer' : $actor->value, 'id' => $actor === CancelActor::Customer ? $order->customer_id : null],
                reasonNotes: $reason,
                reverseLedger: false,
            );

            $newPayStatus = ($cardPortion + $walletPortion) >= $order->total ? 'refunded' : 'partially_refunded';
            $order->update(['payment_status' => $newPayStatus]);
        } catch (\Throwable $e) {
            Log::error('OrderCancellationService: refund via RefundService failed.', [
                'order_id' => $order->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function restoreLoyalty(Order $order, int $cancelledAmount, int $orderTotal): void
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
            \App\Models\Customer::where('id', $order->customer_id)
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

    private function notify(Order $order, Collection $items, string $reason): void
    {
        $subOrderIds = $items->pluck('sub_order_id')->unique();

        foreach ($subOrderIds as $subOrderId) {
            $subOrder = $order->subOrders->firstWhere('id', $subOrderId);
            if (! $subOrder) {
                continue;
            }

            try {
                if ($order->customer) {
                    $order->customer->notify(new CustomerOrderCancelled($subOrder, $reason));
                }

                if ($subOrder->vendor_id) {
                    $subOrder->loadMissing('vendor.vendorAdmins');
                    if ($subOrder->vendor) {
                        Notification::send($subOrder->vendor->vendorAdmins, new OrderCancelledByAdmin($subOrder, $reason));
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('OrderCancellationService: notification failed.', [
                    'order_id' => $order->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
