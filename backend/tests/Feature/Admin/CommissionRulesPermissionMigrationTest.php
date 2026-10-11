<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommissionRulesPermissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        (require database_path('migrations/2026_10_11_000001_add_commission_rules_manage_permission.php'))->up();
    }

    public function test_grants_permission_only_to_roles_that_hold_categories_edit_and_is_idempotent(): void
    {
        Permission::firstOrCreate(['name' => 'categories.edit', 'guard_name' => 'admin']);
        $withEdit = Role::create(['name' => 'catalog_lead', 'guard_name' => 'admin']);
        $withEdit->givePermissionTo('categories.edit');
        $without = Role::create(['name' => 'support_only', 'guard_name' => 'admin']);

        $this->runMigration();
        $this->runMigration();

        $this->assertSame(1, Permission::where('name', 'commission_rules.manage')->where('guard_name', 'admin')->count());
        $this->assertTrue($withEdit->fresh()->hasPermissionTo('commission_rules.manage'));
        $this->assertFalse($without->fresh()->hasPermissionTo('commission_rules.manage'));
    }
}
