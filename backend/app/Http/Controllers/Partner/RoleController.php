<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\VendorAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    private const PROTECTED_ROLES = ['vendor_owner', 'vendor_manager', 'vendor_staff'];

    private const GROUP_LABELS = [
        'listings' => 'Listings',
        'orders' => 'Orders',
        'returns' => 'Returns',
        'disputes' => 'Disputes',
        'finance' => 'Finance',
        'campaigns' => 'Campaigns',
        'promotions' => 'Promotions',
        'products' => 'Products',
        'reviews' => 'Reviews',
        'customers' => 'Customers',
        'team' => 'Team',
        'roles' => 'Roles',
        'settings' => 'Settings',
        'documents' => 'Documents',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $roles = Role::where('guard_name', 'vendor')
            ->withCount('permissions')
            ->orderBy('name')
            ->get();

        $memberCounts = DB::table('model_has_roles')
            ->where('model_type', VendorAdmin::class)
            ->groupBy('role_id')
            ->pluck(DB::raw('count(*)'), 'role_id');

        return view('partner.roles.index', [
            'roles' => $roles,
            'memberCounts' => $memberCounts,
            'protectedRoles' => self::PROTECTED_ROLES,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('partner.roles.create', [
            'role' => null,
            'allPermissions' => $this->groupedPermissions(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $permissions = Permission::where('guard_name', 'vendor')
            ->whereIn('name', $request->input('permissions', []))
            ->get();

        DB::beginTransaction();
        try {
            $role = Role::create(['name' => $request->name, 'guard_name' => 'vendor']);
            $role->syncPermissions($permissions);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Partner\\RoleController@store failed', ['error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['message' => __('partner.roles.messages.create_failed')], 500);
            }
            return back()->withInput()->withErrors(['error' => __('partner.roles.messages.create_failed')]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'redirect' => route('partner.roles.edit', $role->id)]);
        }

        return redirect()->route('partner.roles.edit', $role->id)
            ->with('success', __('partner.roles.messages.created'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(Role $role): View
    {
        $this->guardVendorRole($role);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            abort(403, __('partner.roles.messages.default_cannot_edit'));
        }

        return view('partner.roles.edit', [
            'role' => $role->load('permissions'),
            'allPermissions' => $this->groupedPermissions(),
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->guardVendorRole($role);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return response()->json(['message' => __('partner.roles.messages.default_cannot_edit')], 422);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', "unique:roles,name,{$role->id}"],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $permissions = Permission::where('guard_name', 'vendor')
            ->whereIn('name', $request->input('permissions', []))
            ->get();

        DB::beginTransaction();
        try {
            $role->update(['name' => $request->name]);
            $role->syncPermissions($permissions);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Partner\\RoleController@update failed', ['role' => $role->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => __('partner.roles.messages.update_failed')], 500);
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(Role $role): JsonResponse
    {
        $this->guardVendorRole($role);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return response()->json(['message' => __('partner.roles.messages.default_cannot_delete')], 422);
        }

        $memberCount = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', VendorAdmin::class)
            ->count();

        if ($memberCount > 0) {
            return response()->json([
                'message' => "Cannot delete role \"{$role->name}\": {$memberCount} team member(s) are assigned to it.",
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

    private function guardVendorRole(Role $role): void
    {
        if ($role->guard_name !== 'vendor') {
            abort(404);
        }
    }

    private function groupedPermissions(): array
    {
        $permissions = Permission::where('guard_name', 'vendor')
            ->orderBy('name')
            ->get(['id', 'name']);

        $grouped = [];
        foreach ($permissions as $perm) {
            $prefix = explode('.', $perm->name)[0];
            $label = self::GROUP_LABELS[$prefix] ?? ucfirst($prefix);
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
}
