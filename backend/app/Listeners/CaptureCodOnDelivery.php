<?php

namespace App\Listeners;

use App\Enums\PaymentTransactionStatus;
use App\Events\SubOrderDelivered;
use App\Models\PaymentTransaction;
use App\Models\SubOrder;
use App\Services\Checkout\CouponUsageService;
use App\Services\LedgerService;

/**
 * enhancement.md P-08 task 2: SubOrderDelivered ->
 *  - COD capture: mark the COD payment_transactions row 'succeeded' and the
 *    order 'captured' once ALL of the order's (COD) sub-orders are
 *    delivered — not just this one. This is the single place COD is
 *    captured on delivery now; the old duplicated logic in
 *    Delivery/AssignmentController and Services/Delivery/AssignmentService
 *    has been removed in favour of this listener.
 *  - return_eligible_until on this sub-order's items, read from the
 *    category's return_window_days setting (falls back to 14).
 *  - warranty activation (P-09) and marketer conversion approval (P-12) are
 *    NOT implemented here — those prompts add their own listeners to this
 *    same event later.
 */
class CaptureCodOnDelivery
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly CouponUsageService $couponUsageService,
    ) {
    }

    public function handle(SubOrderDelivered $event): void
    {
        $subOrder = $event->subOrder;
        $subOrder->loadMissing(['order', 'items.productVariant.product.category']);
        $order = $subOrder->order;

        if (! $order) {
            return;
        }

        $this->applyReturnWindow($subOrder);

        if ($order->payment_method !== 'cod') {
            return;
        }

        if ($order->payment_status?->value === 'captured') {
            return;
        }

        $allCodSubOrdersDelivered = SubOrder::where('order_id', $order->id)
            ->whereNotIn('status', ['delivered', 'completed'])
            ->doesntExist();

        if (! $allCodSubOrdersDelivered) {
            return;
        }

        $order->update(['payment_status' => 'captured']);

        PaymentTransaction::where('order_id', $order->id)
            ->where('gateway', 'cod')
            ->where('status', PaymentTransactionStatus::Pending)
            ->update(['status' => PaymentTransactionStatus::Succeeded, 'processed_at' => now()]);

        // enhancement.md P-03 task 5: ledger at capture.
        $this->ledgerService->postOrderCapture($order, (int) $order->total);
        // enhancement.md P-04 task 2: COD coupon usage is consumed on
        // delivery collection, not at placement.
        $this->couponUsageService->consumeForOrder($order);
    }

    private function applyReturnWindow(SubOrder $subOrder): void
    {
        foreach ($subOrder->items as $item) {
            if ($item->return_eligible_until) {
                continue;
            }

            $days = $item->productVariant?->product?->category?->return_window_days ?? 14;

            $item->update(['return_eligible_until' => now()->addDays($days)->toDateString()]);
        }
    }
}
