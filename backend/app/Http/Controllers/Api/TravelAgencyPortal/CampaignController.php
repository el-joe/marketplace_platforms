<?php

namespace App\Http\Controllers\Api\TravelAgencyPortal;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Read-only view of the trip campaigns (MarketerCampaign, owner_type travel_agency) the agency owns. */
class CampaignController extends Controller
{
    private function agencyId(): string
    {
        return auth()->guard('travel_agencies')->user()->id;
    }

    private function agencyCampaigns()
    {
        return MarketerCampaign::where('owner_type', MarketerCampaign::OWNER_TRAVEL_AGENCY)
            ->where('owner_id', $this->agencyId());
    }

    /** GET /api/travel-agency/v1/campaigns */
    public function index(Request $request): JsonResponse
    {
        $campaigns = $this->agencyCampaigns()
            ->with('travelPackage:id,title_ar,title_en,currency,price')
            ->withCount(['invitations', 'invitations as accepted_count' => fn ($q) => $q->where('status', 'accepted')])
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%'.$request->search.'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        $all = $this->agencyCampaigns();

        return response()->json([
            'stats' => [
                'total' => (clone $all)->count(),
                'active' => (clone $all)->whereIn('status', ['active', 'auto_approved'])->count(),
                'commission_spent' => round((float) (clone $all)->sum('commission_budget_spent'), 2),
            ],
            'campaigns' => $campaigns->map(fn (MarketerCampaign $c) => $this->summary($c)),
            'meta' => [
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
                'total' => $campaigns->total(),
            ],
        ]);
    }

    /** GET /api/travel-agency/v1/campaigns/{id} */
    public function show(string $id): JsonResponse
    {
        $campaign = $this->agencyCampaigns()
            ->with(['travelPackage:id,title_ar,title_en,currency,price', 'invitations.marketer:id,name'])
            ->findOrFail($id);

        return response()->json([
            'campaign' => $this->summary($campaign) + [
                'open_to_affiliates' => (bool) $campaign->open_to_affiliates,
                'influencer_fixed_reserved' => (float) $campaign->influencer_fixed_reserved,
                'invitations' => $campaign->invitations->map(fn ($i) => [
                    'id' => $i->id,
                    'status' => $i->status,
                    'marketer_name' => $i->marketer?->name,
                    'total_conversions' => (int) $i->total_conversions,
                    'responded_at' => $i->responded_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    private function summary(MarketerCampaign $c): array
    {
        return [
            'id' => $c->id,
            'title' => $c->title,
            'status' => $c->status,
            'trip' => $c->travelPackage?->title_ar ?: $c->travelPackage?->title_en,
            'currency' => $c->currency,
            'max_commission_budget' => (float) $c->max_commission_budget,
            'commission_budget_spent' => (float) $c->commission_budget_spent,
            'ends_at' => $c->ends_at?->toDateString(),
            'invitations_count' => $c->invitations_count ?? null,
            'accepted_count' => $c->accepted_count ?? null,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }
}
