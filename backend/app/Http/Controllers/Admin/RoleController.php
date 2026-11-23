<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\PermissionLabels;
use App\Support\PortalPermissions;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    use HasDataTable;

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('admin.roles.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Roles & Permissions'],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $columns = $this->columnDefinitions();

        // Pre-fetch admin counts per role
        $adminCounts = DB::table('model_has_roles')
            ->where('model_type', Admin::class)
            ->groupBy('role_id')
            ->pluck(DB::raw('count(*)'), 'role_id');

        $query = Role::query()
            ->where('guard_name', 'admin')
            ->select(['roles.id', 'roles.name', 'roles.guard_name', 'roles.created_at'])
            ->withCount('permissions');

        return $this->dataTableResponse($request, $query, $columns, function ($row) use ($adminCounts) {
            return [
                'id' => $row->id,
                'name' => $row->name,
                'guard_name' => $row->guard_name,
                'admin_count' => (int) ($adminCounts[$row->id] ?? 0),
                'permissions_count' => (int) $row->permissions_count,
                'created_at' => $row->created_at,
                'is_super_admin' => $row->name === 'super_admin',
                'edit_url' => route('admin.roles.edit', $row->id),
                'delete_url' => route('admin.roles.destroy', $row->id),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        $authAdmin = auth('admin')->user();
        if (! $authAdmin->hasPermissionTo('roles.create')) {
            abort(403);
        }

        return view('admin.roles.create', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Roles', 'url' => route('admin.roles.index')],
                ['label' => 'New Role'],
            ],
            'allPermissions' => $this->groupedPermissions(),
            'role' => null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $authAdmin = auth('admin')->user();
        if (! $authAdmin->hasPermissionTo('roles.create')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->merge(['name' => $this->normalizeRoleName($request->input('name'))]);

        $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create(['name' => $request->name, 'guard_name' => 'admin']);
            if ($request->filled('permissions')) {
                $role->syncPermissions($request->permissions);
            }
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('RoleController@store failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to create role.'], 500);
        }

        return response()->json(['success' => true, 'redirect' => route('admin.roles.edit', $role->id)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(Role $role): View
    {
        $authAdmin = auth('admin')->user();
        if (! $authAdmin->hasPermissionTo('roles.edit')) {
            abort(403);
        }

        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'allPermissions' => $this->groupedPermissions(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Roles', 'url' => route('admin.roles.index')],
                ['label' => $role->name],
            ],
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $authAdmin = auth('admin')->user();
        if (! $authAdmin->hasPermissionTo('roles.edit')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($role->name === 'super_admin') {
            return response()->json(['message' => 'Cannot edit the super_admin role.'], 422);
        }

        $request->merge(['name' => $this->normalizeRoleName($request->input('name'))]);

        $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', "unique:roles,name,{$role->id}"],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        DB::beginTransaction();
        try {
            $role->update(['name' => $request->name]);
            $role->syncPermissions($request->permissions ?? []);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('RoleController@update failed', ['role' => $role->id, 'error' => $e->getMessage()]);

            return response()->json(['message' => 'Failed to update role.'], 500);
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(Role $role): JsonResponse
    {
        $authAdmin = auth('admin')->user();
        if (! $authAdmin->hasPermissionTo('roles.delete')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($role->name === 'super_admin') {
            return response()->json(['message' => 'Cannot delete the super_admin role.'], 422);
        }

        $adminCount = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', Admin::class)
            ->count();

        if ($adminCount > 0) {
            return response()->json([
                'message' => "Cannot delete role \"{$role->name}\": {$adminCount} admin(s) are assigned to it.",
            ], 422);
        }

        $role->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // All permissions (for role form)
    // ─────────────────────────────────────────────────────────────────────────

    public function permissions(): JsonResponse
    {
        return response()->json(['data' => $this->groupedPermissions()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Turn a human-typed role name ("portal Editor") into the stored slug ("portal_editor").
     */
    private function normalizeRoleName(mixed $name): mixed
    {
        if (! is_string($name)) {
            return $name;
        }

        return trim(preg_replace('/[\s\-]+/', '_', mb_strtolower(trim($name))), '_');
    }

    private function groupedPermissions(): array
    {
        $permissions = Permission::where('guard_name', 'admin')
            ->orderBy('name')
            ->get(['id', 'name']);

        $grouped = [];
        foreach ($permissions as $perm) {
            $prefix = explode('.', $perm->name)[0];
            $grouped[$prefix] ??= [
                'label' => PermissionLabels::group($prefix),
                'label_en' => PermissionLabels::group($prefix, 'en'),
                'label_ar' => PermissionLabels::group($prefix, 'ar'),
                'key' => $prefix,
                'permissions' => [],
            ];
            $grouped[$prefix]['permissions'][] = [
                'name' => $perm->name,
                'label' => PermissionLabels::label($perm->name),
                'search_text' => implode(' ', [
                    $perm->name,
                    PermissionLabels::label($perm->name, 'en'),
                    PermissionLabels::label($perm->name, 'ar'),
                ]),
                'description' => PortalPermissions::description($perm->name),
            ];
        }

        // Portal permissions: show them in a logical order rather than alphabetically.
        if (isset($grouped['portal_content'])) {
            $order = array_flip(PortalPermissions::CONTENT);
            usort($grouped['portal_content']['permissions'], fn ($a, $b) => ($order[$a['name']] ?? 99) <=> ($order[$b['name']] ?? 99));
            $grouped['portal_content']['description'] = __('admin.portal_permissions.group_description');
        }

        $grouped = array_values($grouped);
        usort($grouped, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $grouped;
    }

    private function columnDefinitions(): array
    {
        return [
            ['title' => 'Role Name', 'data' => 'name', 'name' => 'name', 'orderable_column' => 'roles.name', 'searchable_columns' => ['roles.name']],
            ['title' => 'Guard', 'data' => 'guard_name', 'name' => 'guard_name', 'orderable_column' => 'roles.guard_name', 'searchable' => false],
            ['title' => 'Admins', 'data' => 'admin_count', 'name' => 'admin_count', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
            ['title' => 'Permissions', 'data' => 'permissions_count', 'name' => 'permissions_count', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
            ['title' => 'Created', 'data' => 'created_at', 'name' => 'created_at', 'orderable_column' => 'roles.created_at', 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
        ];
    }
}
