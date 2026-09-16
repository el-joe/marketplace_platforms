<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerService
{
    /**
     * Record a set of balanced double-entry ledger entries.
     *
     * All entries in a group must satisfy: SUM(debit) === SUM(credit).
     * Throws if the entries do not balance.
     *
     * @param  string  $groupId  UUID linking all entries in this transaction
     * @param  array<int, array{
     *   account_type: string,
     *   account_holder_type: string|null,
     *   account_holder_id: string|null,
     *   debit: int,
     *   credit: int,
     *   currency: string,
     *   reference_type: string,
     *   reference_id: string,
     *   description: string
     * }>  $entries
     *
     * @throws \InvalidArgumentException if the entries do not balance
     */
    public function record(string $groupId, array $entries): void
    {
        $totalDebit = array_sum(array_column($entries, 'debit'));
        $totalCredit = array_sum(array_column($entries, 'credit'));

        if ($totalDebit !== $totalCredit) {
            throw new \InvalidArgumentException(
                "Ledger entries must balance. Debit total ({$totalDebit}) ≠ credit total ({$totalCredit})."
            );
        }

        DB::transaction(function () use ($groupId, $entries) {
            foreach ($entries as $entry) {
                LedgerEntry::create([
                    'transaction_group_id' => $groupId,
                    'account_type' => $entry['account_type'],
                    'account_holder_type' => $entry['account_holder_type'] ?? null,
                    'account_holder_id' => $entry['account_holder_id'] ?? null,
                    'debit' => $entry['debit'] ?? 0,
                    'credit' => $entry['credit'] ?? 0,
                    'currency' => $entry['currency'],
                    'reference_type' => $entry['reference_type'],
                    'reference_id' => $entry['reference_id'],
                    'description' => $entry['description'],
                ]);
            }
        });
    }

    /**
     * Generate a new transaction group UUID.
     */
    public function newGroupId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Per-order double-entry ledger at payment capture (enhancement.md
     * P-03 task 5): Dr customer_payment / Cr seller_payable,
     * platform_commission, tax_payable, gateway_fee, marketer_commission_payable,
     * shipping_revenue. Uses the order's own id as the transaction group, so
     * callers (and P-06/P-07's reversal) can find/assert this group with
     * `LedgerEntry::forGroup($order->id)` without tracking a separate id.
     *
     * seller_payable/platform_commission/gateway_fee/marketer_commission_payable
     * are taken directly from the sub_orders rows CheckoutPricingEngine
     * persisted at place-order (never recomputed here). shipping_revenue is
     * the residual that makes the group balance — it absorbs the
     * customer's shipping/cod/warranty payment net of what's already been
     * assigned to the other accounts, which is the platform's true
     * shipping-and-extras margin on this capture.
     *
     * Idempotent: does nothing if this order's capture group already has
     * entries (guards against being called twice, e.g. wallet-then-webhook).
     *
     * @param  int  $amountCapturedCents  the amount actually settled by this capture event (order.total for a single-tender order)
     */
    public function postOrderCapture(Order $order, int $amountCapturedCents): void
    {
        if ($amountCapturedCents <= 0) {
            return;
        }

        if (LedgerEntry::where('transaction_group_id', $order->id)
            ->where('reference_type', 'order_capture')
            ->exists()) {
            return;
        }

        $order->loadMissing('subOrders');

        $sellerPayableByVendor = [];
        $platformCommission = 0;
        $marketerCommissionPayable = 0;
        $gatewayFee = 0;
        foreach ($order->subOrders as $subOrder) {
            if ($subOrder->vendor_id !== null) {
                $sellerPayableByVendor[$subOrder->vendor_id] = ($sellerPayableByVendor[$subOrder->vendor_id] ?? 0) + (int) $subOrder->vendor_payout;
            }
            $platformCommission += (int) $subOrder->platform_commission;
            $marketerCommissionPayable += (int) $subOrder->marketer_commission;
            $gatewayFee += (int) $subOrder->gateway_fee;
        }

        $taxPayable = (int) $order->tax;
        $sellerPayableTotal = array_sum($sellerPayableByVendor);

        // Residual: whatever's left of the captured amount once every other
        // account has taken its share. See the docblock above.
        $shippingRevenue = $amountCapturedCents - $sellerPayableTotal - $platformCommission - $taxPayable - $gatewayFee - $marketerCommissionPayable;

        $entries = [[
            'account_type' => 'customer_payment',
            'account_holder_type' => 'customer',
            'account_holder_id' => $order->customer_id,
            'debit' => $amountCapturedCents,
            'credit' => 0,
            'currency' => $order->currency,
            'reference_type' => 'order_capture',
            'reference_id' => (string) $order->id,
            'description' => "Payment captured for order {$order->order_number}",
        ]];

        foreach ($sellerPayableByVendor as $vendorId => $amount) {
            if ($amount === 0) {
                continue;
            }
            $entries[] = [
                'account_type' => 'seller_payable',
                'account_holder_type' => 'vendor',
                'account_holder_id' => $vendorId,
                'debit' => 0,
                'credit' => $amount,
                'currency' => $order->currency,
                'reference_type' => 'order_capture',
                'reference_id' => (string) $order->id,
                'description' => "Seller payable for order {$order->order_number}",
            ];
        }

        foreach ([
            ['platform_commission', $platformCommission],
            ['tax_payable', $taxPayable],
            ['gateway_fee', $gatewayFee],
            ['marketer_commission_payable', $marketerCommissionPayable],
            ['shipping_revenue', $shippingRevenue],
        ] as [$accountType, $amount]) {
            if ($amount === 0) {
                continue;
            }
            $entries[] = [
                'account_type' => $accountType,
                'account_holder_type' => null,
                'account_holder_id' => null,
                'debit' => 0,
                'credit' => $amount,
                'currency' => $order->currency,
                'reference_type' => 'order_capture',
                'reference_id' => (string) $order->id,
                'description' => "{$accountType} for order {$order->order_number}",
            ];
        }

        $this->record($order->id, $entries);
    }

    /**
     * Reverse a previously-posted capture group (enhancement.md P-03 task 5:
     * "expose a reversal method P-06/P-07 can call"). Posts the exact
     * mirror image of every entry in $order->id's capture group — debit and
     * credit swapped — under a new group id, so both groups independently
     * balance and the reversal is auditable on its own.
     *
     * @return string the new transaction group id the reversal was posted under
     */
    public function reverseOrderCapture(Order $order, string $reason = 'Order cancelled/refunded'): string
    {
        $original = LedgerEntry::where('transaction_group_id', $order->id)
            ->where('reference_type', 'order_capture')
            ->get();

        if ($original->isEmpty()) {
            throw new \RuntimeException("No order_capture ledger entries found for order {$order->id} to reverse.");
        }

        $groupId = $this->newGroupId();
        $entries = $original->map(fn (LedgerEntry $e) => [
            'account_type' => $e->account_type,
            'account_holder_type' => $e->account_holder_type,
            'account_holder_id' => $e->account_holder_id,
            'debit' => (int) $e->credit,
            'credit' => (int) $e->debit,
            'currency' => $e->currency,
            'reference_type' => 'order_capture_reversal',
            'reference_id' => (string) $order->id,
            'description' => "{$reason}: reversal of order {$order->order_number}",
        ])->all();

        $this->record($groupId, $entries);

        return $groupId;
    }
}
