<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CarrierClaimStatus;
use App\Http\Controllers\Controller;
use App\Models\CarrierClaim;
use App\Models\ShippingCompany;
use App\Services\CarrierClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CarrierClaimController extends Controller
{
    public function __construct(private readonly CarrierClaimService $service) {}

    public function index(Request $request): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('carrier-claims.view'), 403);

        $stats = [
            'submitted'    => CarrierClaim::where('status', CarrierClaimStatus::Submitted)->count(),
            'under_review' => CarrierClaim::where('status', CarrierClaimStatus::UnderReview)->count(),
            'approved'     => CarrierClaim::where('status', CarrierClaimStatus::Approved)->count(),
            'compensated'  => CarrierClaim::where('status', CarrierClaimStatus::Compensated)->count(),
            'rejected'     => CarrierClaim::where('status', CarrierClaimStatus::Rejected)->count(),
        ];

        $query = CarrierClaim::with(['shipment', 'shippingCompany'])
            ->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($companyId = $request->input('shipping_company_id')) {
            $query->where('shipping_company_id', $companyId);
        }

        if ($type = $request->input('claim_type')) {
            $query->where('claim_type', $type);
        }

        $claims    = $query->paginate(20)->withQueryString();
        $companies = ShippingCompany::orderBy('name')->get(['id', 'name']);

        return view('admin.carrier-claims.index', compact('claims', 'stats', 'companies'));
    }

    public function show(CarrierClaim $carrierClaim): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('carrier-claims.view'), 403);

        $carrierClaim->load([
            'shipment.subOrder.vendor',
            'shippingCompany',
            'deliveryAgent',
            'resolvedBy',
        ]);

        return view('admin.carrier-claims.show', ['claim' => $carrierClaim]);
    }

    public function resolve(Request $request, CarrierClaim $carrierClaim): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('carrier-claims.resolve'), 403);

        abort_if($carrierClaim->isResolved(), 422, __('admin.carrier_claims.already_resolved'));

        $data = $request->validate([
            'decision'          => ['required', 'in:approved,rejected'],
            'compensated_amount' => ['nullable', 'numeric', 'min:0'],
            'resolution_notes'  => ['nullable', 'string', 'max:2000'],
        ]);

        $compensatedCents = isset($data['compensated_amount'])
            ? (int) round($data['compensated_amount'])
            : null;

        $this->service->resolve(
            $carrierClaim,
            $data['decision'],
            $compensatedCents,
            $admin,
            $data['resolution_notes'] ?? null,
        );

        $decisionLabel = $data['decision'] === 'approved'
            ? __('admin.carrier_claims.decision_approved')
            : __('admin.carrier_claims.decision_rejected');

        return redirect()
            ->route('admin.carrier-claims.show', $carrierClaim)
            ->with('success', __('admin.carrier_claims.resolved_success', ['decision' => $decisionLabel]));
    }

    public function markUnderReview(CarrierClaim $carrierClaim): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('carrier-claims.resolve'), 403);

        $this->service->markUnderReview($carrierClaim);

        return back()->with('success', __('admin.carrier_claims.moved_under_review'));
    }
}
