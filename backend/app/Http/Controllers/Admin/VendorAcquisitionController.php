<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVendorAcquisitionCommissionRequest;
use App\Http\Requests\Admin\UpdateVendorAcquisitionCommissionRequest;
use App\Models\Admin;
use App\Models\Vendor;
use App\Models\VendorAcquisitionCommission;
use App\Services\ActivityLoggerService;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VendorAcquisitionController extends Controller
{
    use HasDataTable;

    public function __construct(
        private ActivityLoggerService $activityLogger,
    ) {}

    public function show(Vendor $vendor)
    {
        $commission = $vendor->acquisitionCommissions()
            ->with(['admin', 'createdByAdmin'])
            ->latest('created_at')
            ->first();

        $monthlyEarnings = collect();
        if ($commission) {
            $monthlyEarnings = $commission->earnings()
                ->selectRaw('month, COUNT(*) as sales_count, SUM(amount) as commission_earned, MAX(status) as status')
                ->groupBy('month')
                ->orderByDesc('month')
                ->get();
        }

        $adminCandidates = Admin::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.vendors.acquisition-agent', compact('vendor', 'commission', 'adminCandidates', 'monthlyEarnings'));
    }

    public function store(StoreVendorAcquisitionCommissionRequest $request, Vendor $vendor): JsonResponse
    {
        if ($vendor->acquisitionCommissions()->where('status', 'active')->exists()) {
            return response()->json(['message' => __('acquisition.errors.active_exists')], 422);
        }

        // The deal runs from the chosen start date; by default from the vendor's approval (the day the agent
        // "brought" the vendor), or today for a vendor that has not been approved yet.
        $validFrom = $request->date('valid_from') ?? $vendor->approved_at ?? now();
        $validUntil = Carbon::parse($validFrom)->addMonths((int) $request->input('duration_months'));

        if ($validUntil->toDateString() < now()->toDateString()) {
            return response()->json([
                'message' => __('acquisition.errors.already_expired', ['date' => $validUntil->toDateString()]),
                'errors' => ['valid_from' => [__('acquisition.errors.already_expired', ['date' => $validUntil->toDateString()])]],
            ], 422);
        }

        $commission = VendorAcquisitionCommission::create([
            'vendor_id' => $vendor->id,
            'admin_id' => $request->input('admin_id'),
            'commission_rate' => $request->input('commission_rate'),
            'monthly_min_sales' => $request->input('monthly_min_sales'),
            'monthly_max_sales' => $request->input('monthly_max_sales'),
            'valid_from' => Carbon::parse($validFrom)->toDateString(),
            'valid_until' => $validUntil->toDateString(),
            'status' => 'active',
            'currency' => $vendor->country?->currency_code ?? 'USD',
            'created_by_admin_id' => auth('admin')->id(),
            'notes' => $request->input('notes'),
        ]);

        $this->activityLogger->log(
            description: 'acquisition_agent_assigned',
            subject: $vendor,
            causer: auth('admin')->user(),
            properties: ['admin_id' => $commission->admin_id, 'commission_id' => $commission->id],
            logName: 'vendor',
            event: 'acquisition_agent_assigned',
        );

        return response()->json(['message' => __('acquisition.messages.assigned'), 'commission' => $commission]);
    }

    /** Vendor picker for the "Assign agent" dialog of the acquisition-commissions page. */
    public function searchVendors(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));

        $vendors = Vendor::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('store_name', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")))
            ->orderBy('store_name')
            ->limit(30)
            ->get(['id', 'store_name', 'name', 'approved_at']);

        return response()->json([
            'results' => $vendors->map(fn (Vendor $v) => [
                'id' => $v->id,
                'text' => $v->store_name ?: $v->name,
                'approved_at' => $v->approved_at?->toDateString(),
            ]),
        ]);
    }

    public function update(UpdateVendorAcquisitionCommissionRequest $request, Vendor $vendor, VendorAcquisitionCommission $commission): JsonResponse
    {
        abort_if($commission->vendor_id !== $vendor->id, 404);

        $commission->update([
            'commission_rate' => $request->input('commission_rate'),
            'monthly_min_sales' => $request->input('monthly_min_sales'),
            'monthly_max_sales' => $request->input('monthly_max_sales'),
            'notes' => $request->input('notes'),
        ]);

        $this->activityLogger->log(
            description: 'acquisition_agent_updated',
            subject: $vendor,
            causer: auth('admin')->user(),
            properties: ['commission_id' => $commission->id],
            logName: 'vendor',
            event: 'acquisition_agent_updated',
        );

        return response()->json(['message' => __('acquisition.messages.updated'), 'commission' => $commission]);
    }

    public function revoke(Request $request, Vendor $vendor, VendorAcquisitionCommission $commission): JsonResponse
    {
        abort_if($commission->vendor_id !== $vendor->id, 404);

        abort_if($commission->status !== 'active', 422, __('acquisition.errors.not_active'));

        $commission->update(['status' => 'revoked']);

        $this->activityLogger->log(
            description: 'acquisition_agent_revoked',
            subject: $vendor,
            causer: auth('admin')->user(),
            properties: ['commission_id' => $commission->id],
            logName: 'vendor',
            event: 'acquisition_agent_revoked',
        );

        return response()->json(['message' => __('acquisition.messages.revoked')]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = VendorAcquisitionCommission::query()
            ->with(['vendor', 'admin'])
            ->where('vendor_id', $request->input('vendor_id'));

        $columns = [
            [],
            [],
            [],
            ['orderable_column' => 'created_at'],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (VendorAcquisitionCommission $commission) {
            return [
                'vendor' => $commission->vendor?->store_name,
                'agent' => $commission->admin?->name,
                'rate' => $commission->commission_rate,
                'status' => $commission->status,
            ];
        });
    }

    public function index(Request $request)
    {
        $commissions = VendorAcquisitionCommission::query()
            ->with(['vendor', 'admin'])
            ->latest('created_at')
            ->paginate(25);

        $agentCandidates = Admin::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.vendor-acquisition.index', compact('commissions', 'agentCandidates'));
    }

    // ── Agent-facing views ──────────────────────────────────────────────────────

    public function myCommissions(Request $request)
    {
        $adminId = auth('admin')->id();

        // ProcessAcquisitionCommissionsJob runs on the 1st and books the PREVIOUS month, so the current month
        // never has earnings yet: show the latest processed month, named in the column header.
        $processedMonth = now()->subMonthNoOverflow()->startOfMonth();

        $commissions = VendorAcquisitionCommission::query()
            ->with([
                'vendor',
                'earnings' => fn ($query) => $query->where('month', $processedMonth->toDateString()),
            ])
            ->where('admin_id', $adminId)
            ->where('status', 'active')
            ->get();

        $rows = $commissions->map(function (VendorAcquisitionCommission $commission) {
            return [
                'vendor_name' => $commission->vendor?->store_name,
                'commission_rate' => $commission->commission_rate,
                'sales_processed_month' => $commission->earnings->count(),
                'earned_processed_month' => $commission->earnings->sum('amount'),
                'total_earned' => $commission->total_earned,
                'currency' => $commission->currency,
                'expires_on' => $commission->valid_until,
            ];
        });

        $processedMonthLabel = $processedMonth->locale(app()->getLocale())->translatedFormat('F Y');

        return view('admin.vendor-acquisition.my-commissions', compact('rows', 'processedMonthLabel'));
    }

    public function myDatatable(Request $request): JsonResponse
    {
        $adminId = auth('admin')->id();

        $query = VendorAcquisitionCommission::query()
            ->with('vendor')
            ->where('admin_id', $adminId)
            ->where('status', 'active');

        $columns = [
            [],
            [],
            [],
            ['orderable_column' => 'valid_until'],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (VendorAcquisitionCommission $commission) {
            return [
                'vendor' => $commission->vendor?->store_name,
                'rate' => $commission->commission_rate,
                'total_earned' => $commission->total_earned,
                'expires_on' => admin_tz($commission->valid_until)?->format('d M Y'),
            ];
        });
    }
}
