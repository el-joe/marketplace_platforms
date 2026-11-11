<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\VendorAdmin;
use App\Services\Vendor\TeamService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function __construct(private readonly TeamService $team) {}

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function vendorAdmin(): VendorAdmin
    {
        return Auth::guard('vendor')->user();
    }

    private function vendorId(): string
    {
        return $this->vendorAdmin()->vendor_id;
    }

    /**
     * Guard: member must belong to the authenticated vendor.
     */
    private function authoriseMember(VendorAdmin $member): void
    {
        if ($member->vendor_id !== $this->vendorId()) {
            abort(404);
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * Team management page — all vendor admins ordered owner→manager→staff.
     */
    public function index(Request $request): View|StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportTeam($request);
        }

        $admin = $this->vendorAdmin();
        $members = $this->buildTeamQuery($request)
            ->orderByDesc('is_owner')
            ->orderBy('created_at')
            ->paginate(20)
            ->withQueryString();

        $assignableRoles = Role::where('guard_name', 'vendor')
            ->where('name', '!=', 'vendor_owner')
            ->orderBy('name')
            ->pluck('name');

        return view('partner.team.index', compact('admin', 'members', 'assignableRoles'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────────

    private function buildTeamQuery(Request $request): Builder
    {
        $query = VendorAdmin::where('vendor_id', $this->vendorId());

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $query = $this->applyFilters($query, $request, [
            'role' => fn ($q, $v) => $q->where('role', $v),
            'is_active' => fn ($q, $v) => $q->where('is_active', (bool) $v),
            'date_from' => fn ($q, $v) => $q->whereDate('created_at', '>=', $v),
            'date_to' => fn ($q, $v) => $q->whereDate('created_at', '<=', $v),
        ]);

        return $query;
    }

    private function exportTeam(Request $request): StreamedResponse
    {
        $members = $this->buildTeamQuery($request)->orderByDesc('is_owner')->orderBy('created_at')->get();

        $headers = ['Name', 'Email', 'Role', 'Active', 'Last Login', 'Joined'];

        $rows = $members->map(fn ($row) => [
            $row->name,
            $row->email,
            $row->role,
            $row->is_active ? 'Yes' : 'No',
            optional(vendor_tz($row->last_login_at))->format('Y-m-d H:i') ?? '—',
            vendor_tz($row->created_at)?->format('Y-m-d H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('team', $headers, $rows),
            'csv' => $this->exportCsv('team', $headers, $rows),
            'word' => $this->exportWord('team', 'Team', $rows),
            default => abort(400, __('common.invalid_export_format')),
        };
    }

    /**
     * Invite a new team member (owner only).
     * Emails a single-use set-password link (no password is created or sent).
     */
    public function store(Request $request): JsonResponse
    {
        $admin = $this->vendorAdmin();

        if (! $admin->isOwner()) {
            return response()->json(['message' => 'يحق للمالك فقط إضافة أعضاء الفريق'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:vendor_admins,email',
            'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'vendor'), Rule::notIn(['vendor_owner'])],
        ]);

        [$member, $delivery] = $this->team->invite($this->vendorAdmin()->vendor, [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => $delivery->warning() ?? 'تم إرسال الدعوة بنجاح',
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role,
                'is_active' => $member->is_active,
                'last_login_at' => null,
            ],
        ] + $delivery->toResponseFields(), 201);
    }

    /**
     * Change a team member's role (owner only, cannot change own or another owner's role).
     */
    public function updateRole(Request $request, VendorAdmin $member): JsonResponse
    {
        $admin = $this->vendorAdmin();

        if (! $admin->isOwner()) {
            return response()->json(['message' => 'يحق للمالك فقط تعديل أدوار الأعضاء'], 403);
        }

        $this->authoriseMember($member);

        if ($member->id === $admin->id) {
            return response()->json(['message' => 'لا يمكنك تعديل دورك الخاص'], 422);
        }

        if ($member->isOwner()) {
            return response()->json(['message' => 'لا يمكن تعديل دور المالك'], 422);
        }

        $validated = $request->validate([
            'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'vendor'), Rule::notIn(['vendor_owner'])],
        ]);

        DB::transaction(function () use ($member, $validated) {
            $member->syncRoles([$validated['role']]);
            $member->update(['role' => $validated['role']]);
        });

        return response()->json([
            'message' => 'تم تحديث دور العضو بنجاح',
            'role' => $member->role,
        ]);
    }

    /**
     * Toggle a team member's active status (owner only, cannot deactivate owner).
     */
    public function toggleActive(VendorAdmin $member): JsonResponse
    {
        $admin = $this->vendorAdmin();

        if (! $admin->isOwner()) {
            return response()->json(['message' => 'يحق للمالك فقط تعديل حالة الأعضاء'], 403);
        }

        $this->authoriseMember($member);

        if ($member->isOwner()) {
            return response()->json(['message' => 'لا يمكن تعطيل حساب المالك'], 422);
        }

        DB::transaction(function () use ($member) {
            $member->update(['is_active' => ! $member->is_active]);

            if ($member->is_active) {
                $member->syncRoles([$member->role]);
            } else {
                $member->syncRoles([]);
            }
        });

        return response()->json([
            'message' => $member->is_active ? 'تم تفعيل العضو' : 'تم تعطيل العضو',
            'is_active' => $member->is_active,
        ]);
    }

    /**
     * Soft-delete a team member (owner only, cannot delete owner).
     */
    public function destroy(VendorAdmin $member): JsonResponse
    {
        $admin = $this->vendorAdmin();

        if (! $admin->isOwner()) {
            return response()->json(['message' => 'يحق للمالك فقط حذف أعضاء الفريق'], 403);
        }

        $this->authoriseMember($member);

        if ($member->isOwner()) {
            return response()->json(['message' => 'لا يمكن حذف حساب المالك'], 422);
        }

        $member->delete();

        return response()->json(['message' => 'تم إزالة العضو بنجاح']);
    }
}
