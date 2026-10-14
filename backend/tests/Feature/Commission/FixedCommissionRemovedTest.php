<?php

namespace Tests\Feature\Commission;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** The global "Fixed Commission" (modal, route, setting, country amount) no longer exists. */
class FixedCommissionRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_list_has_no_fixed_commission_button_or_modal(): void
    {
        $this->withoutVite();
        foreach (['categories.view', 'categories.edit', 'vendors.assigned_only'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['categories.view', 'categories.edit']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertDontSee('fixed-commission', false);
    }

    public function test_route_and_country_column_are_gone(): void
    {
        $this->assertFalse(Route::has('admin.categories.fixed-commission.update'));
        $this->assertFalse(Schema::hasColumn('countries', 'fixed_commission_amount'));
    }
}
