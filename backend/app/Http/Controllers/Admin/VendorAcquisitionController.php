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
    ) {
    }

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

        $adminCandidates = Admin::orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.vendors.acquisition-agent', compact('vendor', 'commission', 'adminCandidates', 'monthlyEarnings'));
    }

    public function store(StoreVendorAcquisitionCommissionRequest $request, Vendor $vendor): JsonResponse
    {
        $validFrom = $vendor->approved_at?->toDateString() ?? now()->toDateString();
        $validUntil = Carbon::parse($validFrom)->addMonths((int) $request->input('duration_months'))->toDateString();

        $commission = VendorAcquisitionCommission::create([
            'vendor_id' => $vendor->id,
            'admin_id' => $request->input('admin_id'),
            'commission_rate' => $request->input('commission_rate'),
            'monthly_min_sales' => $request->input('monthly_min_sales'),
            'monthly_max_sales' => $request->input('monthly_max_sales'),
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
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

        return response()->json(['message' => 'Acquisition agent assigned.', 'commission' => $commission]);
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

        return response()->json(['message' => 'Acquisition commission updated.', 'commission' => $commission]);
    }

    public function revoke(Request $request, Vendor $vendor, VendorAcquisitionCommission $commission): JsonResponse
    {
        abort_if($commission->vendor_id !== $vendor->id, 404);

        $commission->update(['status' => 'revoked']);

        $this->activityLogger->log(
            description: 'acquisition_agent_revoked',
            subject: $vendor,
            causer: auth('admin')->user(),
            properties: ['commission_id' => $commission->id],
            logName: 'vendor',
            event: 'acquisition_agent_revoked',
        );

        return response()->json(['message' => 'Acquisition agent revoked.']);
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

        return view('admin.vendor-acquisition.index', compact('commissions'));
    }

    // ── Agent-facing views ──────────────────────────────────────────────────────

    public function myCommissions(Request $request)
    {
        $adminId = auth('admin')->id();

        $commissions = VendorAcquisitionCommission::query()
            ->with('vendor')
            ->where('admin_id', $adminId)
            ->where('status', 'active')
            ->get();

        $monthStart = now()->startOfMonth();

        $rows = $commissions->map(function (VendorAcquisitionCommission $commission) use ($monthStart) {
            $earningsThisMonth = $commission->earnings()
                ->where('month', $monthStart->toDateString())
                ->get();

            return [
                'vendor_name' => $commission->vendor?->store_name,
                'commission_rate' => $commission->commission_rate,
                'sales_this_month' => $earningsThisMonth->count(),
                'earned_this_month' => $earningsThisMonth->sum('amount'),
                'total_earned' => $commission->total_earned,
                'currency' => $commission->currency,
                'expires_on' => $commission->valid_until,
            ];
        });

        return view('admin.vendor-acquisition.my-commissions', compact('rows'));
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
                'expires_on' => $commission->valid_until->format('d M Y'),
            ];
        });
    }
}
