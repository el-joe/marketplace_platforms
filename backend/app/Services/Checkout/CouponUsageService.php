<?php

namespace App\Services\Checkout;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-04 task 2: the reserve -> consume|release lifecycle for
 * coupon usage. `reserve()` is the only place `coupons.times_used` is
 * incremented; `release()` is the only place it is decremented. Both lock
 * the coupon row (`SELECT ... FOR UPDATE`) so concurrent placements against
 * a coupon with a tight `usage_limit_total` cannot both succeed.
 *
 * `release()` is deliberately a standalone service method (not folded into
 * P-06's not-yet-built cancellation engine) so every existing rollback path
 * — payment decline/exception in Customer\CheckoutController, and the
 * existing customer order-cancel endpoint — can call it today, and P-06 can
 * call the same method later.
 */
class CouponUsageService
{
    /**
     * Lock the coupon row, increment times_used, and record a 'reserved'
     * coupon_usages row. Caller is expected to already be inside a
     * transaction (checkout's place-order transaction) — locking here is
     * what makes two concurrent placements against a usage_limit_total=1
     * coupon serialize instead of both succeeding.
     *
     * @param  int  $simulateDelaySeconds  Test-only hook to widen the race
     *      window between the lock being acquired and the increment being
     *      committed, so a concurrency test can prove the lock actually
     *      serializes the two transactions rather than merely happening to
     *      pass. Never set outside tests.
     */
    public function reserve(
        string $couponId,
        Customer $customer,
        Order $order,
        int $discountAmount,
        int $simulateDelaySeconds = 0,
    ): CouponUsage {
        /** @var Coupon $coupon */
        $coupon = Coupon::where('id', $couponId)->lockForUpdate()->firstOrFail();

        if ($simulateDelaySeconds > 0) {
            sleep($simulateDelaySeconds);
        }

        if ($coupon->usage_limit_total !== null && $coupon->times_used >= $coupon->usage_limit_total) {
            throw new CouponNoLongerValidException(__('common.exceptions.checkout.coupon.usage_limit_reached'));
        }

        $usage = CouponUsage::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'discount_amount' => $discountAmount,
            'used_at' => now(),
            'status' => CouponUsage::STATUS_RESERVED,
        ]);

        $coupon->increment('times_used');

        return $usage;
    }

    /**
     * Transition every reserved usage for an order to 'consumed' — called on
     * payment capture (wallet/card) or on COD delivery collection. Does not
     * touch times_used (it was already incremented at reserve()).
     */
    public function consumeForOrder(Order $order): void
    {
        CouponUsage::where('order_id', $order->id)
            ->where('status', CouponUsage::STATUS_RESERVED)
            ->update(['status' => CouponUsage::STATUS_CONSUMED]);
    }

    /**
     * Release every non-released usage for an order — called on any
     * rollback path (gateway decline/exception before capture -> still
     * 'reserved'; customer cancel/RTO after capture -> already 'consumed').
     * Locks the coupon row and decrements times_used exactly once per usage
     * row (idempotent: already-released rows are excluded by the query).
     */
    public function releaseForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $usages = CouponUsage::where('order_id', $order->id)
                ->whereIn('status', [CouponUsage::STATUS_RESERVED, CouponUsage::STATUS_CONSUMED])
                ->lockForUpdate()
                ->get();

            foreach ($usages as $usage) {
                $coupon = Coupon::where('id', $usage->coupon_id)->lockForUpdate()->first();
                $usage->update(['status' => CouponUsage::STATUS_RELEASED]);

                if ($coupon && $coupon->times_used > 0) {
                    $coupon->decrement('times_used');
                }
            }
        });
    }
}
