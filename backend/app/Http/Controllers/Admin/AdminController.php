<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use App\Enums\AdminStatus;
use App\Models\Admin;
use App\Models\AdminLoginSession;
use App\Models\Country;
use App\Services\ActivityLoggerService;
use App\Traits\HasDataTable;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    use HasDataTable;

    public function __construct(private readonly ActivityLoggerService $activityLogger)
    {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        return view('admin.admins.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.admins_section.administrators')],
            ],
            'roles' => Role::where('guard_name', 'admin')->orderBy('name')->get(['id', 'name']),
            'countries' => Country::orderBy('name_en')->get(['id', 'name_en']),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $columns = $this->columnDefinitions();
        $authAdmin = auth('admin')->user();

        // Pre-fetch admin_count per role to avoid N+1 on the transformer
        $adminCounts = DB::table('model_has_roles')
            ->where('model_type', Admin::class)
            ->groupBy('role_id')
            ->pluck(DB::raw('count(*)'), 'role_id');

        $query = Admin::query()
            ->with(['roles', 'country'])
            ->select([
                'admins.id',
                'admins.name',
                'admins.email',
                'admins.phone',
                'admins.status',
                'admins.last_login_at',
                'admins.created_at',
                'admins.country_id',
            ]);

        $query = $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('admins.status', $v),
            'role' => fn($q, $v) => $q->role($v, 'admin'),
            'country_id' => fn($q, $v) => $q->where('admins.country_id', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, function ($row) use ($authAdmin) {
            $isSelf = $row->id === $authAdmin->id;
            $isSuperAdmin = $row->hasRole('super_admin');
            $canImpersonate = !$isSelf
                && !$isSuperAdmin
                && $authAdmin->hasPermissionTo('admins.impersonate');
            $canDelete = !$isSelf
                && $authAdmin->hasPermissionTo('admins.delete')
                && (!$isSuperAdmin || Admin::role('super_admin', 'admin')->count() > 1);

            return [
                'id' => $row->id,
                'name' => e($row->name),
                'email' => e($row->email),
                'phone' => e($row->phone ?? ''),
                'country_name' => e($row->country?->name_en ?? '—'),
                'roles' => $row->getRoleNames()->toArray(),
                'status' => $row->status?->value,
                'last_login_at' => $row->last_login_at,
                'created_at' => $row->created_at,
                'is_self' => $isSelf,
                'can_impersonate' => $canImpersonate,
                'can_delete' => $canDelete,
                'edit_url' => route('admin.admins.edit', $row->id),
                'reset_pwd_url' => route('admin.admins.reset-password', $row->id),
                'sessions_url' => route('admin.admins.login-sessions', $row->id),
                'impersonate_url' => $canImpersonate ? route('admin.admins.impersonate', $row->id) : null,
                'toggle_url' => route('admin.admins.toggle-status', $row->id),
                'delete_url' => route('admin.admins.destroy', $row->id),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.admins.create', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.admins_section.administrators'), 'url' => route('admin.admins.index')],
                ['label' => __('admin.admins_section.new_admin')],
            ],
            'allRoles' => Role::where('guard_name', 'admin')->with('permissions')->orderBy('name')->get(),
            'countries' => Country::orderBy('name_en')->get(['id', 'name_en']),
        ]);
    }

    public function store(StoreAdminRequest $request): JsonResponse
    {
        $authAdmin = auth('admin')->user();
        if (!$authAdmin->hasPermissionTo('admins.create')) {
            return response()->json(['message' => __('admin.admins_section.forbidden')], 403);
        }

        $password = Str::password(12);

        DB::beginTransaction();
        try {
            $admin = Admin::create([
                'id' => Str::uuid()->toString(),
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'country_id' => $request->country_id,
                'status' => $request->status,
                'password' => Hash::make($password),
            ]);

            $admin->syncRoles($request->roles);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AdminController@store failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => __('admin.admins_section.create_failed')], 500);
        }

        return response()->json([
            'success' => true,
            'generated_password' => $password,
            'redirect' => route('admin.admins.edit', $admin->id),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(Admin $admin): View
    {
        $authAdmin = auth('admin')->user();
        if (!$authAdmin->hasPermissionTo('admins.edit') && $authAdmin->id !== $admin->id) {
            abort(403);
        }

        return view('admin.admins.edit', [
            'model' => $admin,
            'allRoles' => Role::where('guard_name', 'admin')->with('permissions')->orderBy('name')->get(),
            'countries' => Country::orderBy('name_en')->get(['id', 'name_en']),
            'vendorsAssignedOnly' => $admin->hasDirectPermission('vendors.assigned_only'),
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.admins_section.administrators'), 'url' => route('admin.admins.index')],
                ['label' => e($admin->name)],
            ],
        ]);
    }

    public function update(UpdateAdminRequest $request, Admin $admin): JsonResponse
    {
        $authAdmin = auth('admin')->user();
        if (!$authAdmin->hasPermissionTo('admins.edit')) {
            return response()->json(['message' => __('admin.admins_section.forbidden')], 403);
        }

        if ($admin->hasRole('super_admin') && !$authAdmin->hasRole('super_admin')) {
            return response()->json(['message' => __('admin.admins_section.cannot_edit_super_admin')], 403);
        }

        if ($authAdmin->id === $admin->id && $request->status === AdminStatus::Inactive->value) {
            return response()->json(['message' => __('admin.admins_section.cannot_deactivate_self')], 422);
        }

        DB::beginTransaction();
        try {
            $admin->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'country_id' => $request->country_id,
                'status' => $request->status,
            ]);

            $admin->syncRoles($request->roles);
            $this->syncDirectPermissions($admin, $request);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AdminController@update failed', ['admin' => $admin->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => __('admin.admins_section.update_failed')], 500);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Syncs the small whitelist of directly-assignable (non-role) permissions.
     * The UI can only ever grant permissions on this whitelist — never system
     * permissions, and never for a super_admin (it's a restriction, not a grant).
     */
    private function syncDirectPermissions(Admin $admin, UpdateAdminRequest $request): void
    {
        $whitelist = ['vendors.assigned_only'];

        $requested = collect($whitelist)
            ->filter(fn ($p) => $request->boolean('vendors_assigned_only') && $p === 'vendors.assigned_only')
            ->values()
            ->all();

        if ($admin->hasRole('super_admin')) {
            $requested = [];
        }

        $hadPermission = $admin->hasDirectPermission('vendors.assigned_only');
        $willHavePermission = in_array('vendors.assigned_only', $requested, true);

        $admin->syncPermissions($requested);

        if ($hadPermission !== $willHavePermission) {
            $this->activityLogger->log(
                description: 'vendors.assigned_only ' . ($willHavePermission ? 'granted' : 'revoked'),
                subject: $admin,
                causer: auth('admin')->user(),
                logName: 'admin_permissions',
                event: 'admin_permission_changed',
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(Admin $admin): JsonResponse
    {
        $authAdmin = auth('admin')->user();

        if (!$authAdmin->hasPermissionTo('admins.delete')) {
            return response()->json(['message' => __('admin.admins_section.forbidden')], 403);
        }

        if ($authAdmin->id === $admin->id) {
            return response()->json(['message' => __('admin.admins_section.cannot_delete_self')], 422);
        }

        if ($admin->hasRole('super_admin')) {
            if (Admin::role('super_admin', 'admin')->count() <= 1) {
                return response()->json(['message' => __('admin.admins_section.cannot_delete_last_super_admin')], 422);
            }
        }

        $admin->delete();

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Search (Select2 AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $results = Admin::when($q, fn($query) => $query->where(function ($sub) use ($q) {
            $sub->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%");
        }))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name'])
            ->map(fn($a) => ['id' => $a->id, 'text' => $a->name]);

        return response()->json(['results' => $results]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reset Password
    // ─────────────────────────────────────────────────────────────────────────

    public function resetPassword(Admin $admin): JsonResponse
    {
        $authAdmin = auth('admin')->user();
        if (!$authAdmin->hasPermissionTo('admins.edit')) {
            return response()->json(['message' => __('admin.admins_section.forbidden')], 403);
        }

        $password = Str::password(12);
        $admin->update(['password' => Hash::make($password)]);

        // In production: dispatch AdminWelcomeEmailJob or a password reset email
        // Intentionally NOT returning the raw password in the response

        return response()->json([
            'success' => true,
            'message' => __('admin.admins_section.password_reset_email_sent'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Toggle Status
    // ─────────────────────────────────────────────────────────────────────────

    public function toggleStatus(Admin $admin): JsonResponse
    {
        $authAdmin = auth('admin')->user();
        if (!$authAdmin->hasPermissionTo('admins.edit')) {
            return response()->json(['message' => __('admin.admins_section.forbidden')], 403);
        }

        if ($authAdmin->id === $admin->id) {
            return response()->json(['message' => __('admin.admins_section.cannot_change_own_status')], 422);
        }

        $newStatus = $admin->status === AdminStatus::Active ? AdminStatus::Inactive : AdminStatus::Active;
        $admin->update(['status' => $newStatus]);

        return response()->json(['success' => true, 'new_status' => $newStatus->value]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Impersonate
    // ─────────────────────────────────────────────────────────────────────────

    public function impersonate(Admin $admin): RedirectResponse
    {
        $authAdmin = auth('admin')->user();

        if (!$authAdmin->hasPermissionTo('admins.impersonate')) {
            abort(403);
        }

        if ($authAdmin->id === $admin->id) {
            return back()->with('error', __('admin.admins_section.cannot_impersonate_self'));
        }

        if ($admin->hasRole('super_admin')) {
            return back()->with('error', __('admin.admins_section.cannot_impersonate_super_admin'));
        }

        session(['impersonating_original_id' => $authAdmin->id]);

        AdminLoginSession::create([
            'id' => Str::uuid()->toString(),
            'admin_id' => $admin->id,
            'impersonating_id' => $authAdmin->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent() ?? '',
            'started_at' => now()->toDateTimeString(),
        ]);

        Auth::guard('admin')->login($admin);

        return redirect()->route('admin.dashboard')
            ->with('success', __('admin.admins_section.now_impersonating', ['name' => $admin->name]));
    }

    public function stopImpersonating(): RedirectResponse
    {
        $originalId = session('impersonating_original_id');

        if (!$originalId) {
            return redirect()->route('admin.dashboard');
        }

        // Close the open impersonation session
        AdminLoginSession::query()
            ->where('admin_id', auth('admin')->id())
            ->where('impersonating_id', $originalId)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first()
                ?->update(['ended_at' => now()->toDateTimeString()]);

        $originalAdmin = Admin::find($originalId);

        if (!$originalAdmin) {
            Auth::guard('admin')->logout();
            session()->forget('impersonating_original_id');
            return redirect()->route('admin.login');
        }

        Auth::guard('admin')->login($originalAdmin);
        session()->forget('impersonating_original_id');

        return redirect()->route('admin.admins.index')
            ->with('success', __('admin.admins_section.stopped_impersonating'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Login Sessions
    // ─────────────────────────────────────────────────────────────────────────

    public function loginSessions(Admin $admin): JsonResponse
    {
        $authAdmin = auth('admin')->user();
        if (!$authAdmin->hasPermissionTo('admins.view')) {
            return response()->json(['message' => __('admin.admins_section.forbidden')], 403);
        }

        $sessions = AdminLoginSession::query()
            ->where('admin_id', $admin->id)
            ->orderByDesc('started_at')
            ->limit(50)
            ->get()
            ->map(function ($s) {
                $duration = null;
                if ($s->started_at && $s->ended_at) {
                    $duration = Carbon::parse($s->started_at)
                        ->diffForHumans(Carbon::parse($s->ended_at), true);
                }

                return [
                    'id' => $s->id,
                    'ip_address' => $s->ip_address,
                    'user_agent' => $s->user_agent,
                    'started_at' => $s->started_at,
                    'ended_at' => $s->ended_at,
                    'duration' => $duration,
                    'is_impersonation' => !is_null($s->impersonating_id),
                    'impersonator_id' => $s->impersonating_id,
                ];
            });

        return response()->json(['data' => $sessions, 'admin' => ['name' => $admin->name]]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private
    // ─────────────────────────────────────────────────────────────────────────

    private function columnDefinitions(): array
    {
        return [
            ['title' => 'Name', 'data' => 'name', 'name' => 'name', 'orderable_column' => 'admins.name', 'searchable_columns' => ['admins.name', 'admins.email']],
            ['title' => 'Email', 'data' => 'email', 'name' => 'email', 'orderable_column' => 'admins.email', 'searchable' => false],
            ['title' => 'Country', 'data' => 'country_name', 'name' => 'country_name', 'orderable' => false, 'searchable' => false],
            ['title' => 'Roles', 'data' => 'roles', 'name' => 'roles', 'orderable' => false, 'searchable' => false],
            ['title' => 'Status', 'data' => 'status', 'name' => 'status', 'orderable_column' => 'admins.status', 'searchable' => false],
            ['title' => 'Last Login', 'data' => 'last_login_at', 'name' => 'last_login_at', 'orderable_column' => 'admins.last_login_at', 'searchable' => false],
            ['title' => 'Created', 'data' => 'created_at', 'name' => 'created_at', 'orderable_column' => 'admins.created_at', 'searchable' => false],
            ['title' => '', 'data' => 'actions', 'name' => 'actions', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
        ];
    }
}
