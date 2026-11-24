<?php

namespace App\Services\Customer;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class CheckoutWalletService
{
    /**
     * R1 (retest 2026-11-24): the storefront asks to "use the wallet" by sending an amount (it used
     * to send the whole balance, e.g. 20 for a 15.75 order). The amount actually debited is always
     * min(requested, payable total, wallet balance), so a balance above the total never rejects
     * the order and float/DECIMAL(19,4) rounding can never push the debit past the total.
     */
    public function resolveAmountToUse(int|float|string|null $requested, int|float|string|null $payableTotal, int|float|string|null $walletBalance): int|float
    {
        $requested = (float) ($requested ?? 0);

        if ($requested <= 0) {
            return 0;
        }

        return Money::number(max(0, min($requested, (float) ($payableTotal ?? 0), (float) ($walletBalance ?? 0))));
    }

    public function applyWalletToOrder(Customer $customer, Order $order, int|float $walletAmountToUse): void
    {
        $walletAmountToUse = Money::number($walletAmountToUse);

        if ($walletAmountToUse <= 0) {
            throw new \InvalidArgumentException('Wallet amount to use must be greater than zero.');
        }

        if (round($walletAmountToUse - (float) $order->total, 4) > 0) {
            throw new \DomainException('Wallet amount cannot exceed the order total.');
        }

        DB::transaction(function () use ($customer, $order, $walletAmountToUse) {
            $wallet = Wallet::where('owner_type', 'customer')->where('owner_id', $customer->id)->lockForUpdate()->first();

            if (! $wallet || $wallet->balance < $walletAmountToUse) {
                throw new InsufficientWalletBalanceException();
            }

            if ($wallet->currency !== $order->currency) {
                throw new \DomainException('Wallet currency does not match order currency.');
            }

            $wallet->update(['balance' => Money::number($wallet->balance - $walletAmountToUse)]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'customer_id' => $customer->id,
                'type' => 'order_payment',
                'direction' => 'debit',
                'amount' => $walletAmountToUse,
                'balance_after' => $wallet->balance,
                'currency_code' => $wallet->currency,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                // enhancement.md P-05 task 7: wallet_transactions.source_type
                // is NOT NULL with no DB default — omitting it broke every
                // partial wallet+card payment (and full wallet payments) in
                // strict mode. 'order' matches what OrderController@cancel's
                // refund lookup already expects.
                'source_type' => 'order',
                'source_id' => $order->id,
            ]);

            $order->update([
                'wallet_amount_used' => $walletAmountToUse,
                'payment_method' => abs($walletAmountToUse - (float) $order->total) < 0.00005 ? 'wallet' : $order->payment_method,
            ]);
        });
    }

    public function refundToWallet(Customer $customer, Order $order, int|float $refundAmount): void
    {
        $refundAmount = Money::number($refundAmount);

        if ($refundAmount <= 0) {
            throw new \InvalidArgumentException('Refund amount must be greater than zero.');
        }

        DB::transaction(function () use ($customer, $order, $refundAmount) {
            $wallet = Wallet::where('owner_type', 'customer')->where('owner_id', $customer->id)->lockForUpdate()->first();

            if (! $wallet) {
                $wallet = Wallet::create([
                    'owner_type' => 'customer',
                    'owner_id' => $customer->id,
                    'balance' => 0,
                    'pending_balance' => 0,
                    'currency' => $order->currency,
                ]);
            }

            $wallet->update(['balance' => Money::number($wallet->balance + $refundAmount)]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'customer_id' => $customer->id,
                'type' => 'order_refund',
                'direction' => 'credit',
                'amount' => $refundAmount,
                'balance_after' => $wallet->balance,
                'currency_code' => $wallet->currency,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'source_type' => 'order',
                'source_id' => $order->id,
            ]);
        });
    }
}
