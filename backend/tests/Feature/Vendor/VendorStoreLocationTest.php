<?php

namespace Tests\Feature\Vendor;

use App\Models\Activity;
use App\Models\Admin;
use App\Models\VendorAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * WS13b: vendor store location (business address pin) — the distance-pricing origin of last resort.
 */
class VendorStoreLocationTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scenario = MarketplaceScenario::make()->build();
    }

    private function vendorAdminWith(string ...$permissions): VendorAdmin
    {
        $vendorAdmin = VendorAdmin::create([
            'vendor_id' => $this->scenario->vendor->id,
            'name' => 'Staff',
            'email' => 'staff-'.Str::random(8).'@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'is_owner' => false,
            'is_active' => true,
        ]);
        foreach ($permissions as $permission) {
            $vendorAdmin->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'vendor']));
        }

        return $vendorAdmin;
    }

    private function adminWith(string ...$permissions): Admin
    {
        foreach (['vendors.view', 'vendors.edit', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    public function test_partner_saves_the_store_location_with_arabic_digits(): void
    {
        $this->actingAs($this->vendorAdminWith('settings.view', 'settings.edit'), 'vendor')
            ->putJson(route('partner.profile.location.update'), [
                'store_address' => 'Al Khuwair, Way 3013',
                'latitude' => '٢٣٫٥٨٨',
                'longitude' => '58.3829',
            ])
            ->assertOk()
            ->assertJsonPath('data.has_coordinates', true);

        $address = $this->scenario->vendor->fresh()->businessAddress;
        $this->assertSame('Al Khuwair, Way 3013', $address->street_address);
        $this->assertEqualsWithDelta(23.588, $address->latitude, 0.0000001);
        $this->assertEqualsWithDelta(58.3829, $address->longitude, 0.0000001);
    }

    public function test_partner_profile_and_admin_vendor_page_show_the_location_form(): void
    {
        $this->actingAs($this->vendorAdminWith('settings.view', 'settings.edit'), 'vendor')
            ->get(route('partner.profile.index'))
            ->assertOk()
            ->assertSee(route('partner.profile.location.update'), false);

        Role::firstOrCreate(['name' => 'vendor_relations_admin', 'guard_name' => 'admin']);
        $this->actingAs($this->adminWith('vendors.view', 'vendors.edit'), 'admin')
            ->get(route('admin.vendors.show', $this->scenario->vendor))
            ->assertOk()
            ->assertSee(route('admin.vendors.location.update', $this->scenario->vendor), false);
    }

    public function test_partner_location_validation_rejects_partial_out_of_range_and_zero_points(): void
    {
        $vendorAdmin = $this->vendorAdminWith('settings.view', 'settings.edit');
        $url = route('partner.profile.location.update');

        $this->actingAs($vendorAdmin, 'vendor')->putJson($url, ['latitude' => '23.5', 'longitude' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('longitude');
        $this->actingAs($vendorAdmin, 'vendor')->putJson($url, ['latitude' => '91', 'longitude' => '58'])
            ->assertUnprocessable()->assertJsonValidationErrors('latitude');
        $this->actingAs($vendorAdmin, 'vendor')->putJson($url, ['latitude' => '0', 'longitude' => '0'])
            ->assertUnprocessable()->assertJsonValidationErrors('latitude');
    }

    public function test_partner_without_settings_edit_cannot_change_the_location(): void
    {
        $before = $this->scenario->vendor->business_address_id;

        $this->actingAs($this->vendorAdminWith('settings.view'), 'vendor')
            ->putJson(route('partner.profile.location.update'), ['latitude' => '23.5', 'longitude' => '58.3'])
            ->assertForbidden();

        $this->assertSame($before, $this->scenario->vendor->fresh()->business_address_id);
    }

    public function test_admin_saves_and_clears_a_vendor_location_and_it_is_logged(): void
    {
        $vendor = $this->scenario->vendor;
        $url = route('admin.vendors.location.update', $vendor);

        $this->actingAs($this->adminWith('vendors.view'), 'admin')
            ->putJson($url, ['latitude' => '23.5', 'longitude' => '58.3'])
            ->assertForbidden();

        $editor = $this->adminWith('vendors.view', 'vendors.edit');
        $this->actingAs($editor, 'admin')
            ->putJson($url, ['store_address' => 'Ruwi', 'latitude' => '23.6', 'longitude' => '58.5'])
            ->assertOk()
            ->assertJsonPath('data.latitude', 23.6);

        $log = Activity::where('subject_id', $vendor->id)->where('event', 'store_location_updated')->sole();
        $this->assertSame($editor->id, $log->causer_id);
        $this->assertEquals(58.5, $log->properties['attributes']['longitude']);

        $this->actingAs($editor, 'admin')
            ->putJson($url, ['store_address' => 'Ruwi', 'latitude' => '', 'longitude' => ''])
            ->assertOk()
            ->assertJsonPath('data.has_coordinates', false);
        $this->assertNull($vendor->fresh()->businessAddress->latitude);
    }

    public function test_vendor_api_reads_and_updates_the_location(): void
    {
        $this->scenario->vendor->forceFill(['onboarding_completed_at' => now()])->save();
        $vendorAdmin = $this->vendorAdminWith('settings.view', 'settings.edit');

        $this->actingAs($vendorAdmin, 'vendor')
            ->putJson('/api/vendor/v1/profile/location', ['latitude' => '23.61', 'longitude' => '58.54'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($vendorAdmin, 'vendor')
            ->getJson('/api/vendor/v1/profile/location')
            ->assertOk()
            ->assertJsonPath('data.latitude', 23.61)
            ->assertJsonPath('data.longitude', 58.54)
            ->assertJsonPath('data.has_coordinates', true);

        $this->actingAs($vendorAdmin, 'vendor')
            ->putJson('/api/vendor/v1/profile/location', ['latitude' => '23.61'])
            ->assertUnprocessable();

        $this->actingAs($this->vendorAdminWith('settings.view'), 'vendor')
            ->putJson('/api/vendor/v1/profile/location', ['latitude' => '1', 'longitude' => '1'])
            ->assertForbidden();
    }
}
