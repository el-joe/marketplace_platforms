<?php

namespace App\Services;

use App\Enums\VendorSubscriptionInvoiceStatus;
use App\Enums\VendorSubscriptionStatus;
use App\Models\Admin;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;
use App\Models\VendorSubscription;
use App\Models\VendorSubscriptionInvoice;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    // ── Read helpers ──────────────────────────────────────────────────────────

    /**
     * Return the vendor's currently active subscription (or null).
     */
    public function getActiveSubscription(string $vendorId): ?VendorSubscription
    {
        return VendorSubscription::with('plan')
            ->where('vendor_id', $vendorId)
            ->where('status', VendorSubscriptionStatus::Active)
            ->where('current_period_end', '>=', now()->toDateString())
            ->latest('started_at')
            ->first();
    }

    /**
     * Return the plan attached to the vendor's active subscription (or null).
     */
    public function getActivePlan(string $vendorId): ?SubscriptionPlan
    {
        return $this->getActiveSubscription($vendorId)?->plan;
    }

    // ── Mutation helpers ──────────────────────────────────────────────────────

    /**
     * Subscribe a vendor to a plan. Cancels any currently active subscription.
     * Creates a VendorSubscription + an open invoice.
     *
     * @throws \Throwable
     */
    public function subscribe(
        Vendor $vendor,
        SubscriptionPlan $plan,
        ?Admin $approvedBy = null
    ): VendorSubscription {
        return DB::transaction(function () use ($vendor, $plan, $approvedBy) {
            // Cancel existing active subscription
            VendorSubscription::where('vendor_id', $vendor->id)
                ->where('status', VendorSubscriptionStatus::Active)
                ->update([
                    'status' => VendorSubscriptionStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Replaced by new plan: ' . $plan->name_en,
                ]);

            $periodStart = now()->toDateString();
            $periodEnd = now()->addMonth()->subDay()->toDateString();

            $subscription = VendorSubscription::create([
                'vendor_id' => $vendor->id,
                'plan_id' => $plan->id,
                'status' => VendorSubscriptionStatus::Active,
                'started_at' => now(),
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'auto_renew' => true,
                'listings_used' => 0,
                'approved_by_admin_id' => $approvedBy?->id,
            ]);

            VendorSubscriptionInvoice::create([
                'vendor_id' => $vendor->id,
                'subscription_id' => $subscription->id,
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'status' => VendorSubscriptionInvoiceStatus::Open,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);

            return $subscription->load('plan');
        });
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(VendorSubscription $subscription, ?string $reason = null): VendorSubscription
    {
        $subscription->update([
            'status' => VendorSubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'auto_renew' => false,
        ]);

        return $subscription->fresh();
    }

    /**
     * Renew a subscription for another billing cycle (called by scheduler).
     * Creates the new period and a new open invoice.
     *
     * @throws \Throwable
     */
    public function renew(VendorSubscription $subscription): VendorSubscription
    {
        return DB::transaction(function () use ($subscription) {
            $newStart = $subscription->current_period_end->addDay();
            $newEnd = $newStart->copy()->addMonth()->subDay();

            $subscription->update([
                'current_period_start' => $newStart->toDateString(),
                'current_period_end' => $newEnd->toDateString(),
                'listings_used' => 0,
            ]);

            VendorSubscriptionInvoice::create([
                'vendor_id' => $subscription->vendor_id,
                'subscription_id' => $subscription->id,
                'amount' => $subscription->plan->price,
                'currency' => $subscription->plan->currency,
                'status' => VendorSubscriptionInvoiceStatus::Open,
                'period_start' => $newStart->toDateString(),
                'period_end' => $newEnd->toDateString(),
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Mark an invoice as paid.
     */
    public function markInvoicePaid(
        VendorSubscriptionInvoice $invoice,
        ?string $paymentTransactionId = null
    ): VendorSubscriptionInvoice {
        $invoice->update([
            'status' => VendorSubscriptionInvoiceStatus::Paid,
            'paid_at' => now(),
            'payment_transaction_id' => $paymentTransactionId,
        ]);

        return $invoice->fresh();
    }

    // ── Commission calculation ────────────────────────────────────────────────

    /**
     * Apply the vendor's subscription commission discount to a base rate.
     *
     * Usage in CheckoutService when creating order_items:
     *   $effectiveRate = app(SubscriptionService::class)
     *       ->applyCommissionDiscount($baseCommissionPct, $vendor->id);
     *
     * Formula: effective = base * (1 - discount_pct / 100)
     *
     * @param  float  $baseCommissionPct  e.g. 10.0 for 10 %
     * @param  string $vendorId
     * @return float  Effective commission rate (same unit as input)
     */
    public function applyCommissionDiscount(float $baseCommissionPct, string $vendorId): float
    {
        $plan = $this->getActivePlan($vendorId);

        if (!$plan || $plan->commission_discount_pct <= 0) {
            return $baseCommissionPct;
        }

        return round($baseCommissionPct * (1 - $plan->commission_discount_pct / 100), 4);
    }

    /**
     * Check whether the vendor's current plan includes free shipping.
     *
     * Usage in CheckoutService when creating sub_orders:
     *   if (app(SubscriptionService::class)->hasFreeShipping($vendorId)) {
     *       $shippingCents = 0;   // FBP orders only
     *   }
     */
    public function hasFreeShipping(string $vendorId): bool
    {
        $plan = $this->getActivePlan($vendorId);

        return $plan?->free_shipping_included === true;
    }

    // ── Listing counter ───────────────────────────────────────────────────────

    /**
     * Increment the listings_used counter on the active subscription.
     * Safe to call on vendors without a subscription.
     */
    public function incrementListingsUsed(string $vendorId, int $by = 1): void
    {
        VendorSubscription::where('vendor_id', $vendorId)
            ->where('status', VendorSubscriptionStatus::Active)
            ->where('current_period_end', '>=', now()->toDateString())
            ->increment('listings_used', $by);
    }

    /**
     * Returns true if the vendor can still add listings under their plan.
     * Vendors without a subscription are considered to have no limit here
     * (platform-level limits are enforced elsewhere).
     */
    public function canAddListing(string $vendorId): bool
    {
        $sub = $this->getActiveSubscription($vendorId);

        if (!$sub) {
            return true; // no subscription = no plan-based restriction
        }

        if (is_null($sub->plan->max_listings)) {
            return true; // unlimited
        }

        return $sub->listings_used < $sub->plan->max_listings;
    }
}
