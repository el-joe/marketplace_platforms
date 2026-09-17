<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Order;
use App\Services\Checkout\CouponUsageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Test-only helper for enhancement.md P-04's concurrency acceptance
 * criterion: reserves a coupon usage in its own OS process (its own DB
 * connection), so two invocations launched in parallel by a feature test
 * genuinely race against CouponUsageService::reserve()'s row lock instead
 * of merely running sequentially inside one PHP process/connection.
 */
class TestReserveCouponUsage extends Command
{
    protected $signature = 'test:reserve-coupon-usage {couponId} {customerId} {orderId} {discount} {delaySeconds=0}';

    protected $description = 'Test-only: reserve a coupon usage, for concurrency testing.';

    public function handle(CouponUsageService $service): int
    {
        $customer = Customer::find($this->argument('customerId'));
        $order = Order::find($this->argument('orderId'));

        try {
            // Mirrors real usage: Customer\CheckoutController calls reserve()
            // from inside its own place-order DB::transaction(). The row
            // lock reserve() takes only actually holds for the duration of
            // an enclosing transaction (autocommit releases it immediately
            // otherwise), so this wrapper must exist for the concurrency
            // test to reflect production behavior.
            DB::transaction(function () use ($service, $customer, $order) {
                $service->reserve(
                    $this->argument('couponId'),
                    $customer,
                    $order,
                    (int) $this->argument('discount'),
                    (int) $this->argument('delaySeconds'),
                );
            });
            $this->line('OK');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->line('FAILED: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
