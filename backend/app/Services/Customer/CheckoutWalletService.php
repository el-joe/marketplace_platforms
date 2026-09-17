<?php

namespace App\Services\Customer;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class CheckoutWalletService
{
    public function applyWalletToOrder(Customer $customer, Order $order, int $walletAmountToUse): void
    {
        if ($walletAmountToUse <= 0) {
            throw new \InvalidArgumentException('Wallet amount to use must be greater than zero.');
        }

        if ($walletAmountToUse > $order->total) {
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

            $wallet->update(['balance' => $wallet->balance - $walletAmountToUse]);

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
                'payment_method' => $walletAmountToUse === $order->total ? 'wallet' : $order->payment_method,
            ]);
        });
    }

    public function refundToWallet(Customer $customer, Order $order, int $refundAmount): void
    {
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

            $wallet->update(['balance' => $wallet->balance + $refundAmount]);

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
