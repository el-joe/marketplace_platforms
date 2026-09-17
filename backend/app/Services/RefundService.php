<?php

namespace App\Services;

use App\DTOs\Refund\RefundScope;
use App\Enums\RefundReason;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\SubOrder;
use App\Services\Customer\CheckoutWalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * enhancement.md P-07 task 1: the single refund engine. Every refund path
 * in the codebase (return inspection, order cancellation's card portion,
 * admin manual refund) must go through here instead of reimplementing
 * "compute amount + pick destination + move money + reverse ledger" on
 * its own — see enhancement.md 0.1 rule 3.
 *
 * ── Amount ───────────────────────────────────────────────────────────────
 * Computed from the PERSISTED P-03 line values (order_items.line_total /
 * line_discount / line_tax, sub_orders.tax/shipping) — never recomputed
 * from scratch. See RefundScope for the three ways a caller can describe
 * what is being refunded.
 *
 * ── Deduction policy (documented decision) ──────────────────────────────
 * Keyed on $liability (App\Enums\ReturnRequestLiability values):
 *  - 'customer' (customer's own fault — changed mind, wrong size, etc.):
 *    when the destination is the gateway, the refund may deduct a
 *    pro-rated share of the ORIGINAL gateway_fee (plus VAT on that fee
 *    share, GATEWAY_FEE_TAX_RATE) — the same formula the pre-P-07
 *    RefundProcessingJob used, now applied only for customer-fault
 *    refunds. There is no return-shipping-fee column anywhere in the
 *    schema to also deduct, so that half of "may deduct return shipping
 *    or gateway fee" has nothing to wire up; documented here rather than
 *    invented.
 *  - 'seller' | 'platform' | 'carrier' (not the customer's fault): ZERO
 *    deduction, regardless of destination — the customer gets back
 *    exactly what they're owed, and vendor_charged_back records that the
 *    vendor (liability = seller) eats the cost at settlement time.
 *
 * ── Destination (documented decision, matches D6) ───────────────────────
 *  - 'original': electronic (card/gateway) orders refund back through the
 *    gateway on the original transaction. COD orders have no gateway
 *    transaction to reverse (PaymentGatewayFactory has no 'cod' driver),
 *    so 'original' on a COD order is redirected to the wallet.
 *  - 'wallet': always the customer wallet (used for store_credit returns
 *    and COD refunds).
 *  - 'bank': manual bank payout — there is no bank-payout automation in
 *    this codebase, so this destination only marks the refund
 *    'pending' with a note for an admin to action manually; it never
 *    moves money automatically. Reachable only if a caller explicitly
 *    asks for it (e.g. a COD customer with no wallet who declines a
 *    wallet credit) — CheckoutWalletService auto-creates a wallet, so
 *    in practice today every COD refund can go to the wallet.
 *  Never both gateway and wallet for the same refund.
 *
 * ── Ledger reversal for a partial refund (documented approach) ──────────
 * LedgerService::reverseOrderCapture() mirrors the ENTIRE order_capture
 * group and would over-reverse a partial refund. Instead this service
 * calls LedgerService::reversePartialCapture(), which scales every entry
 * in the original capture group by (refundAmount / originally-captured
 * amount) and posts that scaled mirror under a new group id. This is an
 * approximation (it doesn't re-derive vendor/platform shares from the
 * specific items refunded) but it keeps every capture-time account
 * (seller_payable, platform_commission, tax_payable, gateway_fee,
 * marketer_commission_payable, shipping_revenue) moving in the same
 * proportion as the money actually being given back — a fair
 * approximation for this pass. Deeper per-line reconciliation is P-11's
 * job. A refund that covers the WHOLE remaining order still lets
 * OrderCancellationService use the full reverseOrderCapture() instead.
 */
class RefundService
{
    /** VAT rate applied to the gateway-fee portion deducted from a customer-fault refund. */
    public const GATEWAY_FEE_TAX_RATE = 0.05;

    private const RETURN_REASON_MAP = [
        'changed_mind' => 'customer_request',
        'wrong_item' => 'wrong_item',
        'defective' => 'damaged',
        'damaged' => 'damaged',
        'not_as_described' => 'not_as_described',
        'size_issue' => 'customer_request',
        'quality_issue' => 'damaged',
        'arrived_late' => 'late_delivery',
        'other' => 'other',
    ];

    public function __construct(
        private readonly PaymentService $paymentService = new PaymentService(),
        private readonly CheckoutWalletService $checkoutWalletService = new CheckoutWalletService(),
        private readonly LedgerService $ledgerService = new LedgerService(),
    ) {}

    /**
     * @param  string  $reason  A RefundReason value, or a ReturnRequestReason
     *      value (mapped via self::RETURN_REASON_MAP) — the original string
     *      is always preserved in reason_notes.
     * @param  string  $liability  App\Enums\ReturnRequestLiability value:
     *      customer|seller|platform|carrier.
     * @param  string  $destination  original|wallet|bank.
     * @param  array{type: string, id: ?string}|null  $initiatedBy  e.g.
     *      ['type' => 'admin', 'id' => $admin->id] or ['type' => 'system'].
     *      Defaults to the order's customer.
     */
    public function refund(
        Order $order,
        RefundScope $scope,
        string $reason,
        string $liability,
        string $destination,
        ?array $initiatedBy = null,
        ?string $approvedByAdminId = null,
        ?string $reasonNotes = null,
        bool $reverseLedger = true,
    ): Refund {
        return DB::transaction(function () use ($order, $scope, $reason, $liability, $destination, $initiatedBy, $approvedByAdminId, $reasonNotes, $reverseLedger) {
            $order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            [$grossAmount, $subOrderId, $refundType] = $this->computeAmount($order, $scope, $liability);

            if ($grossAmount <= 0) {
                throw new \DomainException('Refund amount must be greater than zero.');
            }

            $isElectronic = $order->payment_method !== 'cod' && $order->payment_method !== 'wallet';
            $resolvedDestination = $this->resolveDestination($order, $destination, $isElectronic);

            $originalTransaction = null;
            $gatewayFeeDeducted = 0;
            $taxDeducted = 0;

            if ($resolvedDestination === 'gateway') {
                $originalTransaction = PaymentTransaction::where('order_id', $order->id)
                    ->whereIn('type', ['authorization', 'capture', 'sale'])
                    ->where('status', 'succeeded')
                    ->where('gateway', '!=', 'cod')
                    ->latest('created_at')
                    ->first();

                if (! $originalTransaction) {
                    throw new \DomainException("No captured gateway transaction found to refund order {$order->id} against.");
                }

                if ($liability === 'customer') {
                    $gatewayFeeDeducted = (int) round(
                        ((int) $originalTransaction->gateway_fee) * ($grossAmount / max(1, (int) $originalTransaction->amount))
                    );
                    $taxDeducted = (int) round($gatewayFeeDeducted * self::GATEWAY_FEE_TAX_RATE);
                }
            }

            $mappedReason = $this->mapReason($reason);
            $notes = $reasonNotes ?? "Original reason: {$reason}";

            $refund = Refund::create([
                'order_id' => $order->id,
                'sub_order_id' => $subOrderId,
                'original_transaction_id' => $originalTransaction?->id,
                'amount' => $grossAmount,
                'currency' => $order->currency,
                'reason' => $mappedReason,
                'reason_notes' => $notes,
                'refund_type' => $refundType,
                'initiated_by_customer_id' => ($initiatedBy === null || $initiatedBy['type'] === 'customer')
                    ? ($initiatedBy['id'] ?? $order->customer_id)
                    : null,
                'initiated_by_type' => $initiatedBy['type'] ?? 'customer',
                'initiated_by_id' => $initiatedBy['id'] ?? $order->customer_id,
                'approved_by_admin_id' => $approvedByAdminId,
                'vendor_charged_back' => $liability === 'seller',
                'status' => 'processing',
                'gateway_fee_deducted' => $gatewayFeeDeducted,
                'tax_deducted' => $taxDeducted,
            ]);
            $refund->refresh();

            $this->settle($refund, $order, $resolvedDestination, $originalTransaction, $reverseLedger);

            return $refund->fresh();
        });
    }

    /**
     * Move the actual money for an already-created, still-'processing'
     * refund. Split out so RefundProcessingJob can call it for a refund
     * created ahead of time (e.g. by an admin action) without duplicating
     * the settlement logic.
     *
     * @param  bool  $reverseLedger  Pass false when the caller (e.g.
     *      OrderCancellationService for a whole-order cancel) will post
     *      its own full LedgerService::reverseOrderCapture() afterwards —
     *      posting both would double-reverse the capture group.
     */
    public function settle(Refund $refund, Order $order, string $resolvedDestination, ?PaymentTransaction $originalTransaction = null, bool $reverseLedger = true): void
    {
        $netAmount = (int) $refund->net_refund;

        try {
            if ($resolvedDestination === 'gateway') {
                $originalTransaction ??= $refund->originalTransaction;
                if (! $originalTransaction) {
                    throw new \DomainException('No original transaction to refund against.');
                }

                $result = $this->paymentService->refund($originalTransaction, $netAmount, $refund->reason->value);

                if (! $result->success) {
                    $refund->update(['status' => 'failed']);
                    Log::error('RefundService: gateway declined refund.', ['refund_id' => $refund->id, 'error' => $result->errorMessage]);

                    return;
                }

                $refundTransaction = $order->transactions()
                    ->where('type', 'refund')
                    ->latest('created_at')
                    ->first();

                // The fake/real gateway drivers in this codebase all confirm
                // a refund synchronously (a single HTTP call returning
                // success/failure) — none of them send an async "refund
                // confirmed" webhook. So marking 'completed' right here,
                // once $result->success is true, matches how the gateways
                // actually behave; inventing an async webhook-confirm flow
                // would not match any real driver.
                $refund->update(['status' => 'completed', 'refund_transaction_id' => $refundTransaction?->id]);
            } elseif ($resolvedDestination === 'wallet') {
                if ($netAmount > 0) {
                    $this->checkoutWalletService->refundToWallet($order->customer, $order, $netAmount);
                }
                $refund->update(['status' => 'completed']);
            } else { // 'bank' — manual payout, no automation exists.
                $refund->update(['status' => 'pending']);
                Log::warning('RefundService: refund requires a manual bank payout — no automated bank-payout driver exists.', [
                    'refund_id' => $refund->id,
                    'order_id' => $order->id,
                ]);

                return;
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Transient network failure talking to the gateway — leave the
            // refund 'processing' and let the caller (RefundProcessingJob)
            // retry with backoff instead of marking it permanently failed.
            Log::warning('RefundService: transient gateway connection failure, will retry.', ['refund_id' => $refund->id, 'exception' => $e->getMessage()]);

            throw $e;
        } catch (\Throwable $e) {
            $refund->update(['status' => 'failed']);
            Log::error('RefundService: settlement failed.', ['refund_id' => $refund->id, 'exception' => $e->getMessage()]);

            return;
        }

        $this->updateOrderPaymentStatus($order);

        if ($reverseLedger) {
            $this->postLedgerReversal($refund, $order);
        }
    }

    private function resolveDestination(Order $order, string $destination, bool $isElectronic): string
    {
        if ($destination === 'wallet') {
            return 'wallet';
        }

        if ($destination === 'bank') {
            return 'bank';
        }

        // 'original'
        if (! $isElectronic) {
            // COD (or wallet-funded) order — nothing was captured through a
            // card gateway, so 'original' means the wallet instead.
            return 'wallet';
        }

        return 'gateway';
    }

    /**
     * @return array{0: int, 1: ?string, 2: string} [grossAmountCents, subOrderId, refundType]
     */
    private function computeAmount(Order $order, RefundScope $scope, string $liability): array
    {
        if ($scope->kind === RefundScope::KIND_AMOUNT) {
            return [$scope->rawAmountCents, $scope->subOrderId, 'partial'];
        }

        $subOrder = SubOrder::where('id', $scope->subOrderId)->where('order_id', $order->id)->firstOrFail();

        if ($scope->kind === RefundScope::KIND_SHIPPING) {
            return [(int) $subOrder->shipping, $subOrder->id, 'shipping_only'];
        }

        // KIND_ITEMS
        $subOrder->loadMissing('items');
        $itemsById = $subOrder->items->keyBy('id');

        $amount = 0;
        $everyItemFullyRefunded = true;

        foreach ($subOrder->items as $item) {
            $qtyRequested = $scope->itemQuantities[$item->id] ?? 0;

            if ($qtyRequested < (int) $item->quantity) {
                $everyItemFullyRefunded = false;
            }

            if ($qtyRequested <= 0) {
                continue;
            }

            $amount += $this->lineShare($item, min($qtyRequested, (int) $item->quantity));
        }

        // Any item not even mentioned in the scope also blocks "every item
        // fully refunded".
        foreach ($itemsById as $id => $item) {
            if (! array_key_exists($id, $scope->itemQuantities)) {
                $everyItemFullyRefunded = false;
            }
        }

        $includeShipping = $everyItemFullyRefunded || in_array($liability, ['seller', 'platform', 'carrier'], true);
        if ($includeShipping) {
            $amount += (int) $subOrder->shipping;
        }

        $isFullSubOrder = $everyItemFullyRefunded;

        return [$amount, $subOrder->id, $isFullSubOrder ? 'full' : 'partial'];
    }

    /**
     * This item's line_total (subtotal - discount + tax, as persisted by
     * CheckoutPricingEngine at place-order) pro-rated to the quantity
     * actually being refunded.
     */
    private function lineShare(OrderItem $item, int $qty): int
    {
        if ((int) $item->quantity <= 0) {
            return 0;
        }

        if ($qty >= (int) $item->quantity) {
            return (int) $item->line_total;
        }

        return (int) round(((int) $item->line_total) * ($qty / (int) $item->quantity));
    }

    private function mapReason(string $reason): string
    {
        if (RefundReason::tryFrom($reason)) {
            return $reason;
        }

        return self::RETURN_REASON_MAP[$reason] ?? 'other';
    }

    private function updateOrderPaymentStatus(Order $order): void
    {
        $totalRefunded = (int) \App\Models\Refund::where('order_id', $order->id)
            ->where('status', 'completed')
            ->sum('amount');

        if ($totalRefunded <= 0) {
            return;
        }

        $newStatus = $totalRefunded >= (int) $order->total ? 'refunded' : 'partially_refunded';
        $order->update(['payment_status' => $newStatus]);
    }

    private function postLedgerReversal(Refund $refund, Order $order): void
    {
        $hasCapture = \App\Models\LedgerEntry::where('transaction_group_id', $order->id)
            ->where('reference_type', 'order_capture')
            ->exists();

        if (! $hasCapture) {
            return;
        }

        $alreadyReversedForThisRefund = \App\Models\LedgerEntry::where('reference_type', 'refund_reversal')
            ->where('reference_id', (string) $refund->id)
            ->exists();

        if ($alreadyReversedForThisRefund) {
            return;
        }

        $this->ledgerService->reversePartialCapture($order, (int) $refund->amount, (string) $refund->id, "Refund {$refund->id}");
    }
}
