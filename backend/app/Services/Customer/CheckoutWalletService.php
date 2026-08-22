<?php

namespace App\Services\Customer;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Order;
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
            $wallet = CustomerWallet::where('customer_id', $customer->id)->lockForUpdate()->first();

            if (! $wallet || $wallet->balance < $walletAmountToUse) {
                throw new InsufficientWalletBalanceException();
            }

            if ($wallet->currency_code !== $order->currency) {
                throw new \DomainException('Wallet currency does not match order currency.');
            }

            $wallet->debit($walletAmountToUse);
            $wallet->refresh();

            WalletTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'order_payment',
                'direction' => 'debit',
                'amount' => $walletAmountToUse,
                'balance_after' => $wallet->balance,
                'currency_code' => $wallet->currency_code,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
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
            $wallet = CustomerWallet::where('customer_id', $customer->id)->lockForUpdate()->first();

            if (! $wallet) {
                $wallet = CustomerWallet::create([
                    'customer_id' => $customer->id,
                    'balance' => 0,
                    'currency_code' => $order->currency,
                ]);
            }

            $wallet->credit($refundAmount);
            $wallet->refresh();

            WalletTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'order_refund',
                'direction' => 'credit',
                'amount' => $refundAmount,
                'balance_after' => $wallet->balance,
                'currency_code' => $wallet->currency_code,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
            ]);
        });
    }
}
