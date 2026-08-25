<?php

namespace App\Http\Controllers\Api\TravelAgencyPortal;

use App\Enums\VendorCampaignInvitationStatus;
use App\Enums\VendorCampaignOfferStatus;
use App\Http\Controllers\Controller;
use App\Models\TravelAgencyCampaignOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    private function agencyId(): string
    {
        return auth()->guard('travel_agencies')->user()->id;
    }

    /** GET /api/travel-agency/v1/campaigns */
    public function index(Request $request): JsonResponse
    {
        $agencyId = $this->agencyId();

        $query = TravelAgencyCampaignOffer::where('travel_agency_id', $agencyId)
            ->withCount([
                'invitations',
                'invitations as accepted_count' => fn ($q) => $q->where('status', VendorCampaignInvitationStatus::Accepted->value),
            ])
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest();

        $offers = $query->paginate(20);

        // Summary stats across all offers (not just current page)
        $allOffers = TravelAgencyCampaignOffer::where('travel_agency_id', $agencyId)
            ->withCount([
                'invitations',
                'invitations as accepted_count' => fn ($q) => $q->where('status', VendorCampaignInvitationStatus::Accepted->value),
            ])
            ->get();

        $conversions = TravelAgencyCampaignOffer::where('travel_agency_id', $agencyId)
            ->join('travel_agency_campaign_invitations', 'travel_agency_campaign_offers.id', '=', 'travel_agency_campaign_invitations.travel_agency_campaign_offer_id')
            ->join('marketer_campaigns', 'travel_agency_campaign_invitations.resulting_campaign_id', '=', 'marketer_campaigns.id')
            ->join('marketer_campaign_conversions', 'marketer_campaigns.id', '=', 'marketer_campaign_conversions.campaign_id')
            ->count('marketer_campaign_conversions.id');

        $stats = [
            'draft'       => $allOffers->where('status', VendorCampaignOfferStatus::Draft)->count(),
            'active'      => $allOffers->where('status', VendorCampaignOfferStatus::Active)->count(),
            'invited'     => $allOffers->sum('invitations_count'),
            'accepted'    => $allOffers->sum('accepted_count'),
            'conversions' => $conversions,
        ];

        return response()->json([
            'stats' => $stats,
            'campaigns' => $offers->map(fn ($o) => [
                'id'                       => $o->id,
                'name'                     => $o->name,
                'status'                   => $o->status,
                'campaign_type'            => $o->campaign_type,
                'commission_type'          => $o->commission_type,
                'offered_commission_rate'  => $o->offered_commission_rate,
                'starts_at'                => $o->starts_at?->toDateString(),
                'ends_at'                  => $o->ends_at?->toDateString(),
                'invitations_count'        => $o->invitations_count,
                'accepted_count'           => $o->accepted_count,
                'created_at'               => $o->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $offers->currentPage(),
                'last_page'    => $offers->lastPage(),
                'total'        => $offers->total(),
            ],
        ]);
    }

    /** GET /api/travel-agency/v1/campaigns/{id} */
    public function show(string $id): JsonResponse
    {
        $offer = TravelAgencyCampaignOffer::where('id', $id)
            ->where('travel_agency_id', $this->agencyId())
            ->with([
                'packages.package:id,title_ar,title_en,destination_country,destination_city,currency,price',
                'invitations.marketer:id,name,store_name,marketer_type',
                'invitations.resultingCampaign:id,status',
            ])
            ->firstOrFail();

        $invitationStats = [
            'invited'  => $offer->invitations->count(),
            'accepted' => $offer->invitations->where('status', VendorCampaignInvitationStatus::Accepted)->count(),
            'declined' => $offer->invitations->whereIn('status', [
                VendorCampaignInvitationStatus::Declined,
                VendorCampaignInvitationStatus::Expired,
                VendorCampaignInvitationStatus::Revoked,
            ])->count(),
        ];

        return response()->json([
            'campaign' => [
                'id'                      => $offer->id,
                'name'                    => $offer->name,
                'description'             => $offer->description,
                'requirements'            => $offer->requirements,
                'status'                  => $offer->status,
                'campaign_type'           => $offer->campaign_type,
                'commission_type'         => $offer->commission_type,
                'offered_commission_rate' => $offer->offered_commission_rate,
                'attribution_model'       => $offer->attribution_model,
                'starts_at'               => $offer->starts_at?->toDateString(),
                'ends_at'                 => $offer->ends_at?->toDateString(),
                'invitation_deadline'     => $offer->invitation_deadline?->toDateString(),
                'packages'                => $offer->packages->map(fn ($p) => [
                    'id'                  => $p->package?->id,
                    'title'               => $p->package?->title_ar ?: $p->package?->title_en,
                    'destination'         => trim(($p->package?->destination_city ?? '') . ' ' . ($p->package?->destination_country ?? '')),
                    'price'               => $p->package?->price,
                    'currency'            => $p->package?->currency,
                    'commission_override' => $p->commission_override,
                ]),
                'invitation_stats' => $invitationStats,
                'invitations' => $offer->invitations->map(fn ($i) => [
                    'id'              => $i->id,
                    'status'          => $i->status,
                    'marketer_name'   => $i->marketer?->store_name ?: $i->marketer?->name,
                    'marketer_type'   => $i->marketer?->marketer_type,
                    'responded_at'    => $i->responded_at?->toIso8601String(),
                    'campaign_status' => $i->resultingCampaign?->status,
                ]),
                'created_at' => $offer->created_at?->toIso8601String(),
            ],
        ]);
    }
}
