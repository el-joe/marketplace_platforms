<?php

use App\Models\Admin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Granular portal-content permissions (guard `admin`).
 *
 * Creates portal_content.{view,create,edit,delete,publish,manage} and portal_analytics.view and
 * keeps everybody's current access:
 *   - portal_content.view holders keep view (nothing to add).
 *   - portal_content.edit holders could, before this change, save text, toggle "Active" and
 *     upload / reset images, so they also receive create + publish + delete.
 *   - settings.edit holders could change the /join option toggles, so they receive
 *     portal_content.manage (which now guards those toggles).
 *   - super_admin receives everything.
 * The same equivalence is applied to permissions granted directly to an individual admin.
 * Additive only: never removes a permission from anyone.
 */
return new class extends Migration
{
    // Names are inlined on purpose: a migration must not break if app classes change later.
    private const GUARD = 'admin';

    private const EDIT = 'portal_content.edit';

    private const CREATE = 'portal_content.create';

    private const DELETE = 'portal_content.delete';

    private const PUBLISH = 'portal_content.publish';

    private const MANAGE = 'portal_content.manage';

    private const ANALYTICS_VIEW = 'portal_analytics.view';

    private const ALL = [
        'portal_content.view',
        'portal_content.create',
        'portal_content.edit',
        'portal_content.delete',
        'portal_content.publish',
        'portal_content.manage',
        'portal_analytics.view',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ALL as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }

        $this->grantToRolesHolding(self::EDIT, [
            self::CREATE,
            self::PUBLISH,
            self::DELETE,
        ]);
        $this->grantToRolesHolding('settings.edit', [self::MANAGE]);

        $this->grantDirectlyToHolders(self::EDIT, [
            self::CREATE,
            self::PUBLISH,
            self::DELETE,
        ]);
        $this->grantDirectlyToHolders('settings.edit', [self::MANAGE]);

        $superAdmin = Role::where('name', 'super_admin')->where('guard_name', self::GUARD)->first();
        $superAdmin?->givePermissionTo(self::ALL);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Only the permissions introduced here are removed; portal_content.view/edit predate it.
        Permission::where('guard_name', self::GUARD)
            ->whereIn('name', [
                self::CREATE,
                self::DELETE,
                self::PUBLISH,
                self::MANAGE,
                self::ANALYTICS_VIEW,
            ])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $grant
     */
    private function grantToRolesHolding(string $held, array $grant): void
    {
        Role::where('guard_name', self::GUARD)
            ->whereHas('permissions', fn ($q) => $q->where('name', $held)->where('guard_name', self::GUARD))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($grant));
    }

    /**
     * Copy direct (non-role) admin grants of $held to the new permissions.
     *
     * @param  list<string>  $grant
     */
    private function grantDirectlyToHolders(string $held, array $grant): void
    {
        $tables = config('permission.table_names');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $heldId = Permission::where('name', $held)->where('guard_name', self::GUARD)->value('id');

        if (! $heldId) {
            return;
        }

        $holders = DB::table($tables['model_has_permissions'])
            ->where('permission_id', $heldId)
            ->where('model_type', (new Admin)->getMorphClass())
            ->pluck($morphKey);

        $permissionIds = Permission::whereIn('name', $grant)->where('guard_name', self::GUARD)->pluck('id');

        foreach ($holders as $holder) {
            foreach ($permissionIds as $permissionId) {
                DB::table($tables['model_has_permissions'])->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'model_type' => (new Admin)->getMorphClass(),
                    $morphKey => $holder,
                ]);
            }
        }
    }
};
