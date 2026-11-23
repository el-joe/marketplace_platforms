<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Admin → Customers list (client item #14): gender filter, "Joined between" (customers.created_at)
 * vs "Activity between" (orders feeding the orders / spend / points columns), and the export.
 */
class CustomerListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scenario = MarketplaceScenario::make()->build();

        foreach (['customers.view', 'customers.edit', 'customers.suspend', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['customers.view', 'customers.edit']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function customer(array $attributes = []): Customer
    {
        return Customer::create($attributes + [
            'name' => 'Customer '.Str::random(6),
            'email' => 'list-'.Str::lower(Str::random(10)).'@example.test',
            'password' => 'password',
            'country_id' => $this->scenario->country->id,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(Customer $customer, string $placedAt, array $attributes = []): Order
    {
        return Order::create($attributes + [
            'order_number' => 'T'.Str::upper(Str::random(12)),
            'customer_id' => $customer->id,
            'country_id' => $this->scenario->country->id,
            'status' => 'delivered',
            'currency' => 'AED',
            'subtotal' => 100, 'discount' => 0, 'shipping' => 0, 'tax' => 0, 'cod_fee' => 0,
            'warranty_total' => 0, 'total' => 100,
            'loyalty_points_earned' => 1,
            'payment_method' => 'cod', 'payment_status' => 'captured',
            'placed_at' => $placedAt,
            'shipping_address_snapshot' => [],
            'ip_address' => '127.0.0.1',
        ]);
    }

    /**
     * Column indexes follow the JS table: 7 = orders, 8 = spend, 10 = joined.
     *
     * @param  array<string, mixed>  $filters
     */
    private function datatable(array $filters = [], int $orderColumn = 10, string $dir = 'desc'): TestResponse
    {
        return $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.customers.datatable'), $filters + [
                'draw' => 1, 'start' => 0, 'length' => 100,
                'order' => [['column' => $orderColumn, 'dir' => $dir]],
            ]);
    }

    /**
     * @return array<string, array<string, mixed>> keyed by customer id
     */
    private function rowsById(TestResponse $response): array
    {
        return collect($response->assertOk()->json('data'))->keyBy('DT_RowData.id')->all();
    }

    public function test_gender_filter_matches_male_female_and_not_specified(): void
    {
        $male = $this->customer(['gender' => 'male']);
        $female = $this->customer(['gender' => 'female']);
        $unspecified = $this->customer();

        $this->assertSame([$male->id], array_keys($this->rowsById($this->datatable(['gender' => 'male']))));
        $this->assertSame([$female->id], array_keys($this->rowsById($this->datatable(['gender' => 'female']))));

        $unspecifiedIds = array_keys($this->rowsById($this->datatable(['gender' => 'unspecified'])));
        $this->assertContains($unspecified->id, $unspecifiedIds);
        $this->assertNotContains($male->id, $unspecifiedIds);
        $this->assertNotContains($female->id, $unspecifiedIds);

        $this->datatable(['gender' => 'other'])->assertUnprocessable()->assertJsonValidationErrors('gender');
    }

    public function test_joined_range_filters_on_registration_date_only(): void
    {
        $joinedInRange = $this->customer();
        $joinedInRange->forceFill(['created_at' => '2026-02-15 10:00:00'])->save();
        $joinedBefore = $this->customer();
        $joinedBefore->forceFill(['created_at' => '2025-12-01 10:00:00'])->save();
        // Activity inside the range must not pull an older customer into a "joined" filter.
        $this->order($joinedBefore, '2026-02-10 10:00:00');

        $ids = array_keys($this->rowsById($this->datatable(['date_from' => '2026-02-01', 'date_to' => '2026-02-28'])));

        $this->assertSame([$joinedInRange->id], $ids);
    }

    public function test_activity_range_limits_metrics_to_orders_placed_inside_it_and_skips_cancelled_and_failed(): void
    {
        $customer = $this->customer(['loyalty_points' => 500]);
        $this->order($customer, '2026-03-05 10:00:00', ['total' => 100, 'loyalty_points_earned' => 2]);
        $this->order($customer, '2026-03-20 10:00:00', ['total' => 50.5, 'loyalty_points_earned' => 3]);
        $this->order($customer, '2026-01-10 10:00:00', ['total' => 999, 'loyalty_points_earned' => 40]);
        $this->order($customer, '2026-03-12 10:00:00', ['total' => 700, 'status' => 'cancelled', 'loyalty_points_earned' => 7]);
        $this->order($customer, '2026-03-13 10:00:00', ['total' => 800, 'status' => 'placed', 'payment_status' => 'failed']);

        $inRange = $this->rowsById($this->datatable(['activity_from' => '2026-03-01', 'activity_to' => '2026-03-31']))[$customer->id];
        $this->assertSame(2, $inRange['total_orders']);
        $this->assertSame('150.50 AED', $inRange['total_spent']);
        $this->assertSame('5.00', $inRange['loyalty_points']);

        $allTime = $this->rowsById($this->datatable())[$customer->id];
        $this->assertSame(3, $allTime['total_orders']);
        $this->assertSame('1,149.50 AED', $allTime['total_spent']);
        $this->assertSame('500.00', $allTime['loyalty_points'], 'Without a range the points column is the current balance.');
    }

    public function test_spend_is_shown_per_currency_and_sorted_by_usd_equivalent(): void
    {
        Currency::updateOrCreate(['code' => 'AED'], ['name' => 'Dirham', 'symbol' => 'AED', 'decimal_places' => 2, 'exchange_rate_to_base' => 4, 'is_active' => true]);
        Currency::updateOrCreate(['code' => 'USD'], ['name' => 'Dollar', 'symbol' => '$', 'decimal_places' => 2, 'exchange_rate_to_base' => 1, 'is_active' => true]);

        $mixed = $this->customer();
        $this->order($mixed, '2026-03-05 10:00:00', ['total' => 400, 'currency' => 'AED']);   // 100 USD
        $this->order($mixed, '2026-03-06 10:00:00', ['total' => 30, 'currency' => 'USD']);    // 30 USD
        $bigAed = $this->customer();
        $this->order($bigAed, '2026-03-05 10:00:00', ['total' => 600, 'currency' => 'AED']);  // 150 USD

        $rows = $this->datatable(['activity_from' => '2026-03-01', 'activity_to' => '2026-03-31'], 8, 'desc')->assertOk()->json('data');
        $ids = array_column(array_column($rows, 'DT_RowData'), 'id');

        $this->assertLessThan(array_search($mixed->id, $ids), array_search($bigAed->id, $ids));
        $this->assertSame('400.00 AED · 30.00 USD', collect($rows)->firstWhere('DT_RowData.id', $mixed->id)['total_spent']);
    }

    public function test_sorting_by_orders_uses_the_activity_range_counts(): void
    {
        $busyInRange = $this->customer();
        $this->order($busyInRange, '2026-03-05 10:00:00');
        $this->order($busyInRange, '2026-03-06 10:00:00');
        $busyOutside = $this->customer();
        foreach (['2025-05-01', '2025-06-01', '2025-07-01'] as $day) {
            $this->order($busyOutside, $day.' 10:00:00');
        }

        $ids = array_column(array_column(
            $this->datatable(['activity_from' => '2026-03-01', 'activity_to' => '2026-03-31', 'min_orders' => 0], 7, 'desc')->assertOk()->json('data'),
            'DT_RowData'
        ), 'id');

        $this->assertLessThan(array_search($busyOutside->id, $ids), array_search($busyInRange->id, $ids));
    }

    public function test_export_respects_gender_and_activity_filters(): void
    {
        $female = $this->customer(['gender' => 'female', 'name' => 'Export Female']);
        $this->order($female, '2026-03-05 10:00:00', ['total' => 100]);
        $this->order($female, '2026-01-05 10:00:00', ['total' => 900]);
        $this->customer(['gender' => 'male', 'name' => 'Export Male']);

        $csv = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.customers.index', [
                'export' => 'csv', 'gender' => 'female',
                'activity_from' => '2026-03-01', 'activity_to' => '2026-03-31',
            ]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Export Female', $csv);
        $this->assertStringNotContainsString('Export Male', $csv);
        $this->assertStringContainsString('100.00 AED', $csv);
        $this->assertStringNotContainsString('900.00 AED', $csv);
    }

    public function test_admin_can_set_and_clear_a_customer_gender(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->admin, 'admin')
            ->putJson(route('admin.customers.update', $customer), ['gender' => 'female'])
            ->assertOk();
        $this->assertSame('female', $customer->fresh()->gender?->value);

        $this->putJson(route('admin.customers.update', $customer), ['gender' => null])->assertOk();
        $this->assertNull($customer->fresh()->gender);

        $this->putJson(route('admin.customers.update', $customer), ['gender' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gender');
    }
}
