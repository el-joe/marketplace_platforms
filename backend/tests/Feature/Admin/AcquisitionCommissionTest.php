<?php

namespace Tests\Feature\Admin;

use App\Jobs\ProcessAcquisitionCommissionsJob;
use App\Models\Admin;
use App\Models\Order;
use App\Models\SubOrder;
use App\Models\Vendor;
use App\Models\VendorAcquisitionCommission;
use App\Models\VendorAcquisitionCommissionEarning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** WS12 #15: assigning the employee who brought a vendor, and the monthly accrual of their commission. */
class AcquisitionCommissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['vendors.assigned_only', 'vendors.view', 'acquisition_commissions.view', 'acquisition_commissions.manage'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
    }

    private function manager(bool $canManage = true): Admin
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($canManage ? ['vendors.view', 'acquisition_commissions.view', 'acquisition_commissions.manage'] : ['vendors.view', 'acquisition_commissions.view']);

        return $admin;
    }

    /** @return array<string, mixed> */
    private function payload(Admin $agent, array $overrides = []): array
    {
        return array_merge([
            'admin_id' => $agent->id,
            'commission_rate' => 250, // 2.5 %
            'monthly_min_sales' => 2,
            'monthly_max_sales' => 3,
            'duration_months' => 12,
        ], $overrides);
    }

    public function test_the_page_explains_the_commission_and_offers_the_assign_dialog(): void
    {
        $this->actingAs($this->manager(), 'admin')
            ->get(route('admin.acquisition-commissions.index'))
            ->assertOk()
            ->assertSee(__('acquisition.help.title'))
            ->assertSee('acquisition-assign-modal');

        $this->actingAs($this->manager(canManage: false), 'admin')
            ->get(route('admin.acquisition-commissions.index'))
            ->assertOk()
            ->assertDontSee('acquisition-assign-modal');
    }

    public function test_an_employee_is_assigned_to_a_vendor_from_today_and_cannot_be_doubled(): void
    {
        $vendor = Vendor::factory()->create(['approved_at' => now()->subYears(3)]);
        $agent = Admin::factory()->create();

        $this->actingAs($this->manager(), 'admin')
            ->postJson(route('admin.vendors.acquisition-agent.store', $vendor), $this->payload($agent, ['valid_from' => now()->toDateString()]))
            ->assertOk();

        $deal = VendorAcquisitionCommission::where('vendor_id', $vendor->id)->sole();
        $this->assertSame('active', $deal->status);
        $this->assertSame($agent->id, $deal->admin_id);
        $this->assertSame(now()->addMonths(12)->toDateString(), $deal->valid_until->toDateString());

        $this->actingAs($this->manager(), 'admin')
            ->postJson(route('admin.vendors.acquisition-agent.store', $vendor), $this->payload(Admin::factory()->create()))
            ->assertStatus(422)
            ->assertJsonPath('message', __('acquisition.errors.active_exists'));
    }

    public function test_a_deal_that_would_already_be_over_is_refused(): void
    {
        // Default start = approval date, three years ago; 12 months later is in the past.
        $vendor = Vendor::factory()->create(['approved_at' => now()->subYears(3)]);

        $this->actingAs($this->manager(), 'admin')
            ->postJson(route('admin.vendors.acquisition-agent.store', $vendor), $this->payload(Admin::factory()->create()))
            ->assertStatus(422)
            ->assertJsonValidationErrors('valid_from');

        $this->assertSame(0, VendorAcquisitionCommission::where('vendor_id', $vendor->id)->count());
    }

    public function test_the_same_employee_can_be_assigned_again_after_a_revoke(): void
    {
        $vendor = Vendor::factory()->create();
        $agent = Admin::factory()->create();
        $manager = $this->manager();

        $this->actingAs($manager, 'admin')->postJson(route('admin.vendors.acquisition-agent.store', $vendor), $this->payload($agent))->assertOk();
        $first = VendorAcquisitionCommission::where('vendor_id', $vendor->id)->sole();
        $this->actingAs($manager, 'admin')->postJson(route('admin.vendors.acquisition-agent.revoke', [$vendor, $first]))->assertOk();
        $this->actingAs($manager, 'admin')->postJson(route('admin.vendors.acquisition-agent.store', $vendor), $this->payload($agent))->assertOk();

        $this->assertSame(['revoked', 'active'], VendorAcquisitionCommission::where('vendor_id', $vendor->id)->orderBy('created_at')->orderBy('status', 'desc')->pluck('status')->all());
    }

    public function test_assigning_needs_the_manage_permission(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($this->manager(canManage: false), 'admin')
            ->postJson(route('admin.vendors.acquisition-agent.store', $vendor), $this->payload(Admin::factory()->create()))
            ->assertForbidden();
        $this->actingAs($this->manager(canManage: false), 'admin')
            ->getJson(route('admin.acquisition-commissions.vendors.search', ['q' => $vendor->store_name]))
            ->assertForbidden();

        $this->actingAs($this->manager(), 'admin')
            ->getJson(route('admin.acquisition-commissions.vendors.search', ['q' => $vendor->store_name]))
            ->assertOk()
            ->assertJsonPath('results.0.id', $vendor->id);
    }

    public function test_completed_orders_of_last_month_accrue_rate_times_subtotal_up_to_the_monthly_cap(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(10);
        $deal = $this->deal($scenario->vendor, validFrom: now()->subMonths(3));

        $this->completedSubOrder($scenario, 1000, $lastMonth);
        $this->completedSubOrder($scenario, 2000, $lastMonth->copy()->addDay());
        $this->completedSubOrder($scenario, 3000, $lastMonth->copy()->addDays(2));
        $this->completedSubOrder($scenario, 4000, $lastMonth->copy()->addDays(3)); // over the cap of 3

        (new ProcessAcquisitionCommissionsJob)->handle();

        $earnings = VendorAcquisitionCommissionEarning::where('commission_id', $deal->id)->orderBy('order_count_in_month')->pluck('amount')->all();
        $this->assertEquals([25, 50, 75], $earnings);
        $this->assertEquals(150, $deal->fresh()->total_earned);

        // Re-running for the same month never pays twice.
        (new ProcessAcquisitionCommissionsJob)->handle();
        $this->assertEquals(150, $deal->fresh()->total_earned);
    }

    public function test_nothing_accrues_below_the_monthly_minimum_or_before_the_deal_started(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        // Deal starts on the 20th of last month: the order of the 5th does not count, leaving 1 < minimum 2.
        $deal = $this->deal($scenario->vendor, validFrom: $lastMonthStart->copy()->addDays(19));

        $this->completedSubOrder($scenario, 1000, $lastMonthStart->copy()->addDays(4));
        $this->completedSubOrder($scenario, 1000, $lastMonthStart->copy()->addDays(21));

        (new ProcessAcquisitionCommissionsJob)->handle();

        $this->assertSame(0, VendorAcquisitionCommissionEarning::where('commission_id', $deal->id)->count());
        $this->assertEquals(0, $deal->fresh()->total_earned);
    }

    public function test_my_commissions_shows_the_latest_processed_month_named_in_the_header(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(10);
        $deal = $this->deal($scenario->vendor, validFrom: now()->subMonths(3));
        $this->completedSubOrder($scenario, 1000, $lastMonth);
        $this->completedSubOrder($scenario, 2000, $lastMonth->copy()->addDay());
        (new ProcessAcquisitionCommissionsJob)->handle();

        app()->setLocale('en');
        $monthLabel = now()->subMonthNoOverflow()->startOfMonth()->locale('en')->translatedFormat('F Y');

        $this->actingAs(Admin::findOrFail($deal->admin_id), 'admin')
            ->withSession(['locale' => 'en'])
            ->get(route('admin.my-acquisition-commissions.index'))
            ->assertOk()
            ->assertSee(__('admin.static_text.admin_vendor_acquisition_my_commissions.earned_in_month', ['month' => $monthLabel], 'en'))
            ->assertSeeInOrder([$scenario->vendor->store_name, '2.50%', '>2<', '75'], false);
    }

    private function deal(Vendor $vendor, Carbon $validFrom): VendorAcquisitionCommission
    {
        return VendorAcquisitionCommission::create([
            'vendor_id' => $vendor->id,
            'admin_id' => Admin::factory()->create()->id,
            'commission_rate' => 250,
            'monthly_min_sales' => 2,
            'monthly_max_sales' => 3,
            'valid_from' => $validFrom->toDateString(),
            'valid_until' => $validFrom->copy()->addYear()->toDateString(),
            'status' => 'active',
            'currency' => 'AED',
        ]);
    }

    private function completedSubOrder(MarketplaceScenario $scenario, int $subtotal, Carbon $completedAt): SubOrder
    {
        $order = Order::create([
            'customer_id' => $scenario->customer->id,
            'country_id' => $scenario->country->id,
            'order_number' => 'ORD-ACQ-'.Str::random(8),
            'status' => 'completed',
            'currency' => 'AED',
            'subtotal' => $subtotal,
            'shipping' => 0,
            'tax' => 0,
            'total' => $subtotal,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'shipping_address_snapshot' => [],
            'ip_address' => '127.0.0.1',
            'placed_at' => $completedAt,
        ]);

        $subOrder = SubOrder::create([
            'order_id' => $order->id,
            'sub_order_number' => 'SO-ACQ-'.Str::random(8),
            'vendor_id' => $scenario->vendor->id,
            'status' => 'completed',
            'fulfillment_model' => 'fbm',
            'subtotal' => $subtotal,
            'shipping' => 0,
            'tax' => 0,
            'platform_commission' => 0,
            'vendor_payout' => $subtotal,
        ]);
        SubOrder::whereKey($subOrder->id)->update(['updated_at' => $completedAt]);

        return $subOrder;
    }
}
