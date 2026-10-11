<?php

namespace Tests\Feature\Admin;

use App\Models\Activity;
use App\Models\Admin;
use App\Models\Vendor;
use App\Models\VendorAcquisitionCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CommissionSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions = ['vendors.view', 'vendors.edit']): Admin
    {
        foreach (array_merge($permissions, ['vendors.assigned_only']) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    private function scopedAdmin(): Admin
    {
        return $this->admin(['vendors.view', 'vendors.edit', 'vendors.assigned_only']);
    }

    private function flatRateUrl(Vendor $vendor): string
    {
        return route('admin.commissions.vendors.commission.update', $vendor);
    }

    private function deal(Vendor $vendor, array $attributes = []): VendorAcquisitionCommission
    {
        return VendorAcquisitionCommission::create($attributes + [
            'vendor_id' => $vendor->id, 'admin_id' => Admin::factory()->create()->id, 'commission_rate' => 500,
            'monthly_min_sales' => 1, 'monthly_max_sales' => 10, 'valid_from' => '2026-01-01', 'valid_until' => '2027-01-01',
            'status' => 'active', 'currency' => 'USD',
        ]);
    }

    public function test_updates_flat_rate_only_and_logs_before_and_after(): void
    {
        $vendor = Vendor::factory()->create(['commission_rate' => 3, 'commission_discount_type' => 'flat', 'commission_discount_flat' => 50]);

        $this->actingAs($this->admin(), 'admin')
            ->patchJson($this->flatRateUrl($vendor), ['section' => 'flat_rate', 'commission_rate' => '7.5'])
            ->assertOk();

        $fresh = $vendor->fresh();
        $this->assertSame('7.50', (string) $fresh->commission_rate);
        $this->assertSame(50, (int) $fresh->commission_discount_flat, 'the flat-rate section must not touch the discount');
        $log = Activity::where('event', 'vendor_commission_updated')->firstOrFail();
        $this->assertSame('3.00', (string) $log->properties['before']['commission_rate']);
        $this->assertSame('7.50', (string) $log->properties['after']['commission_rate']);
    }

    public function test_clearing_the_flat_rate_stores_null_which_means_no_override(): void
    {
        $vendor = Vendor::factory()->create(['commission_rate' => 3]);

        $this->actingAs($this->admin(), 'admin')
            ->patchJson($this->flatRateUrl($vendor), ['section' => 'flat_rate', 'commission_rate' => null])
            ->assertOk();

        $this->assertNull($vendor->fresh()->commission_rate);
    }

    public function test_saves_a_percentage_discount(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->patchJson($this->flatRateUrl($vendor), [
                'section' => 'discount', 'commission_discount_type' => 'percentage', 'commission_discount_percentage' => '25', 'commission_discount_notes' => 'launch promo',
            ])->assertOk();

        $fresh = $vendor->fresh();
        $this->assertSame('percentage', $fresh->commission_discount_type->value);
        $this->assertEqualsWithDelta(25.0, (float) $fresh->commission_discount_percentage, 0.001);
        $this->assertSame(0, (int) $fresh->commission_discount_flat, 'blank flat amount falls back to the NOT NULL default');
        $this->assertSame('launch promo', $fresh->commission_discount_notes);
    }

    public function test_discount_none_with_blank_amounts_does_not_violate_not_null_columns(): void
    {
        $vendor = Vendor::factory()->create(['commission_discount_type' => 'flat', 'commission_discount_flat' => 90]);

        $this->actingAs($this->admin(), 'admin')
            ->patchJson($this->flatRateUrl($vendor), ['section' => 'discount', 'commission_discount_type' => 'none'])
            ->assertOk();

        $this->assertSame('none', $vendor->fresh()->commission_discount_type->value);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidVendorCommission(): array
    {
        return [
            'rate above 100' => [['section' => 'flat_rate', 'commission_rate' => '100.5'], 'commission_rate'],
            'negative rate' => [['section' => 'flat_rate', 'commission_rate' => '-1'], 'commission_rate'],
            'non numeric rate' => [['section' => 'flat_rate', 'commission_rate' => 'x'], 'commission_rate'],
            'unknown discount type' => [['section' => 'discount', 'commission_discount_type' => 'bogus'], 'commission_discount_type'],
            'negative flat discount' => [['section' => 'discount', 'commission_discount_type' => 'flat', 'commission_discount_flat' => '-5'], 'commission_discount_flat'],
            'percentage discount above 100' => [['section' => 'discount', 'commission_discount_type' => 'percentage', 'commission_discount_percentage' => '101'], 'commission_discount_percentage'],
            'unknown section' => [['section' => 'everything'], 'section'],
        ];
    }

    #[DataProvider('invalidVendorCommission')]
    public function test_rejects_invalid_vendor_commission_input(array $payload, string $field): void
    {
        $vendor = Vendor::factory()->create(['commission_rate' => 3]);

        $this->actingAs($this->admin(), 'admin')
            ->patchJson($this->flatRateUrl($vendor), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertSame('3.00', (string) $vendor->fresh()->commission_rate);
    }

    public function test_forbids_admin_without_vendors_edit(): void
    {
        $vendor = Vendor::factory()->create(['commission_rate' => 3]);

        $this->actingAs($this->admin(['vendors.view']), 'admin')
            ->patchJson($this->flatRateUrl($vendor), ['section' => 'flat_rate', 'commission_rate' => 9])
            ->assertForbidden();

        $this->assertSame('3.00', (string) $vendor->fresh()->commission_rate);
    }

    public function test_scoped_admin_can_edit_an_assigned_vendor_but_gets_404_for_a_foreign_one(): void
    {
        $admin = $this->scopedAdmin();
        $mine = Vendor::factory()->create(['account_manager_admin_id' => $admin->id, 'commission_rate' => 3]);
        $foreign = Vendor::factory()->create(['commission_rate' => 3]);

        $this->actingAs($admin, 'admin')->patchJson($this->flatRateUrl($mine), ['section' => 'flat_rate', 'commission_rate' => 6])->assertOk();
        $this->actingAs($admin, 'admin')->patchJson($this->flatRateUrl($foreign), ['section' => 'flat_rate', 'commission_rate' => 6])->assertNotFound();

        $this->assertSame('6.00', (string) $mine->fresh()->commission_rate);
        $this->assertSame('3.00', (string) $foreign->fresh()->commission_rate);
    }

    public function test_assigns_acquisition_agent_through_the_existing_write_path(): void
    {
        $vendor = Vendor::factory()->create(['approved_at' => '2026-02-01 00:00:00']);
        $agent = Admin::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.commissions.vendors.acquisition.store', $vendor), [
                'admin_id' => $agent->id, 'commission_rate' => 250, 'monthly_min_sales' => 2, 'monthly_max_sales' => 20, 'duration_months' => 6,
            ])->assertOk();

        $deal = VendorAcquisitionCommission::where('vendor_id', $vendor->id)->firstOrFail();
        $this->assertSame(250, (int) $deal->commission_rate);
        $this->assertSame('active', $deal->status);
        $this->assertSame('2026-08-01', $deal->valid_until->toDateString());
        $this->assertTrue(Activity::where('event', 'acquisition_agent_assigned')->exists());
    }

    public function test_rejects_invalid_acquisition_assignment_without_saving(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.commissions.vendors.acquisition.store', $vendor), [
                'admin_id' => Admin::factory()->create()->id, 'commission_rate' => 10001, 'monthly_min_sales' => 5, 'monthly_max_sales' => 2, 'duration_months' => 0,
            ])->assertUnprocessable()->assertJsonValidationErrors(['commission_rate', 'monthly_max_sales', 'duration_months']);

        $this->assertSame(0, VendorAcquisitionCommission::count());
    }

    public function test_updates_and_revokes_an_acquisition_deal(): void
    {
        $vendor = Vendor::factory()->create();
        $deal = $this->deal($vendor);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->putJson(route('admin.commissions.vendors.acquisition.update', [$vendor, $deal]), ['commission_rate' => 900, 'monthly_min_sales' => 3, 'monthly_max_sales' => 30])
            ->assertOk();
        $this->assertSame(900, (int) $deal->fresh()->commission_rate);

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.commissions.vendors.acquisition.revoke', [$vendor, $deal]))
            ->assertOk();
        $this->assertSame('revoked', $deal->fresh()->status);
    }

    public function test_acquisition_writes_are_forbidden_for_view_only_admin_and_hidden_for_foreign_vendor(): void
    {
        $vendor = Vendor::factory()->create();
        $deal = $this->deal($vendor);

        $this->actingAs($this->admin(['vendors.view']), 'admin')
            ->postJson(route('admin.commissions.vendors.acquisition.revoke', [$vendor, $deal]))->assertForbidden();

        $this->actingAs($this->scopedAdmin(), 'admin')
            ->postJson(route('admin.commissions.vendors.acquisition.revoke', [$vendor, $deal]))->assertNotFound();

        $this->assertSame('active', $deal->fresh()->status);
    }

    public function test_deal_belonging_to_another_vendor_cannot_be_modified_through_a_different_vendor_url(): void
    {
        $owner = Vendor::factory()->create();
        $other = Vendor::factory()->create();
        $deal = $this->deal($owner);

        $this->actingAs($this->admin(), 'admin')
            ->postJson(route('admin.commissions.vendors.acquisition.revoke', [$other, $deal]))
            ->assertNotFound();

        $this->assertSame('active', $deal->fresh()->status);
    }
}
