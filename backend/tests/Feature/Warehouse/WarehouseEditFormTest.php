<?php

namespace Tests\Feature\Warehouse;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Client test report, defect #1: the warehouse edit form opened with an empty owner (only
 * vendors with a non-existent "approved" status were listed) and a blank default limit was
 * rejected, so GPS coordinates could never be saved on a seller warehouse.
 */
class WarehouseEditFormTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
    }

    private function admin(): Admin
    {
        foreach (['warehouses.view', 'warehouses.manage', 'vendors.assigned_only'] as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['warehouses.view', 'warehouses.manage']);

        return $admin;
    }

    public function test_edit_form_preselects_the_active_vendor_owner(): void
    {
        $warehouse = $this->s->vendorWarehouse;

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.warehouses.edit', $warehouse->id))
            ->assertOk()
            ->assertViewHas('vendors', fn ($vendors) => $vendors->has($this->s->vendor->id));
    }

    public function test_edit_form_keeps_the_current_owner_even_when_not_active(): void
    {
        $this->s->vendor->updateQuietly(['global_status' => 'suspended']);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.warehouses.edit', $this->s->vendorWarehouse->id))
            ->assertOk()
            ->assertViewHas('vendors', fn ($vendors) => $vendors->has($this->s->vendor->id));
    }

    public function test_seller_warehouse_saves_coordinates_with_the_form_defaults(): void
    {
        $warehouse = $this->s->vendorWarehouse;

        // Exactly what the browser posts: the hidden default-limit toggle still says "quantity".
        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.warehouses.update', $warehouse->id), [
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'type' => 'seller_owned',
                'country_id' => $warehouse->country_id,
                'owner_vendor_id' => $this->s->vendor->id,
                'latitude' => '29.3759',
                'longitude' => '47.9774',
                'default_limit_type' => 'quantity',
                'default_max_quantity' => '',
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.warehouses.show', $warehouse->id));

        $warehouse->refresh();
        $this->assertEqualsWithDelta(29.3759, (float) $warehouse->latitude, 0.0001);
        $this->assertEqualsWithDelta(47.9774, (float) $warehouse->longitude, 0.0001);
    }

    public function test_platform_warehouse_with_blank_default_limit_means_no_limit(): void
    {
        $warehouse = $this->s->platformWarehouse;

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.warehouses.update', $warehouse->id), [
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'type' => 'platform_fbn',
                'country_id' => $warehouse->country_id,
                'default_limit_type' => 'quantity',
                'default_max_quantity' => '',
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        $warehouse->refresh();
        $this->assertNull($warehouse->default_limit_type);
        $this->assertNull($warehouse->default_max_quantity);
    }

    public function test_platform_warehouse_still_saves_a_filled_default_limit(): void
    {
        $warehouse = $this->s->platformWarehouse;

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.warehouses.update', $warehouse->id), [
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'type' => 'platform_fbn',
                'country_id' => $warehouse->country_id,
                'default_limit_type' => 'quantity',
                'default_max_quantity' => '500',
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(500, (int) $warehouse->fresh()->default_max_quantity);
    }
}
