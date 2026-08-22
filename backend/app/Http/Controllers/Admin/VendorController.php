<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VendorBankAccountVerificationStatus;
use App\Enums\VendorDocumentStatus;
use App\Enums\VendorGlobalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveVendorRequest;
use App\Http\Requests\Admin\AssignVendorManagerRequest;
use App\Http\Requests\Admin\IssueVendorStrikeRequest;
use App\Http\Requests\Admin\RejectVendorRequest;
use App\Http\Requests\Admin\UpdateVendorRequest;
use App\Http\Requests\Admin\VerifyVendorDocumentRequest;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\Country;
use App\Models\Notification;
use App\Models\SubOrder;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorBankAccount;
use App\Models\VendorDocument;
use App\Services\ActivityLoggerService;
use App\Services\VendorApprovalService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function __construct(
        private VendorApprovalService $approvalService,
        private ActivityLoggerService $activityLogger,
    ) {
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        if ($request->filled('export')) {
            return $this->exportVendors($request);
        }

        $isScopedAdmin = (bool) $request->attributes->get('is_scoped_admin');
        $scopedVendorIds = $request->attributes->get('scoped_vendor_ids');

        $pendingCountQuery = Vendor::where('global_status', VendorGlobalStatus::Pending->value);
        if ($isScopedAdmin) {
            $pendingCountQuery->whereIn('id', $scopedVendorIds ?? []);
        }
        $pendingCount = $pendingCountQuery->count();

        $admins = Admin::orderBy('name')->get(['id', 'name']);
        $countries = Country::orderBy('name_en')->get(['id', 'name_en']);

        return view('admin.vendors.index', compact('pendingCount', 'admins', 'countries', 'isScopedAdmin'));
    }

    // ── Export ────────────────────────────────────────────────────────────────

    private function buildVendorsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $isScopedAdmin = (bool) $request->attributes->get('is_scoped_admin');
        $scopedVendorIds = $request->attributes->get('scoped_vendor_ids');

        $query = Vendor::query()
            ->with(['country', 'accountManagerAdmin'])
            ->select('vendors.*');

        if ($isScopedAdmin) {
            $query->whereIn('vendors.id', $scopedVendorIds ?? []);
        } else {
            $query->withCount('bankAccounts');
        }

        return $this->applyFilters($query, $request, [
            'search' => fn($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('vendors.name', 'like', "%{$v}%")
                    ->orWhere('vendors.store_name', 'like', "%{$v}%")
                    ->orWhere('vendors.email', 'like', "%{$v}%");
            }),
            'global_status' => fn($q, $v) => $q->where('global_status', $v),
            'country_id' => fn($q, $v) => $q->where('country_id', $v),
            'account_manager_admin_id' => fn($q, $v) => $q->where('account_manager_admin_id', $v),
            'date_from' => fn($q, $v) => $q->whereDate('created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('created_at', '<=', $v),
        ]);
    }

    private function exportVendors(Request $request)
    {
        $isScopedAdmin = (bool) $request->attributes->get('is_scoped_admin');

        $vendors = $this->buildVendorsQuery($request)->latest('created_at')->get();

        // Financial / payout columns are omitted for account managers scoped to
        // their assigned vendor(s), matching the same gating used in datatable().
        $headers = ['ID', 'Store Name', 'Email', 'Country', 'Status', 'Orders', 'Joined'];
        if (!$isScopedAdmin) {
            $headers[] = 'GMV';
        }

        $rows = $vendors->map(function (Vendor $vendor) use ($isScopedAdmin) {
            $row = [
                $vendor->id,
                $vendor->store_name,
                $vendor->email,
                $vendor->country?->name_en ?? '—',
                $vendor->global_status?->value,
                $vendor->total_orders,
                $vendor->created_at->format('d M Y'),
            ];

            if (!$isScopedAdmin) {
                $row[] = number_format($vendor->total_sales, 2);
            }

            return $row;
        });

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('vendors', $headers, $rows),
            'csv' => $this->exportCsv('vendors', $headers, $rows),
            'word' => $this->exportWord('vendors', 'Vendors', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    // ── DataTable ─────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $isScopedAdmin = (bool) $request->attributes->get('is_scoped_admin');

        $query = $this->buildVendorsQuery($request);

        $columns = [
            ['searchable_columns' => ['vendors.store_name', 'vendors.business_name'], 'orderable_column' => 'vendors.store_name'],
            ['searchable_columns' => ['vendors.name'], 'orderable_column' => 'vendors.name'],
            ['searchable_columns' => ['vendors.email'], 'orderable_column' => 'vendors.email'],
            ['orderable_column' => 'vendors.total_sales'],
            ['orderable_column' => 'vendors.total_orders'],
            ['orderable_column' => 'vendors.store_rating_avg'],
            ['orderable_column' => 'vendors.global_status'],
            [],
            ['orderable_column' => 'vendors.created_at'],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (Vendor $vendor) use ($isScopedAdmin) {
            $row = [
                'store' => [
                    'id' => $vendor->id,
                    'store_name' => $vendor->store_name,
                    'avatar' => $vendor->avatar,
                    'strikes' => $vendor->strikes_count,
                ],
                'owner_name' => $vendor->name,
                'email' => $vendor->email,
                'orders' => number_format($vendor->total_orders),
                'rating' => number_format($vendor->store_rating_avg, 1),
                'global_status' => $vendor->global_status?->value,
                'manager' => $vendor->accountManagerAdmin?->name ?? '—',
                'created_at' => $vendor->created_at->format('d M Y'),
                'actions' => [
                    'id'            => $vendor->id,
                    'store_name'    => $vendor->store_name,
                    'global_status' => $vendor->global_status?->value,
                ],
            ];

            // Financial / payout columns are omitted entirely for account managers
            // scoped to their assigned vendor(s) — they must not see revenue data.
            if (!$isScopedAdmin) {
                $row['store']['payout_hold'] = $vendor->payout_hold_active;
                $row['gmv'] = '$' . number_format($vendor->total_sales, 2);
                $row['commission_rate'] = $vendor->commission_rate;
                $row['bank_account_count'] = $vendor->bank_accounts_count;
            }

            return $row;
        });
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(Request $request, Vendor $vendor)
    {
        $isScopedAdmin = (bool) $request->attributes->get('is_scoped_admin');
        $scopedVendorIds = $request->attributes->get('scoped_vendor_ids');

        if ($isScopedAdmin) {
            abort_if(!in_array($vendor->id, $scopedVendorIds ?? [], true), 403);

            $completedOrdersCount = SubOrder::query()
                ->join('orders as o', 'o.id', '=', 'sub_orders.order_id')
                ->where('sub_orders.vendor_id', $vendor->id)
                ->where('o.status', 'completed')
                ->count();

            $vendor->load(['country']);

            return view('admin.vendors.show_scoped', compact('vendor', 'completedOrdersCount'));
        }

        $vendor->load([
            'country',
            'approvedByAdmin',
            'accountManagerAdmin',
            'documents',
            'strikes.issuedByAdmin',
            'bankAccounts',
            'vendorAdmins' => function ($q) {
                $q->withTrashed()->with('roles');
            },
            'sectionLocks.lockedByAdmin',
            'sectionLocks.unlockedByAdmin',
            'acquisitionCommissions' => function ($q) {
                $q->where('status', 'active')->with('admin')->latest('created_at');
            },
        ]);

        $sectionLocks = $vendor->sectionLocks->keyBy('section');

        $subOrders = $vendor->subOrders()->latest()->limit(50)->get();
        $payouts = $vendor->payouts()->latest()->limit(50)->get();
        $citySurcharges = $vendor->cityShippingSurcharges()->with('city.country')->orderBy('created_at', 'desc')->get();
        $activityLog = Activity::query()
            ->where('subject_type', Vendor::class)
            ->where('subject_id', $vendor->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $admins = Admin::orderBy('name')->get(['id', 'name']);
        $accountManagerCandidates = Admin::role('vendor_relations_admin')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.vendors.show', compact('vendor', 'subOrders', 'payouts', 'activityLog', 'admins', 'accountManagerCandidates', 'citySurcharges', 'sectionLocks'));
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(UpdateVendorRequest $request, Vendor $vendor): JsonResponse
    {
        $vendor->update($request->validated());

        if ($request->has('marketer_type')) {
            abort_unless(auth('admin')->user()->hasPermissionTo('vendor_marketer_type.edit'), 403);

            $request->validate([
                'marketer_type' => ['nullable', Rule::in(['influencer', 'affiliate'])],
                'whatsapp_for_campaigns' => ['nullable', 'string', 'max:30'],
            ]);

            $newType = $request->input('marketer_type') ?: null;
            $oldType = $vendor->marketer_type;

            $vendor->update([
                'marketer_type' => $newType,
                'whatsapp_for_campaigns' => $request->input('whatsapp_for_campaigns'),
            ]);

            if ($newType && !$oldType) {
                $vendor->marketerProfile()->firstOrCreate(['vendor_id' => $vendor->id]);
            }

            if (!$newType && $oldType) {
                $vendor->campaignInvitations()
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);
            }
        }

        return response()->json(['message' => 'Vendor profile updated successfully.']);
    }

    // ── Approval actions ──────────────────────────────────────────────────────

    public function approve(ApproveVendorRequest $request, Vendor $vendor): JsonResponse
    {
        // Verify all critical documents are verified before approval
        $unverified = $vendor->documents()
            ->where('status', '!=', VendorDocumentStatus::Approved->value)
            ->count();

        if ($unverified > 0) {
            return response()->json([
                'message' => 'All required documents must be verified before approval. ' . $unverified . ' document(s) pending.',
            ], 422);
        }

        $this->approvalService->approve($vendor, auth('admin')->user());

        return response()->json(['message' => 'Vendor approved successfully.']);
    }

    public function reject(RejectVendorRequest $request, Vendor $vendor): JsonResponse
    {
        $this->approvalService->reject($vendor, $request->input('rejection_reason'), auth('admin')->user());

        return response()->json(['message' => 'Vendor application rejected.']);
    }

    public function requestInfo(Request $request, Vendor $vendor): JsonResponse
    {
        $request->validate([
            'document_types' => ['required', 'array', 'min:1'],
            'document_types.*' => ['string'],
        ]);

        $this->approvalService->requestMoreInfo($vendor, $request->input('document_types'), auth('admin')->user());

        return response()->json(['message' => 'Additional information requested.']);
    }

    // ── Status transitions ────────────────────────────────────────────────────

    public function suspend(Request $request, Vendor $vendor): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $this->approvalService->suspend($vendor, $request->input('reason'), auth('admin')->user());

        return response()->json(['message' => 'Vendor suspended.']);
    }

    public function reactivate(Request $request, Vendor $vendor): JsonResponse
    {
        $this->approvalService->reactivate($vendor, auth('admin')->user());

        return response()->json(['message' => 'Vendor reactivated.']);
    }

    public function blacklist(Request $request, Vendor $vendor): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        $this->approvalService->blacklist($vendor, $request->input('reason'), auth('admin')->user());

        return response()->json(['message' => 'Vendor has been blacklisted.']);
    }

    // ── Strikes ───────────────────────────────────────────────────────────────

    public function issueStrike(IssueVendorStrikeRequest $request, Vendor $vendor): JsonResponse
    {
        $strike = $this->approvalService->issueStrike($vendor, $request->validated(), auth('admin')->user());

        return response()->json([
            'message' => 'Strike issued successfully.',
            'data' => [
                'active_count' => $strike->active_count,
                'auto_suspended' => $strike->auto_suspended,
            ],
        ]);
    }

    // ── Payout hold ───────────────────────────────────────────────────────────

    public function placeHold(Request $request, Vendor $vendor): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $this->approvalService->placePayoutHold($vendor, $request->input('reason'));

        return response()->json(['message' => 'Payout hold placed.']);
    }

    public function releaseHold(Request $request, Vendor $vendor): JsonResponse
    {
        $this->approvalService->releasePayoutHold($vendor);

        return response()->json(['message' => 'Payout hold released.']);
    }

    // ── Manager assignment ────────────────────────────────────────────────────

    public function assignManager(AssignVendorManagerRequest $request, Vendor $vendor): JsonResponse
    {
        $vendor->update(['account_manager_admin_id' => $request->input('admin_id')]);

        return response()->json(['message' => 'Account manager assigned.']);
    }

    public function assignAccountManager(Request $request, Vendor $vendor): JsonResponse
    {
        $request->validate([
            'admin_id' => ['nullable', 'uuid', 'exists:admins,id'],
        ]);

        $adminId = $request->input('admin_id');

        if ($adminId !== null) {
            $manager = Admin::findOrFail($adminId);
            abort_unless($manager->hasRole('vendor_relations_admin'), 422, 'Selected admin does not have the vendor relations role.');
        }

        $vendor->update(['account_manager_admin_id' => $adminId]);

        $this->activityLogger->log(
            description: 'account_manager_assigned',
            subject: $vendor,
            causer: auth('admin')->user(),
            properties: ['admin_id' => $adminId],
            logName: 'vendor',
            event: 'account_manager_assigned',
        );

        return response()->json(['message' => 'Account manager assigned.']);
    }

    // ── Team ──────────────────────────────────────────────────────────────────

    public function deactivateTeamMember(Request $request, Vendor $vendor, VendorAdmin $vendorAdmin): JsonResponse
    {
        abort_if($vendorAdmin->vendor_id !== $vendor->id, 404);

        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $vendorAdmin->update(['is_active' => false]);

        $this->activityLogger->log(
            description: 'vendor_team_member_deactivated',
            subject: $vendorAdmin,
            causer: auth('admin')->user(),
            properties: ['reason' => $request->input('reason'), 'vendor_id' => $vendor->id],
            logName: 'vendor_team',
            event: 'deactivated',
        );

        return response()->json(['message' => 'Team member deactivated.']);
    }

    public function reactivateTeamMember(Request $request, Vendor $vendor, VendorAdmin $vendorAdmin): JsonResponse
    {
        abort_if($vendorAdmin->vendor_id !== $vendor->id, 404);

        $vendorAdmin->update(['is_active' => true]);

        $this->activityLogger->log(
            description: 'vendor_team_member_reactivated',
            subject: $vendorAdmin,
            causer: auth('admin')->user(),
            properties: ['vendor_id' => $vendor->id],
            logName: 'vendor_team',
            event: 'reactivated',
        );

        return response()->json(['message' => 'Team member reactivated.']);
    }

    // ── Documents ─────────────────────────────────────────────────────────────

    public function documents(Vendor $vendor): JsonResponse
    {
        return response()->json([
            'data' => $vendor->documents->map(fn($d) => [
                'id' => $d->id,
                'document_type' => $d->document_type,
                'status' => $d->status?->value,
                'expires_at' => $d->expires_at?->toDateString(),
                'file_path' => $d->file_path,
            ]),
        ]);
    }

    public function verifyDocument(VerifyVendorDocumentRequest $request, VendorDocument $document): JsonResponse
    {
        $document->update([
            'status' => VendorDocumentStatus::Approved,
            'verified_by_admin_id' => auth('admin')->id(),
            'verified_at' => now(),
        ]);

        return response()->json(['message' => 'Document verified.']);
    }

    public function rejectDocument(Request $request, VendorDocument $document): JsonResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $document->update([
            'status' => VendorDocumentStatus::Rejected,
            'rejection_reason' => $request->input('rejection_reason'),
            'notes' => $request->input('notes'),
        ]);

        return response()->json(['message' => 'Document rejected.']);
    }

    // ── Bank accounts ─────────────────────────────────────────────────────────

    public function verifyBankAccount(Request $request, Vendor $vendor, string $accountId): JsonResponse
    {
        abort_if((bool) $request->attributes->get('is_scoped_admin'), 403);

        $account = $vendor->bankAccounts()->findOrFail($accountId);

        $account->update([
            'verification_status' => VendorBankAccountVerificationStatus::Verified,
            'verified_by_admin_id' => auth('admin')->id(),
            'verified_at' => now(),
        ]);

        return response()->json(['message' => 'Bank account verified.']);
    }

    // ── Performance data ──────────────────────────────────────────────────────

    public function performanceData(Vendor $vendor): JsonResponse
    {
        $days = 30;
        $from = now()->subDays($days - 1)->startOfDay();
        // A vendor is scoped to one country_id and therefore one currency, so this SUM
        // is safe in practice. GROUP BY orders.currency is added as an explicit safeguard
        // so that any future cross-border vendor data produces separate rows instead of
        // silently blending currencies.
        $gmvRaw = SubOrder::query()
            ->join('orders as o', 'o.id', '=', 'sub_orders.order_id')
            ->where('sub_orders.vendor_id', $vendor->id)
            ->whereIn('sub_orders.status', ['completed', 'delivered'])
            ->where('sub_orders.created_at', '>=', $from)
            ->selectRaw('DATE(sub_orders.created_at) as date, o.currency, COALESCE(SUM(sub_orders.vendor_payout),0) as gmv')
            ->groupBy('date', 'o.currency')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(fn($rows) => $rows->sum('gmv'));

        $labels = [];
        $gmvArr = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $labels[] = now()->subDays($i)->format('d M');
            $gmvArr[] = (float) ($gmvRaw[$d] ?? 0) / 100;
        }

        // Orders by status
        $ordersByStatus = SubOrder::query()
            ->where('vendor_id', $vendor->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // Platform averages
        $platformAvg = Vendor::query()
            ->whereIn('global_status', [VendorGlobalStatus::Active->value, VendorGlobalStatus::Suspended->value])
            ->selectRaw('AVG(total_sales) as gmv, AVG(total_orders) as orders, AVG(store_rating_avg) as rating')
            ->first();

        return response()->json([
            'data' => [
                'labels' => $labels,
                'gmv' => $gmvArr,
                'orders_by_status' => $ordersByStatus,
                'platform_avg' => [
                    'gmv' => round((float) $platformAvg->gmv, 2),
                    'orders' => round((float) $platformAvg->orders, 0),
                    'rating' => round((float) $platformAvg->rating, 1),
                ],
            ],
        ]);
    }

    // ── Bulk actions ──────────────────────────────────────────────────────────

    public function bulkAction(Request $request): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'in:suspend,reactivate,assign_manager,place_hold,export'],
            'vendor_ids' => ['required', 'array', 'min:1'],
        ]);

        $vendors = Vendor::whereIn('id', $request->input('vendor_ids'))->get();
        $admin = auth('admin')->user();
        $count = 0;

        foreach ($vendors as $vendor) {
            match ($request->input('action')) {
                'suspend' => $this->approvalService->suspend($vendor, $request->input('reason', 'Bulk suspension'), $admin),
                'reactivate' => $this->approvalService->reactivate($vendor, $admin),
                'assign_manager' => $vendor->update(['account_manager_admin_id' => $request->input('admin_id')]),
                'place_hold' => $this->approvalService->placePayoutHold($vendor, $request->input('reason', 'Bulk hold')),
                default => null,
            };
            $count++;
        }

        return response()->json(['message' => "Bulk action applied to {$count} vendor(s)."]);
    }

    // ── Notifications ─────────────────────────────────────────────────────────

    public function sendNotification(Request $request, Vendor $vendor): JsonResponse
    {
        $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Notification::query()->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\Vendor\\AdminMessage',
            'notifiable_type' => Vendor::class,
            'notifiable_id' => $vendor->id,
            'data' => json_encode([
                'subject' => $request->input('subject'),
                'message' => $request->input('message'),
                'from' => auth('admin')->user()->name,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Notification sent.']);
    }
}
