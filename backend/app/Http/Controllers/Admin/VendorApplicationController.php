<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VendorBankAccountVerificationStatus;
use App\Enums\VendorDocumentStatus;
use App\Enums\VendorGlobalStatus;
use App\Http\Controllers\Controller;
use App\Jobs\VendorApprovedJob;
use App\Models\Admin;
use App\Models\Country;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorDocument;
use App\Services\VendorApprovalService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VendorApplicationController extends Controller
{
    use HasDataTable;
    use HasExport;

    /** Required document types that must be verified before approval */
    private const REQUIRED_DOC_TYPES = ['business_license', 'tax_certificate', 'owner_id'];

    public function __construct(private VendorApprovalService $approvalService)
    {
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('vendors.view'), 403);

        if ($request->filled('export')) {
            return $this->exportApplications($request);
        }

        $stats = [
            'pending' => Vendor::where('global_status', VendorGlobalStatus::Pending->value)

                ->count(),
            'under_review' => Vendor::where('global_status', VendorGlobalStatus::UnderReview->value)

                ->count(),
            'waiting_5plus' => Vendor::whereIn('global_status', [VendorGlobalStatus::Pending->value, VendorGlobalStatus::UnderReview->value])

                ->where('onboarding_completed_at', '<=', now()->subDays(5))
                ->count(),
        ];

        $countries = Country::orderBy('name_en')->get(['id', 'name_en', 'flag_emoji']);

        return view('admin.vendor-applications.index', compact('stats', 'countries'));
    }

    // ─── DataTable ────────────────────────────────────────────────────────────

    private function buildApplicationsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Vendor::query()
            ->with(['country', 'documents', 'bankAccounts'])
            // Base queue is pending/under_review; 'rejected' is included so the
            // status filter (which supports pending/rejected/under_review, per
            // spec) can also surface previously rejected applications.
            ->whereIn('global_status', [
                VendorGlobalStatus::Pending->value,
                VendorGlobalStatus::UnderReview->value,
                VendorGlobalStatus::Rejected->value,
            ])
            ;



        return $this->applyFilters($query, $request, [
            'search' => fn($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('vendors.name', 'like', "%{$v}%")
                    ->orWhere('vendors.email', 'like', "%{$v}%")
                    ->orWhere('vendors.store_name', 'like', "%{$v}%");
            }),
            'country_id' => fn($q, $v) => $q->where('country_id', $v),
            'status' => fn($q, $v) => $q->where('global_status', $v),
            'date_from' => fn($q, $v) => $q->whereDate('created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('created_at', '<=', $v),
            'days_min' => fn($q, $v) => $q->where('onboarding_completed_at', '<=', now()->subDays((int) $v)),
            'days_max' => fn($q, $v) => $q->where('onboarding_completed_at', '>=', now()->subDays((int) $v)),
        ]);
    }

    private function exportApplications(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $applications = $this->buildApplicationsQuery($request)->latest('created_at')->get();

        $headers = ['Applicant', 'Email', 'Store', 'Country', 'Status', 'Applied At'];

        $rows = $applications->map(fn(Vendor $vendor) => [
            $vendor->name,
            $vendor->email,
            $vendor->store_name,
            $vendor->country?->name_en ?? '—',
            $vendor->global_status?->value,
            $vendor->created_at->format('d M Y'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('vendor_applications', $headers, $rows),
            'csv' => $this->exportCsv('vendor_applications', $headers, $rows),
            'word' => $this->exportWord('vendor_applications', 'Vendor Applications', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('vendors.view'), 403);

        $query = $this->buildApplicationsQuery($request);

        $columns = [
            ['searchable_columns' => ['vendors.store_name', 'vendors.business_name'], 'orderable_column' => 'vendors.store_name'],
            ['searchable_columns' => ['vendors.business_name'], 'orderable_column' => 'vendors.business_name'],
            ['searchable_columns' => [], 'orderable_column' => null], // country
            ['searchable_columns' => ['vendors.business_type'], 'orderable_column' => 'vendors.business_type'],
            ['searchable_columns' => [], 'orderable_column' => null], // docs status
            ['searchable_columns' => [], 'orderable_column' => null], // bank status
            ['searchable_columns' => [], 'orderable_column' => 'vendors.onboarding_completed_at'], // days waiting
            ['searchable_columns' => [], 'orderable_column' => 'vendors.created_at'],
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        return $this->dataTableResponse($request, $query, $columns, function (Vendor $vendor) {
            $daysWaiting = (int) now()->diffInDays($vendor->onboarding_completed_at);

            $urgencyClass = match (true) {
                $daysWaiting > 5 => 'text-red-600 font-semibold',
                $daysWaiting >= 2 => 'text-yellow-600 font-semibold',
                default => 'text-green-600',
            };

            $statusColors = [
                VendorGlobalStatus::Pending->value => 'warning',
                VendorGlobalStatus::UnderReview->value => 'primary',
            ];
            $statusColor = $statusColors[$vendor->global_status->value] ?? 'gray';
            $statusLabel = $vendor->global_status->label();
            $statusBadge = "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{$statusColor}-100 text-{$statusColor}-700\">{$statusLabel}</span>";

            // Documents X / Y verified
            $totalDocs = $vendor->documents->count();
            $verifiedDocs = $vendor->documents->filter(fn($d) => $d->status === VendorDocumentStatus::Approved)->count();
            $docsStatus = "{$verifiedDocs}/{$totalDocs} verified";
            $docsColor = $verifiedDocs === $totalDocs && $totalDocs > 0 ? 'text-green-600' : 'text-yellow-600';

            // Bank status
            $primaryBank = $vendor->bankAccounts->where('is_primary', true)->first()
                ?? $vendor->bankAccounts->first();
            $bankColors = [
                VendorBankAccountVerificationStatus::Verified->value => 'success',
                VendorBankAccountVerificationStatus::Pending->value => 'warning',
                VendorBankAccountVerificationStatus::Rejected->value => 'danger',
            ];
            $bankStatus = $primaryBank?->verification_status?->value ?? 'none';
            $bc = $bankColors[$bankStatus] ?? 'gray';
            $bankBadge = "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{$bc}-100 text-{$bc}-700\">" . ucfirst($bankStatus) . "</span>";

            $showUrl = route('admin.vendor-applications.show', $vendor->id);
            $actions = "<div class=\"flex items-center gap-1\">"
                . "<a href=\"{$showUrl}\" class=\"btn btn-xs btn-primary\">Review</a>"
                . "</div>";

            return [
                'store_name' => '<div><span class="font-medium">' . e($vendor->store_name) . '</span>' . '<br><span class="text-xs text-gray-400">' . $statusBadge . '</span></div>',
                'business_name' => e($vendor->business_name ?? '—'),
                'country' => e($vendor->country?->name_en ?? '—'),
                'business_type' => e($vendor->business_type?->label() ?? '—'),
                'docs_status' => "<span class=\"text-sm {$docsColor}\">{$docsStatus}</span>",
                'bank_status' => $bankBadge,
                'days_waiting' => "<span class=\"{$urgencyClass}\">{$daysWaiting}d</span>",
                'created_at' => $vendor->created_at->format('d M Y'),
                'actions' => $actions,
                'DT_RowData' => ['id' => $vendor->id, 'status' => $vendor->global_status->value],
            ];
        });
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(Vendor $vendor): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('vendors.view'), 403);

        $vendor->load([
            'country',
            'approvedByAdmin',
            'accountManagerAdmin',
            'documents.verifiedByAdmin',
            'documents.documentType',
            'bankAccounts',
            'addresses',
        ]);

        $admins = Admin::orderBy('name')->get(['id', 'name']);
        $daysWaiting = (int) now()->diffInDays($vendor->onboarding_completed_at ?? $vendor->created_at);

        // Required docs checklist
        $requiredDocs = collect(self::REQUIRED_DOC_TYPES)->mapWithKeys(function ($type) use ($vendor) {
            $doc = $vendor->documents->first(fn($d) => $d->documentType?->code === $type);
            return [
                $type => [
                    'label' => $this->docTypeLabel($type),
                    'doc' => $doc,
                    'uploaded' => (bool) $doc,
                    'verified' => $doc ? $doc->status === VendorDocumentStatus::Approved : false,
                ]
            ];
        });

        // Checklist items (for right panel)
        $businessInfoComplete = !empty($vendor->business_name) && !empty($vendor->business_type);
        $allRequiredUploaded = $requiredDocs->every(fn($d) => $d['uploaded']);
        $allRequiredVerified = $requiredDocs->every(fn($d) => $d['verified']);
        $hasBankAccount = $vendor->bankAccounts->isNotEmpty();
        $storeProfileComplete = !empty($vendor->store_name) && !empty($vendor->store_description);

        $checklist = [
            'business_info' => ['label' => 'Business info complete', 'pass' => $businessInfoComplete],
            'docs_uploaded' => ['label' => 'All required docs uploaded', 'pass' => $allRequiredUploaded],
            'docs_verified' => ['label' => 'All required docs verified', 'pass' => $allRequiredVerified],
            'bank_account' => ['label' => 'Bank account added', 'pass' => $hasBankAccount, 'required' => false],
            'store_profile' => ['label' => 'Store profile complete', 'pass' => $storeProfileComplete],
        ];

        $canApprove = collect($checklist)
            ->reject(fn($c) => ($c['required'] ?? true) === false)
            ->every(fn($c) => $c['pass']);

        return view('admin.vendor-applications.show', compact(
            'vendor',
            'admins',
            'daysWaiting',
            'requiredDocs',
            'checklist',
            'canApprove'
        ));
    }

    // ─── Start Review ─────────────────────────────────────────────────────────

    public function startReview(Vendor $vendor): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('vendors.edit'), 403);

        if (!in_array($vendor->global_status, [VendorGlobalStatus::Pending, VendorGlobalStatus::UnderReview], true)) {
            return response()->json(['message' => 'Application is not pending review.'], 422);
        }

        $vendor->update([
            'global_status' => VendorGlobalStatus::UnderReview,
            'account_manager_admin_id' => $vendor->account_manager_admin_id ?? $admin->id,
        ]);

        return response()->json(['message' => 'Application marked as under review.', 'status' => VendorGlobalStatus::UnderReview->value]);
    }

    // ─── Assign Me ────────────────────────────────────────────────────────────

    public function assignMe(Vendor $vendor): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('vendors.edit'), 403);

        $vendor->update(['account_manager_admin_id' => $admin->id]);

        return response()->json(['message' => 'You have been assigned as account manager.']);
    }

    // ─── Approve ──────────────────────────────────────────────────────────────

    public function approve(Request $request, Vendor $vendor): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('vendors.edit'), 403);

        $request->validate([
            'commission_rate_override' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'account_manager_admin_id' => ['nullable', 'string', 'exists:admins,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // 1. Check onboarding completed
        if (!$vendor->onboarding_completed_at) {
            return response()->json(['message' => 'Vendor has not completed onboarding.'], 422);
        }

        // 2. Check all required document types are verified
        $missingOrUnverified = [];
        foreach (self::REQUIRED_DOC_TYPES as $type) {
            $doc = $vendor->documents()->whereHas('documentType', fn($q) => $q->where('code', $type))->first();
            if (!$doc || $doc->status !== VendorDocumentStatus::Approved) {
                $missingOrUnverified[] = $this->docTypeLabel($type);
            }
        }
        if (!empty($missingOrUnverified)) {
            return response()->json([
                'message' => 'The following required documents must be verified before approval.',
                'missing' => $missingOrUnverified,
            ], 422);
        }

        // 3. Check at least one bank account
        if (!$vendor->bankAccounts()->exists()) {
            return response()->json(['message' => 'Vendor must have at least one bank account on file.'], 422);
        }

        DB::transaction(function () use ($vendor, $admin, $request) {
            // 1. Update vendor status
            $vendor->update([
                'global_status' => VendorGlobalStatus::Active,
                'approved_by_admin_id' => $admin->id,
                'approved_at' => now(),
            ]);

            // 2. Commission rate override
            if ($request->filled('commission_rate_override')) {
                $vendor->update(['commission_rate' => $request->input('commission_rate_override')]);
            }

            // 3. Account manager
            if ($request->filled('account_manager_admin_id')) {
                $vendor->update(['account_manager_admin_id' => $request->input('account_manager_admin_id')]);
            }

            // 4. Create VendorAdmin (owner role) if not exists
            $exists = VendorAdmin::where('vendor_id', $vendor->id)->where('is_owner', true)->exists();
            if (!$exists) {
                $owner = VendorAdmin::create([
                    'vendor_id' => $vendor->id,
                    'name' => $vendor->business_name ?? $vendor->store_name,
                    'email' => $vendor->email,
                    'password' => Hash::make(Str::random(12)),
                    'role' => 'vendor_owner',
                    'is_owner' => true,
                    'is_active' => true,
                ]);
                $owner->assignRole('vendor_owner');
            }

            // 5. Dispatch welcome job
            VendorApprovedJob::dispatch($vendor->id);
        });

        return response()->json(['message' => 'Vendor approved. Welcome email dispatched.']);
    }

    // ─── Reject ───────────────────────────────────────────────────────────────

    public function reject(Request $request, Vendor $vendor): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('vendors.edit'), 403);

        $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:2000'],
            'rejection_codes' => ['nullable', 'array'],
            'rejection_codes.*' => ['string', 'in:documents_incomplete,documents_invalid,business_not_verifiable,policy_violation,duplicate_account,prohibited_category,other'],
        ]);

        $reason = $request->input('rejection_reason');
        if (!empty($request->input('rejection_codes'))) {
            $codes = implode(', ', array_map(fn($c) => ucwords(str_replace('_', ' ', $c)), $request->input('rejection_codes')));
            $reason = "[{$codes}] {$reason}";
        }

        $this->approvalService->reject($vendor, $reason, $admin);

        return response()->json(['message' => 'Application rejected. Notification dispatched.']);
    }

    // ─── Request More Info ────────────────────────────────────────────────────

    public function requestMoreInfo(Request $request, Vendor $vendor): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('vendors.edit'), 403);

        $request->validate([
            'required_document_types' => ['required', 'array', 'min:1'],
            'required_document_types.*' => ['string'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->approvalService->requestMoreInfo(
            $vendor,
            $request->input('required_document_types'),
            $admin
        );

        return response()->json(['message' => 'More information requested. Vendor notified.']);
    }

    // ─── Verify Document ──────────────────────────────────────────────────────

    public function verifyDocument(Request $request, VendorDocument $document): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('vendors.edit'), 403);

        $document->update([
            'status' => VendorDocumentStatus::Approved,
            'verified_by_admin_id' => $admin->id,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json([
            'message' => 'Document verified.',
            'doc_id' => $document->id,
            'status' => VendorDocumentStatus::Approved->value,
        ]);
    }

    // ─── Reject Document ──────────────────────────────────────────────────────

    public function rejectDocument(Request $request, VendorDocument $document): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('vendors.edit'), 403);

        $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $document->update([
            'status' => VendorDocumentStatus::Rejected,
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        return response()->json([
            'message' => 'Document rejected.',
            'doc_id' => $document->id,
            'status' => VendorDocumentStatus::Rejected->value,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function docTypeLabel(string $type): string
    {
        return match ($type) {
            'business_license' => 'Business License',
            'tax_certificate' => 'Tax Certificate',
            'owner_id' => 'Owner ID',
            'bank_statement' => 'Bank Statement',
            'trade_license' => 'Trade License',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}
