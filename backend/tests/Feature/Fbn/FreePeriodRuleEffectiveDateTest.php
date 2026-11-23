<?php

namespace Tests\Feature\Fbn;

use App\Enums\FbnStorageFeeStatus;
use App\Jobs\GenerateFbnStorageFeesJob;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\FbnStorageFee;
use App\Models\StorageFeeFreePeriodRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Client item #11: a free-period rule applies only to storage that started on
 * or after its effective date (default: the rule's creation day). Invoiced and
 * paid fees are never re-priced; pending fees only after explicit confirmation.
 */
class FreePeriodRuleEffectiveDateTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scenario = MarketplaceScenario::make()->build();
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['fbn_storage_fees.view', 'fbn_storage_fees.manage']);

        StorageFeeFreePeriodRule::query()->delete();

        $this->scenario->platformWarehouse->update(['storage_rate_per_m3_price' => 10000, 'storage_currency' => 'AED']);
        // 10x10x10 cm = 0.001 m3 (volumetric 200 g), declared 500 g -> chargeable 500 g.
        $this->scenario->vendorListingFbn->update([
            'declared_weight_grams' => 500,
            'declared_length_cm' => 10,
            'declared_width_cm' => 10,
            'declared_height_cm' => 10,
        ]);
        DB::table('warehouse_inventories')
            ->where('id', $this->scenario->vendorListingFbnInventory->id)
            ->update(['first_stocked_at' => now()->subDays(40)->startOfDay()]);
    }

    private function generateCurrentMonth(): FbnStorageFee
    {
        GenerateFbnStorageFeesJob::dispatchSync(now()->format('Y-m'));

        return FbnStorageFee::where('warehouse_inventory_id', $this->scenario->vendorListingFbnInventory->id)
            ->whereDate('month', now()->startOfMonth())
            ->sole();
    }

    public function test_a_rule_effective_after_the_storage_started_does_not_apply_to_it(): void
    {
        StorageFeeFreePeriodRule::create(['min_weight_grams' => 0, 'max_weight_grams' => 999, 'free_days' => 90, 'effective_from' => now()->subDays(10)->toDateString()]);

        $fee = $this->generateCurrentMonth();

        $this->assertSame(0, $fee->free_days_applied);
        $this->assertFalse($fee->within_free_period);
        $this->assertGreaterThan(0, (float) $fee->total_fee);
    }

    public function test_a_rule_effective_before_the_storage_started_applies_to_it(): void
    {
        StorageFeeFreePeriodRule::create(['min_weight_grams' => 0, 'max_weight_grams' => 999, 'free_days' => 90, 'effective_from' => now()->subDays(60)->toDateString()]);

        $fee = $this->generateCurrentMonth();

        $this->assertSame(90, $fee->free_days_applied);
        $this->assertTrue($fee->within_free_period);
        $this->assertEqualsWithDelta(0.0, (float) $fee->total_fee, 0.0001);
    }

    public function test_regenerating_a_month_never_reprices_an_invoiced_or_paid_fee(): void
    {
        foreach ([FbnStorageFeeStatus::Invoiced, FbnStorageFeeStatus::Paid] as $status) {
            FbnStorageFee::query()->delete();
            $fee = FbnStorageFee::create([
                'vendor_id' => $this->scenario->vendor->id,
                'warehouse_inventory_id' => $this->scenario->vendorListingFbnInventory->id,
                'month' => now()->startOfMonth()->toDateString(),
                'units_stored' => 1,
                'rate_per_unit' => 1,
                'total_fee' => 99.5,
                'currency' => 'AED',
                'status' => $status,
            ]);

            GenerateFbnStorageFeesJob::dispatchSync(now()->format('Y-m'));

            $fee->refresh();
            $this->assertSame($status, $fee->status);
            $this->assertEqualsWithDelta(99.5, (float) $fee->total_fee, 0.0001);
        }
    }

    public function test_the_effective_date_defaults_to_the_creation_day_or_takes_the_chosen_date(): void
    {
        $store = fn (array $payload) => $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.fbn.storage-fees.free-period-rules.store'), $payload + ['free_days' => 15, 'open_ended' => false])
            ->assertOk()
            ->json('id');

        $fromCreation = $store(['min_weight_grams' => 0, 'max_weight_grams' => 99]);
        $fromDate = $store(['min_weight_grams' => 100, 'max_weight_grams' => 199, 'effective_mode' => 'from_date', 'effective_from' => '2026-01-15']);
        $allStorage = $store(['min_weight_grams' => 200, 'max_weight_grams' => 299, 'effective_mode' => 'from_date']);

        $this->assertSame(admin_tz(now())->toDateString(), StorageFeeFreePeriodRule::find($fromCreation)->effective_from->toDateString());
        $this->assertSame('2026-01-15', StorageFeeFreePeriodRule::find($fromDate)->effective_from->toDateString());
        $this->assertNull(StorageFeeFreePeriodRule::find($allStorage)->effective_from);
    }

    public function test_the_creation_day_follows_the_admin_timezone_just_after_local_midnight(): void
    {
        $this->travelTo(admin_day_start('2026-11-24')->addMinutes(20));

        $ruleId = $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.fbn.storage-fees.free-period-rules.store'), [
                'min_weight_grams' => 0, 'max_weight_grams' => 99, 'free_days' => 15, 'open_ended' => false,
            ])
            ->assertOk()
            ->json('id');

        $this->assertSame('2026-11-24', StorageFeeFreePeriodRule::find($ruleId)->effective_from->toDateString());
    }

    public function test_pending_fees_are_only_recalculated_after_an_explicit_confirmation(): void
    {
        $pending = $this->generateCurrentMonth();
        $chargedAmount = (float) $pending->total_fee;
        $this->assertGreaterThan(0, $chargedAmount);

        $invoiced = $pending->replicate()->fill([
            'month' => now()->subMonth()->startOfMonth()->toDateString(),
            'status' => FbnStorageFeeStatus::Invoiced,
        ]);
        $invoiced->save();

        // Option B with no date = applies to all storage, so both fees are in scope.
        $response = $this->actingAs($this->admin, 'admin')->postJson(route('admin.fbn.storage-fees.free-period-rules.store'), [
            'min_weight_grams' => 0, 'max_weight_grams' => 999, 'open_ended' => false, 'free_days' => 60, 'effective_mode' => 'from_date',
        ])->assertOk();

        $response->assertJsonPath('pending_impact.count', 1);
        $this->assertEqualsWithDelta($chargedAmount, $response->json('pending_impact.totals.AED.current'), 0.0001);
        $this->assertEqualsWithDelta(0.0, $response->json('pending_impact.totals.AED.recalculated'), 0.0001);
        $this->assertEqualsWithDelta($chargedAmount, (float) $pending->fresh()->total_fee, 0.0001, 'Saving a rule must not re-price fees by itself.');

        $recalculate = route('admin.fbn.storage-fees.free-period-rules.recalculate-pending');
        $this->actingAs($this->admin, 'admin')->postJson($recalculate)->assertJsonValidationErrors('confirm');
        $this->actingAs($this->admin, 'admin')->postJson($recalculate, ['confirm' => true])->assertOk()->assertJsonPath('summary.count', 1);

        $pending->refresh();
        $this->assertSame(60, $pending->free_days_applied);
        $this->assertTrue($pending->within_free_period);
        $this->assertEqualsWithDelta(0.0, (float) $pending->total_fee, 0.0001);

        $invoiced->refresh();
        $this->assertSame(0, $invoiced->free_days_applied);
        $this->assertEqualsWithDelta($chargedAmount, (float) $invoiced->total_fee, 0.0001);

        $this->assertTrue(Activity::where('event', 'storage_fees_recalculated')->where('causer_id', $this->admin->id)->exists());
    }
}
