<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use App\Models\SubOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Loyalty Points Service
 *
 * Points are DECIMAL (not BIGINT monetary). They are earned as a floating-point
 * rate on the sub-order subtotal and converted to a base-currency discount at
 * redemption using FLOOR() for the discount amount calculation.
 *
 * All point mutations use lockForUpdate() inside DB::transaction() to prevent
 * race conditions when concurrent requests modify the same customer's balance.
 */
class LoyaltyService
{
    // ── Earn ──────────────────────────────────────────────────────────────────

    /**
     * Award loyalty points for a delivered sub-order.
     *
     * Called from SubOrderObserver when status transitions to 'delivered'.
     * Uses the sub-order subtotal (not total) — shipping, tax, and discounts
     * do NOT earn points. Only items earn points.
     *
     * Points = FLOOR(subtotal * earn_rate)
     * Stored as DECIMAL(10,2) on customers.loyalty_points.
     */
    public function earnForSubOrder(SubOrder $subOrder): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $earnRate   = (float) Setting::get('loyalty_earn_rate', 0.01);
        $subtotal   = (int) $subOrder->subtotal; // BIGINT base-currency units
        $pointsEarned = floor($subtotal * $earnRate * 100) / 100; // DECIMAL(10,2)

        if ($pointsEarned <= 0) {
            return;
        }

        DB::transaction(function () use ($subOrder, $pointsEarned): void {
            // Lock the customer row to prevent concurrent point mutations.
            $customer = Customer::where('id', $subOrder->order->customer_id)
                ->lockForUpdate()
                ->first();

            if (! $customer) {
                return;
            }

            $customer->increment('loyalty_points', $pointsEarned);

            // Accumulate on the parent order for reporting.
            $subOrder->order->increment('loyalty_points_earned', $pointsEarned);
        });
    }

    // ── Redeem ────────────────────────────────────────────────────────────────

    /**
     * Calculate the base-currency discount amount for a given redemption request.
     *
     * Returns the discount in base-currency BIGINT units (FLOOR applied).
     * Does NOT mutate any balances — call this at checkout preview time.
     *
     * @throws ValidationException if points are below minimum or customer has insufficient balance.
     */
    public function calculateRedemptionDiscount(Customer $customer, float $pointsToUse, int $orderTotal): int
    {
        if (! $this->isEnabled()) {
            throw ValidationException::withMessages([
                'loyalty_points_to_use' => ['Loyalty redemption is currently disabled.'],
            ]);
        }

        $minRedeem  = (int) Setting::get('loyalty_min_redeem', 100);
        $redeemRate = (float) Setting::get('loyalty_redeem_rate', 1.0);

        if ($pointsToUse < $minRedeem) {
            throw ValidationException::withMessages([
                'loyalty_points_to_use' => ["Minimum redemption is {$minRedeem} points."],
            ]);
        }

        if ($customer->loyalty_points < $pointsToUse) {
            throw ValidationException::withMessages([
                'loyalty_points_to_use' => ['Insufficient loyalty points balance.'],
            ]);
        }

        // FLOOR for the monetary conversion — never round up discounts.
        $discountAmount = (int) floor($pointsToUse * $redeemRate);

        // Redemption cannot exceed the order total.
        return min($discountAmount, $orderTotal);
    }

    /**
     * Debit loyalty points from the customer and record on the order.
     *
     * Must be called inside an existing DB::transaction() — it uses
     * lockForUpdate() to guard against concurrent mutations.
     *
     * @param  float  $pointsToUse  Points to deduct (as provided by customer)
     * @param  int    $discountAmount  Pre-calculated discount (from calculateRedemptionDiscount)
     */
    public function debitPointsForOrder(Customer $customer, Order $order, float $pointsToUse, int $discountAmount): void
    {
        // Re-lock and re-check inside the transaction for safety.
        $lockedCustomer = Customer::where('id', $customer->id)->lockForUpdate()->first();

        if (! $lockedCustomer || $lockedCustomer->loyalty_points < $pointsToUse) {
            throw new \DomainException('Insufficient loyalty points at time of order placement.');
        }

        $lockedCustomer->decrement('loyalty_points', $pointsToUse);

        $order->update([
            'loyalty_discount'     => $discountAmount,
            'loyalty_points_used'  => $pointsToUse,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isEnabled(): bool
    {
        return (bool) Setting::get('loyalty_enabled', true);
    }

    /**
     * Customer-facing loyalty info for checkout preview.
     */
    public function previewInfo(Customer $customer, string $currency): array
    {
        $redeemRate = (float) Setting::get('loyalty_redeem_rate', 1.0);
        $minRedeem  = (int)   Setting::get('loyalty_min_redeem', 100);
        $balance    = (float) $customer->loyalty_points;
        $enabled    = $this->isEnabled();

        return [
            'enabled'          => $enabled,
            'balance'          => $balance,
            'min_redeem'       => $minRedeem,
            'redeem_rate'      => $redeemRate,
            'max_discount'     => $enabled ? (int) floor($balance * $redeemRate) : 0,
            'currency'         => $currency,
            'applicable'       => $enabled && $balance >= $minRedeem,
        ];
    }
}
