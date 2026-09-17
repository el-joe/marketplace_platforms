<?php

namespace App\Jobs;

use App\Models\Refund;
use App\Notifications\Customer\OrderRefundProcessed;
use App\Services\RefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * enhancement.md P-07 task 3: fixes for the pre-existing bugs:
 *  - No more unconditional wallet credit on top of the gateway refund
 *    (that was the double-refund bug — every electronic refund was paid
 *    twice). Money now goes to exactly ONE destination, resolved the same
 *    way RefundService::refund() resolves it.
 *  - COD refunds no longer try to refund through PaymentGatewayFactory
 *    (which has no 'cod' driver and always failed) — they resolve to the
 *    wallet instead.
 *  - Retries with backoff on a transient gateway connection failure
 *    (RefundService::settle() lets Illuminate\Http\Client\ConnectionException
 *    bubble up for exactly this reason) instead of permanently failing the
 *    refund on the first network hiccup.
 *  - Does not mark the refund 'completed' until the gateway actually
 *    confirms. Every gateway driver in this codebase (Stripe/Paytabs/
 *    Thawani/the fake test gateway) confirms a refund SYNCHRONOUSLY via a
 *    single HTTP call that returns success/failure immediately — none of
 *    them send an async "refund succeeded" webhook the way payment capture
 *    does. So RefundService::settle() marking 'completed' right after that
 *    synchronous call returns success is correct for how these gateways
 *    actually work; inventing a webhook-wait here would not match any
 *    real driver and would leave every refund stuck 'processing' forever.
 *
 * This job is now only needed for refunds created OUTSIDE
 * RefundService::refund() (e.g. OrderInterventionService::processRefund's
 * legacy admin-manual-refund path, dispatched from
 * Admin\TransactionController), which settles synchronously already.
 * Dispatching it for a refund RefundService already settled is a
 * harmless no-op (see the early return below).
 */
class RefundProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly Refund $refund)
    {
    }

    /** Exponential-ish backoff (seconds) between retries of a transient gateway failure. */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function handle(RefundService $refundService): void
    {
        $this->refund->loadMissing('originalTransaction', 'order.customer');
        $refund = $this->refund;
        $order = $refund->order;

        if ($refund->status->value === 'completed') {
            // Idempotent — already settled (most likely by RefundService
            // synchronously when the refund was created).
            return;
        }

        $isElectronic = $order->payment_method !== 'cod' && $order->payment_method !== 'wallet';

        // Legacy refunds created directly by OrderInterventionService (not
        // through RefundService::refund()) never had gateway_fee_deducted/
        // tax_deducted computed. Preserve the pre-P-07 deduction formula
        // for that one remaining caller so its behavior doesn't silently
        // change; RefundService::refund() itself already sets these at
        // creation time and this branch is then a no-op.
        if ($isElectronic && (int) $refund->gateway_fee_deducted === 0 && (int) $refund->tax_deducted === 0 && $refund->originalTransaction) {
            $gatewayFeeDeductedCents = (int) round(
                ((int) $refund->originalTransaction->gateway_fee) * ($refund->amount / max(1, (int) $refund->originalTransaction->amount))
            );
            $taxDeductedCents = (int) round($gatewayFeeDeductedCents * RefundService::GATEWAY_FEE_TAX_RATE);

            $refund->update([
                'gateway_fee_deducted' => $gatewayFeeDeductedCents,
                'tax_deducted' => $taxDeductedCents,
            ]);
            $refund->refresh();
        }

        $resolvedDestination = $isElectronic ? 'gateway' : 'wallet';

        // A transient Illuminate\Http\Client\ConnectionException from
        // RefundService::settle() is intentionally left to propagate here
        // — Laravel's queue worker retries this job using $tries/backoff()
        // above, rather than us manually releasing it back onto the queue.
        $refundService->settle($refund, $order, $resolvedDestination);

        $refund->refresh();

        if ($refund->status->value === 'completed' && $order->customer) {
            try {
                $order->customer->notify(new OrderRefundProcessed($refund));
            } catch (\Throwable $e) {
                // Notification failure must never roll back a completed
                // refund's settlement — log and move on, same defensive
                // pattern OrderCancellationService::notify() uses.
                Log::warning('RefundProcessingJob: notification failed.', [
                    'refund_id' => $refund->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
