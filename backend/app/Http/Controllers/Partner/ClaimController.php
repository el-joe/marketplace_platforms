<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\CarrierClaim;
use App\Models\Shipment;
use App\Services\CarrierClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClaimController extends Controller
{
    public function __construct(private readonly CarrierClaimService $service) {}

    public function index(): View
    {
        $vendor = auth('vendor')->user();

        $claims = CarrierClaim::whereHas('shipment.subOrder', function ($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id);
            })
            ->with(['shipment', 'shippingCompany'])
            ->latest()
            ->paginate(20);

        return view('partner.claims.index', compact('claims'));
    }

    public function create(Request $request): View
    {
        $vendor = auth('vendor')->user();

        // Only show shipped/delivered sub-orders that don't yet have an open claim
        $shipments = Shipment::whereHas('subOrder', function ($q) use ($vendor) {
                $q->where('vendor_id', $vendor->id)
                  ->whereIn('status', ['shipped', 'out_for_delivery', 'delivered', 'completed']);
            })
            ->whereDoesntHave('carrierClaims', function ($q) {
                $q->whereNotIn('status', ['rejected']);
            })
            ->with('subOrder')
            ->latest()
            ->get();

        return view('partner.claims.create', compact('shipments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = auth('vendor')->user();

        $data = $request->validate([
            'shipment_id'          => ['required', 'uuid', 'exists:shipments,id'],
            'claim_type'           => ['required', 'in:lost,damaged,delayed,wrong_item,other'],
            'description'          => ['required', 'string', 'min:20', 'max:3000'],
            'claimed_amount'       => ['required', 'numeric', 'min:0.01'],
            'evidence_files'       => ['nullable', 'array', 'max:10'],
            'evidence_files.*'     => ['string'],
        ]);

        // Ensure shipment belongs to this vendor
        $shipment = Shipment::whereHas('subOrder', fn($q) => $q->where('vendor_id', $vendor->id))
            ->findOrFail($data['shipment_id']);

        $claim = $this->service->submitClaim([
            'shipment_id'          => $shipment->id,
            'shipping_company_id'  => $shipment->subOrder->shippingCompanyId ?? null,
            'claim_type'           => $data['claim_type'],
            'description'          => $data['description'],
            'claimed_amount' => (int) round($data['claimed_amount'] * 100),
            'evidence_files'       => $data['evidence_files'] ?? null,
        ], $vendor);

        return redirect()
            ->route('partner.claims.show', $claim)
            ->with('success', __('partner.claims.messages.submitted', ['number' => $claim->claim_number]));
    }

    public function show(CarrierClaim $claim): View
    {
        $vendor = auth('vendor')->user();

        // Ensure this claim belongs to the authenticated vendor
        abort_unless(
            $claim->shipment?->subOrder?->vendor_id === $vendor->id,
            403
        );

        $claim->load(['shipment.subOrder', 'resolvedBy']);

        // Intentionally NOT loading shippingCompany — vendor CAN see carrier identity
        $claim->load('shippingCompany');

        return view('partner.claims.show', compact('claim'));
    }
}
