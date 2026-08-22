<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
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
            ->withCount('permissions')
            ->select(['roles.id', 'roles.name', 'roles.guard_name', 'roles.created_at']);

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
        if (!$authAdmin->hasPermissionTo('roles.create')) {
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
        if (!$authAdmin->hasPermissionTo('roles.create')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

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
        if (!$authAdmin->hasPermissionTo('roles.edit')) {
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
        if (!$authAdmin->hasPermissionTo('roles.edit')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($role->name === 'super_admin') {
            return response()->json(['message' => 'Cannot edit the super_admin role.'], 422);
        }

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
        if (!$authAdmin->hasPermissionTo('roles.delete')) {
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

    private function groupedPermissions(): array
    {
        $groupLabels = [
            'products' => 'Catalog — Products',
            'categories' => 'Catalog — Categories',
            'brands' => 'Catalog — Brands',
            'attributes' => 'Catalog — Attributes',
            'vendors' => 'Vendors',
            'orders' => 'Orders',
            'payouts' => 'Finance — Payouts',
            'commissions' => 'Finance — Commissions',
            'ledger' => 'Finance — Ledger',
            'banners' => 'Marketing — Banners',
            'flash_sales' => 'Marketing — Flash Sales',
            'coupons' => 'Marketing — Coupons',
            'ad_campaigns' => 'Marketing — Ad Campaigns',
            'page_builder' => 'Marketing — Page Builder',
            'app_contexts' => 'Marketing — App Contexts',
            'customers' => 'Customers',
            'tickets' => 'Support — Tickets',
            'reviews' => 'Support — Reviews',
            'disputes' => 'Support — Disputes',
            'countries' => 'System — Countries',
            'settings' => 'System — Settings',
            'admins' => 'System — Admins',
            'roles' => 'System — Roles',
            'activity_log' => 'System — Activity Log',
        ];

        $permissions = Permission::where('guard_name', 'admin')
            ->orderBy('name')
            ->get(['id', 'name']);

        $grouped = [];
        foreach ($permissions as $perm) {
            $prefix = explode('.', $perm->name)[0];
            $label = $groupLabels[$prefix] ?? ucfirst($prefix);
            $grouped[$prefix] ??= ['label' => $label, 'key' => $prefix, 'permissions' => []];
            $grouped[$prefix]['permissions'][] = [
                'name' => $perm->name,
                'label' => $this->formatPermissionLabel($perm->name),
            ];
        }

        return array_values($grouped);
    }

    private function formatPermissionLabel(string $permission): string
    {
        $parts = explode('.', $permission, 2);
        $action = $parts[1] ?? $parts[0];
        return ucwords(str_replace(['_', '.'], ' ', $action));
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
