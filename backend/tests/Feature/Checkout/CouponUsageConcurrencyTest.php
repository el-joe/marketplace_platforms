<?php

namespace Tests\Feature\Checkout;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * enhancement.md P-04 acceptance criterion: two parallel placements against
 * a coupon with usage_limit_total=1 must result in exactly one success.
 * Each side runs in its own OS process (own DB connection) via the
 * test:reserve-coupon-usage artisan command, so this proves
 * CouponUsageService::reserve()'s SELECT ... FOR UPDATE actually serializes
 * the two attempts rather than merely happening to pass sequentially.
 *
 * Deliberately does NOT use RefreshDatabase or MarketplaceScenario: that
 * trait wraps the test in an uncommitted transaction, which the two
 * subprocesses (separate DB connections) would never see. This test only
 * needs a country, a customer, a coupon and two orders — not the full
 * marketplace scenario — so it builds those directly and reuses/deletes
 * them by hand, keeping reruns idempotent without touching shared fixtures.
 */
class CouponUsageConcurrencyTest extends TestCase
{
    private array $orderIds = [];

    private ?Coupon $coupon = null;

    private ?Customer $customer = null;

    private ?\App\Models\Admin $admin = null;

    private ?Country $country = null;

    protected function tearDown(): void
    {
        CouponUsage::whereIn('order_id', $this->orderIds)->delete();
        Order::whereIn('id', $this->orderIds)->delete();
        $this->coupon?->delete();
        $this->customer?->delete();
        $this->admin?->delete();
        // Every other test in the suite uses RefreshDatabase and creates
        // its own 'AE' country inside a rolled-back transaction; leaving
        // this one committed permanently breaks all of them on the unique
        // iso_code_2 constraint. This test is the only one that persists
        // real rows (see class docblock), so it alone is responsible for
        // deleting the country it created.
        $this->country?->delete();

        parent::tearDown();
    }

    private function dummyOrder(Country $country, Customer $customer, string $number): Order
    {
        return Order::create([
            'order_number' => $number,
            'customer_id' => $customer->id,
            'country_id' => $country->id,
            'status' => 'placed',
            'currency' => 'AED',
            'subtotal' => 100, 'discount' => 0, 'shipping' => 0, 'tax' => 0, 'cod_fee' => 0,
            'warranty_total' => 0, 'total' => 100,
            'payment_method' => 'cod', 'payment_status' => 'pending',
            'placed_at' => now(),
            'shipping_address_snapshot' => [],
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_two_concurrent_reservations_against_usage_limit_one_result_in_exactly_one_success(): void
    {
        // Uses a throwaway ISO code distinct from 'AE' (used pervasively by
        // MarketplaceScenario under RefreshDatabase elsewhere in the suite)
        // so this test's committed, non-rolled-back row can't collide with
        // anything else on the unique iso_code_2 constraint.
        $this->country = $country = Country::create([
            'id' => (string) Str::uuid(),
            'iso_code_2' => 'ZZ', 'iso_code_3' => 'ZZZ', 'name_ar' => 'اختبار', 'name_en' => 'Race Test Country',
            'currency_code' => 'AED', 'vat_rate' => 5, 'is_active' => true, 'is_launched' => true,
            'cod_available' => true, 'timezone' => 'Asia/Dubai',
        ]);

        $this->customer = $customer = Customer::create([
            'name' => 'Race Test Customer',
            'email' => 'race-' . Str::lower(Str::random(10)) . '@example.test',
            'phone' => '+9715' . random_int(10000000, 99999999),
            'password' => Hash::make('password'),
            'country_id' => $country->id,
        ]);

        $this->admin = $admin = \App\Models\Admin::factory()->create();

        $this->coupon = $coupon = Coupon::create([
            'code' => 'RACE-' . Str::upper(Str::random(8)),
            'created_by_user_id' => $admin->id,
            'name' => 'Race test coupon',
            'type' => 'percentage',
            'value' => 10,
            'currency' => 'AED',
            'scope' => 'platform',
            'usage_limit_per_customer' => 5,
            'usage_limit_total' => 1,
            'times_used' => 0,
            'customer_eligibility' => 'all',
            'funded_by' => 'platform',
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'is_active' => true,
        ]);

        $orderA = $this->dummyOrder($country, $customer, 'RACE-A-' . substr(uniqid(), -8));
        $orderB = $this->dummyOrder($country, $customer, 'RACE-B-' . substr(uniqid(), -8));
        $this->orderIds = [$orderA->id, $orderB->id];

        $php = PHP_BINARY;
        $artisan = base_path('artisan');

        // Both processes sleep for 2s after acquiring the row lock, so their
        // execution windows are guaranteed to overlap regardless of process
        // start jitter — this is what makes the test prove serialization
        // rather than accidentally passing due to fast sequential timing.
        $procA = Process::start("{$php} {$artisan} test:reserve-coupon-usage {$coupon->id} {$customer->id} {$orderA->id} 10 2");
        $procB = Process::start("{$php} {$artisan} test:reserve-coupon-usage {$coupon->id} {$customer->id} {$orderB->id} 10 2");

        $resultA = $procA->wait();
        $resultB = $procB->wait();

        $outputs = [$resultA->output(), $resultB->output()];
        $successes = count(array_filter($outputs, fn ($o) => str_contains($o, 'OK')));
        $failures = count(array_filter($outputs, fn ($o) => str_contains($o, 'FAILED')));

        $this->assertSame(1, $successes, 'Expected exactly one of the two concurrent reservations to succeed. Outputs: ' . implode(' | ', $outputs));
        $this->assertSame(1, $failures);

        $this->assertSame(1, $coupon->refresh()->times_used);
        $this->assertSame(1, CouponUsage::where('coupon_id', $coupon->id)->where('status', CouponUsage::STATUS_RESERVED)->count());
    }
}
