<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use App\Models\TravelAgencyMember;
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
    use ResolvesTravelAgency;

    private const GUARD = 'travel_agency';

    private const SYSTEM_ROLES = ['travel_agency_owner', 'travel_agency_manager', 'travel_agency_staff'];

    private function groupLabels(): array
    {
        return [
            'packages' => __('travel.roles.groups.packages'),
            'bookings' => __('travel.roles.groups.bookings'),
            'inquiries' => __('travel.roles.groups.inquiries'),
            'campaigns' => __('travel.roles.groups.campaigns'),
            'profile' => __('travel.roles.groups.profile'),
            'team' => __('travel.roles.groups.team'),
            'roles' => __('travel.roles.groups.roles'),
            'bank_accounts' => __('travel.roles.groups.bank_accounts'),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Custom roles are stored as "{travel_agency_id}::{slug}" so the physical
     * `name` column stays globally unique while the UI only ever shows `label`.
     */
    private function buildRoleName(string $slug): string
    {
        return $this->agencyId() . '::' . $slug;
    }

    /**
     * Only this agency's own custom roles may be edited/deleted — never
     * another agency's roles and never the shared system roles.
     */
    private function guardOwnCustomRole(Role $role): void
    {
        if ($role->guard_name !== self::GUARD || $role->travel_agency_id !== $this->agencyId()) {
            abort(404);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $roles = Role::where('guard_name', self::GUARD)
            ->where(function ($query) {
                $query->whereNull('travel_agency_id')
                    ->orWhere('travel_agency_id', $this->agencyId());
            })
            ->withCount('permissions')
            ->orderByRaw('travel_agency_id is null desc')
            ->orderBy('name')
            ->get();

        $memberCounts = DB::table('model_has_roles')
            ->where('model_type', TravelAgencyMember::class)
            ->groupBy('role_id')
            ->pluck(DB::raw('count(*)'), 'role_id');

        return view('travel-agency.roles.index', [
            'roles' => $roles,
            'memberCounts' => $memberCounts,
            'systemRoles' => self::SYSTEM_ROLES,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('travel-agency.roles.create', [
            'role' => null,
            'allPermissions' => $this->groupedPermissions(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                function ($attribute, $value, $fail) {
                    $exists = Role::where('guard_name', self::GUARD)
                        ->where('name', $this->buildRoleName($value))
                        ->exists();
                    if ($exists) {
                        $fail(__('travel.roles.name_exists'));
                    }
                },
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $permissions = Permission::where('guard_name', self::GUARD)
            ->whereIn('name', $request->input('permissions', []))
            ->get();

        DB::beginTransaction();
        try {
            $role = Role::create([
                'name' => $this->buildRoleName($request->name),
                'guard_name' => self::GUARD,
                'travel_agency_id' => $this->agencyId(),
                'label' => $request->name,
            ]);
            $role->syncPermissions($permissions);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('TravelAgencyPortal\\RoleController@store failed', ['error' => $e->getMessage()]);
            if ($request->wantsJson()) {
                return response()->json(['message' => __('travel.roles.create_failed')], 500);
            }
            return back()->withInput()->withErrors(['error' => __('travel.roles.create_failed')]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'redirect' => route('travel-agency.roles.edit', $role->id)]);
        }

        return redirect()->route('travel-agency.roles.edit', $role->id)
            ->with('success', __('travel.roles.created'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(Role $role): View
    {
        $this->guardOwnCustomRole($role);

        return view('travel-agency.roles.edit', [
            'role' => $role->load('permissions'),
            'allPermissions' => $this->groupedPermissions(),
        ]);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->guardOwnCustomRole($role);

        $request->validate([
            'name' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                function ($attribute, $value, $fail) use ($role) {
                    $exists = Role::where('guard_name', self::GUARD)
                        ->where('name', $this->buildRoleName($value))
                        ->where('id', '!=', $role->id)
                        ->exists();
                    if ($exists) {
                        $fail(__('travel.roles.name_exists'));
                    }
                },
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $permissions = Permission::where('guard_name', self::GUARD)
            ->whereIn('name', $request->input('permissions', []))
            ->get();

        DB::beginTransaction();
        try {
            $role->update(['name' => $this->buildRoleName($request->name), 'label' => $request->name]);
            $role->syncPermissions($permissions);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('TravelAgencyPortal\\RoleController@update failed', ['role' => $role->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => __('travel.roles.update_failed')], 500);
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(Role $role): JsonResponse
    {
        $this->guardOwnCustomRole($role);

        $memberCount = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', TravelAgencyMember::class)
            ->count();

        if ($memberCount > 0) {
            return response()->json([
                'message' => __('travel.roles.delete_forbidden_members', [
                    'role'  => $role->label,
                    'count' => $memberCount,
                ]),
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
        $permissions = Permission::where('guard_name', self::GUARD)
            ->orderBy('name')
            ->get(['id', 'name']);

        $grouped = [];
        foreach ($permissions as $perm) {
            $prefix = explode('.', $perm->name)[0];
            $label = $this->groupLabels()[$prefix] ?? ucfirst($prefix);
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
