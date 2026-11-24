<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\ScopeAdminToAssignedVendor;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Client test report, point 17: a super admin whose role carried the `vendors.assigned_only`
 * restriction saw every vendor as a "restricted profile", hiding the acquisition-agent card.
 */
class AdminVendorScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{is_scoped_admin: mixed, scoped_vendor_ids: mixed}
     */
    private function scopeFor(Admin $admin): array
    {
        Auth::guard('admin')->setUser($admin);
        $request = Request::create('/vendors');

        (new ScopeAdminToAssignedVendor)->handle($request, fn () => new Response);

        return [
            'is_scoped_admin' => $request->attributes->get('is_scoped_admin'),
            'scoped_vendor_ids' => $request->attributes->get('scoped_vendor_ids'),
        ];
    }

    private function roleWithRestriction(string $roleName): Role
    {
        $permission = Permission::findOrCreate('vendors.assigned_only', 'admin');
        $role = Role::findOrCreate($roleName, 'admin');
        $role->givePermissionTo($permission);

        return $role;
    }

    public function test_super_admin_is_never_scoped_even_if_the_role_holds_the_restriction(): void
    {
        $admin = Admin::factory()->create();
        $admin->assignRole($this->roleWithRestriction('super_admin'));

        $scope = $this->scopeFor($admin);

        $this->assertFalse($scope['is_scoped_admin']);
        $this->assertNull($scope['scoped_vendor_ids']);
    }

    public function test_restricted_staff_admin_is_still_scoped(): void
    {
        $admin = Admin::factory()->create();
        $admin->assignRole($this->roleWithRestriction('operations_admin'));

        $scope = $this->scopeFor($admin);

        $this->assertTrue($scope['is_scoped_admin']);
        $this->assertSame([], $scope['scoped_vendor_ids']);
    }

    public function test_migration_removes_the_restriction_from_the_super_admin_role(): void
    {
        $role = $this->roleWithRestriction('super_admin');

        $migration = require database_path('migrations/2026_11_24_210728_revoke_vendors_assigned_only_from_super_admin_role.php');
        $migration->up();

        $this->assertFalse($role->fresh()->hasPermissionTo('vendors.assigned_only', 'admin'));
    }
}
