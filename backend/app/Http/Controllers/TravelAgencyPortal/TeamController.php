<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Contracts\TravelAgencyAuthUser;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use App\Models\TravelAgencyMember;
use App\Notifications\TravelAgencyMemberPasswordReset;
use App\Notifications\TravelAgencyMemberWelcome;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamController extends Controller
{
    use HasDataTable;
    use ResolvesTravelAgency;
    use HasExport;

    private const GUARD = 'travel_agency';

    private const SYSTEM_ROLES = ['travel_agency_owner', 'travel_agency_manager', 'travel_agency_staff'];

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * The authenticated principal — either the TravelAgency itself (owner
     * logging in directly) or a TravelAgencyMember.
     */
    private function authUser(): TravelAgencyAuthUser
    {
        return Auth::guard(self::GUARD)->user();
    }

    /**
     * Roles assignable within this agency: the shared system roles plus any
     * custom roles owned by this agency (scoped via `roles.travel_agency_id`).
     */
    private function scopedRoles()
    {
        $agencyId = $this->agencyId();

        return Role::where('guard_name', self::GUARD)
            ->where(function ($query) use ($agencyId) {
                $query->whereIn('name', self::SYSTEM_ROLES)
                    ->orWhere('travel_agency_id', $agencyId);
            });
    }

    /**
     * A role is usable by this agency if it's a global/system role or one of
     * this agency's own custom roles — never another agency's custom role.
     */
    private function roleBelongsToAgency(Role $role): bool
    {
        return $role->travel_agency_id === null || $role->travel_agency_id === $this->agencyId();
    }

    private function authorizeMemberAccess(TravelAgencyMember $member): void
    {
        if ($member->travel_agency_id !== $this->agencyId()) {
            abort(403);
        }
    }

    private function forgetSidebarCache(): void
    {
        Cache::forget('ta_sidebar_' . $this->agencyId());
    }

    private function roleDisplayLabel(?Role $role): ?string
    {
        if (!$role) {
            return null;
        }

        if ($role->label) {
            return $role->label;
        }

        return ucwords(str_replace(['travel_agency_', '_'], ['', ' '], $role->name));
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function index(): View
    {
        // Resolving the agency id also validates the auth guard is set up
        // correctly for this request; the id itself isn't needed by the view
        // since all data is fetched client-side via the datatable() endpoint.
        $this->agencyId();

        $user = $this->authUser();
        $canInvite = $user->can('team.invite');
        $canManage = $user->can('team.manage');

        return view('travel-agency.team.index', compact('canInvite', 'canManage'));
    }

    private function filteredMembersQuery(Request $request)
    {
        $query = TravelAgencyMember::query()
            ->where('travel_agency_id', $this->agencyId())
            ->with('roles');

        if ($request->boolean('show_deleted')) {
            $query->withTrashed();
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->query('is_active') !== null && $request->query('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $query;
    }

    public function export(Request $request): Response|StreamedResponse
    {
        $members = $this->filteredMembersQuery($request)->get();

        $headers = [
            __('travel.team.export.name'),
            __('travel.team.export.email'),
            __('travel.team.export.role'),
            __('travel.team.export.active'),
            __('travel.team.export.last_login'),
        ];

        $rows = $members->map(fn (TravelAgencyMember $member) => [
            $member->name,
            $member->email,
            $this->roleDisplayLabel($member->roles->first()) ?? '—',
            $member->is_active ? __('travel.team.export.yes') : __('travel.team.export.no'),
            $member->last_login_at?->toDateTimeString() ?? '',
        ]);

        $filename = 'team-' . now()->toDateString();
        $format = $request->input('format', 'csv');

        return match ($format) {
            'excel' => $this->exportExcel($filename, $headers, $rows),
            'word'  => $this->exportWord($filename, __('travel.team.export.sheet_title'), $rows),
            'csv'   => $this->exportCsv($filename, $headers, $rows),
            default => abort(400, __('travel.export.invalid_format')),
        };
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = $this->filteredMembersQuery($request);

        $columns = [
            ['orderable_column' => 'name', 'searchable_columns' => ['name', 'email']],
            ['orderable_column' => 'email', 'searchable_columns' => ['email']],
            ['orderable_column' => 'phone'],
            [],
            ['orderable_column' => 'is_active'],
            ['orderable_column' => 'last_login_at'],
            [],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (TravelAgencyMember $member) {
            $role = $member->roles->first();

            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'role' => $this->roleDisplayLabel($role) ?? '—',
                'status' => $member->is_active ? __('travel.team.active') : __('travel.team.inactive'),
                'is_active' => (bool) $member->is_active,
                'last_login_at' => $member->last_login_at?->diffForHumans() ?? '—',
                'trashed' => (bool) $member->trashed(),
                'is_self' => $member->id === $this->authUser()->getAuthIdentifier(),
                'edit_url' => route('travel-agency.team.edit', $member->id),
                'toggle_status_url' => route('travel-agency.team.toggle-status', $member->id),
                'force_password_reset_url' => route('travel-agency.team.force-password-reset', $member->id),
                'destroy_url' => route('travel-agency.team.destroy', $member->id),
                'restore_url' => route('travel-agency.team.restore', $member->id),
            ];
        });
    }

    public function create(): View
    {
        $roles = $this->scopedRoles()->get();

        return view('travel-agency.team.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:travel_agency_members,email',
            'phone' => 'nullable|string|max:30',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($validated['role_id']);
        if (!$this->roleBelongsToAgency($role)) {
            abort(403);
        }

        $agencyId = $this->agencyId();
        $plainPassword = $validated['password'];

        $member = DB::transaction(function () use ($validated, $role, $agencyId, $plainPassword) {
            $member = TravelAgencyMember::create([
                'travel_agency_id' => $agencyId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($plainPassword),
                'role' => $role->name,
                'is_owner' => false,
                'is_active' => true,
            ]);

            $member->assignRole($role);

            return $member;
        });

        $member->notify(new TravelAgencyMemberWelcome($member, $plainPassword));

        $this->forgetSidebarCache();

        return redirect()->route('travel-agency.team.index')
            ->with('success', __('travel.team.member_created'));
    }

    public function edit(TravelAgencyMember $member): View
    {
        $this->authorizeMemberAccess($member);

        $roles = $this->scopedRoles()->get();
        $memberRole = $member->roles->first();

        return view('travel-agency.team.edit', compact('member', 'roles', 'memberRole'));
    }

    public function update(Request $request, TravelAgencyMember $member): RedirectResponse
    {
        $this->authorizeMemberAccess($member);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('travel_agency_members', 'email')->ignore($member->id)],
            'phone' => 'nullable|string|max:30',
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $role = null;
        if (!empty($validated['role_id'])) {
            $role = Role::findOrFail($validated['role_id']);
            if (!$this->roleBelongsToAgency($role)) {
                abort(403);
            }
        }

        DB::transaction(function () use ($member, $validated, $role) {
            $member->name = $validated['name'];
            $member->email = $validated['email'];
            $member->phone = $validated['phone'] ?? null;

            if (!empty($validated['password'])) {
                $member->password = Hash::make($validated['password']);
            }

            $member->save();

            if ($role) {
                $member->syncRoles([$role]);
                $member->update(['role' => $role->name]);
            }
        });

        $this->forgetSidebarCache();

        return redirect()->route('travel-agency.team.index')
            ->with('success', __('travel.team.member_updated'));
    }

    public function toggleStatus(TravelAgencyMember $member): JsonResponse
    {
        $this->authorizeMemberAccess($member);

        $member->update(['is_active' => !$member->is_active]);

        $this->forgetSidebarCache();

        return response()->json([
            'success' => true,
            'is_active' => $member->is_active,
            'message' => $member->is_active ? __('travel.team.activated') : __('travel.team.deactivated'),
        ]);
    }

    public function forcePasswordReset(TravelAgencyMember $member): JsonResponse
    {
        $this->authorizeMemberAccess($member);

        $tempPassword = Str::random(12);

        $member->update(['password' => Hash::make($tempPassword)]);

        $member->notify(new TravelAgencyMemberPasswordReset($member, $tempPassword));

        return response()->json([
            'success' => true,
            'message' => __('travel.team.password_reset_sent'),
        ]);
    }

    public function destroy(TravelAgencyMember $member): RedirectResponse
    {
        $this->requireOwner();
        $this->authorizeMemberAccess($member);

        if ($member->id === $this->authUser()->getAuthIdentifier()) {
            return back()->with('error', __('travel.team.cannot_delete_self'));
        }

        $member->delete();

        $this->forgetSidebarCache();

        return redirect()->route('travel-agency.team.index')
            ->with('success', __('travel.team.member_removed'));
    }

    public function restore(string $member): RedirectResponse
    {
        $member = TravelAgencyMember::withTrashed()->findOrFail($member);

        $this->authorizeMemberAccess($member);

        $member->restore();

        return redirect()->route('travel-agency.team.index')
            ->with('success', __('travel.team.member_restored'));
    }
}
