<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Enums\VendorCampaignInvitationStatus;
use App\Enums\VendorCampaignOfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use App\Models\Admin;
use App\Models\MarketerCampaign;
use App\Models\TravelAgencyCampaignInvitation;
use App\Models\TravelAgencyCampaignOffer;
use App\Models\TravelAgencyCampaignOfferPackage;
use App\Models\TravelPackage;
use App\Notifications\Admin\TravelAgencyCampaignOfferSubmitted;
use App\Notifications\Marketer\TravelAgencyCampaignInvitationReceived;
use App\Traits\HasExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignController extends Controller
{
    use ResolvesTravelAgency;
    use HasExport;

    private function filteredOffersQuery(Request $request)
    {
        $query = TravelAgencyCampaignOffer::where('travel_agency_id', $this->agencyId());

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query;
    }

    public function index(Request $request): View
    {
        $agencyId = $this->agencyId();

        $offers = $this->filteredOffersQuery($request)
            ->withCount(['invitations', 'invitations as accepted_count' => fn($q) => $q->where('status', VendorCampaignInvitationStatus::Accepted->value)])
            ->latest()
            ->get();

        $stats = [
            'draft'       => $offers->where('status', VendorCampaignOfferStatus::Draft)->count(),
            'active'      => $offers->where('status', VendorCampaignOfferStatus::Active)->count(),
            'invited'     => $offers->sum('invitations_count'),
            'accepted'    => $offers->sum('accepted_count'),
            'conversions' => TravelAgencyCampaignOffer::where('travel_agency_id', $agencyId)
                ->join('travel_agency_campaign_invitations', 'travel_agency_campaign_offers.id', '=', 'travel_agency_campaign_invitations.travel_agency_campaign_offer_id')
                ->join('marketer_campaigns', 'travel_agency_campaign_invitations.resulting_campaign_id', '=', 'marketer_campaigns.id')
                ->join('marketer_conversions', 'marketer_campaigns.id', '=', 'marketer_conversions.campaign_id')
                ->count('marketer_conversions.id'),
        ];

        return view('travel-agency.campaigns.index', compact('offers', 'stats'));
    }

    public function export(Request $request): Response|StreamedResponse
    {
        $offers = $this->filteredOffersQuery($request)
            ->withCount('invitations')
            ->latest()
            ->get();

        $headers = [
            __('travel.campaigns.export.campaign'),
            __('travel.campaigns.export.type'),
            __('travel.campaigns.export.marketers'),
            __('travel.campaigns.export.status'),
            __('travel.campaigns.export.start'),
            __('travel.campaigns.export.end'),
        ];

        $rows = $offers->map(fn (TravelAgencyCampaignOffer $offer) => [
            $offer->name,
            $offer->campaign_type->value ?? $offer->campaign_type,
            $offer->invitations_count,
            $offer->status->value ?? $offer->status,
            $offer->starts_at?->toDateString(),
            $offer->ends_at?->toDateString(),
        ]);

        $filename = 'campaigns-' . now()->toDateString();
        $format = $request->input('format', 'csv');

        return match ($format) {
            'excel' => $this->exportExcel($filename, $headers, $rows),
            'word'  => $this->exportWord($filename, __('travel.campaigns.export.sheet_title'), $rows),
            'csv'   => $this->exportCsv($filename, $headers, $rows),
            default => abort(400, __('travel.export.invalid_format')),
        };
    }

    public function create(): View
    {
        return view('travel-agency.campaigns.create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $agencyId = $this->agencyId();

        $validated = $request->validate([
            'name'                                  => 'required|string|max:255',
            'description'                           => 'nullable|string|max:2000',
            'requirements'                          => 'nullable|string|max:2000',
            'campaign_type'                         => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\CampaignType::class)],
            'offered_commission_rate'               => 'required|numeric|min:0|max:100',
            'commission_type'                       => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\CommissionType::class)],
            'budget_per_marketer_display'           => 'nullable|numeric|min:0',
            'total_budget_display'                  => 'nullable|numeric|min:0',
            'starts_at'                             => 'required|date|after_or_equal:today',
            'ends_at'                               => 'required|date|after:starts_at',
            'invitation_deadline'                   => 'nullable|date|before:starts_at',
            'attribution_model'                     => 'required|in:last_click,first_click,linear',
            'whatsapp_sharing_enabled'              => 'boolean',
            'package_ids'                           => 'required|array|min:1',
            'package_ids.*'                         => 'uuid',
            'commission_overrides'                  => 'nullable|array',
            'commission_overrides.*.package_id'     => 'uuid',
            'commission_overrides.*.rate'           => 'numeric|min:0|max:100',
        ]);

        // Validate all package_ids belong to this travel agency
        $packageCount = TravelPackage::where('travel_agency_id', $agencyId)
            ->whereIn('id', $validated['package_ids'])
            ->count();

        if ($packageCount !== count($validated['package_ids'])) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => __('travel.campaigns.package_not_owned')], 422);
            }
            return back()->withErrors(['package_ids' => __('travel.campaigns.package_not_owned')])->withInput();
        }

        $overridesMap = collect($validated['commission_overrides'] ?? [])
            ->keyBy('package_id');

        $offer = null;

        DB::transaction(function () use ($validated, $agencyId, $overridesMap, &$offer) {
            $offer = TravelAgencyCampaignOffer::create([
                'travel_agency_id'           => $agencyId,
                'name'                       => $validated['name'],
                'description'                => $validated['description'] ?? null,
                'requirements'               => $validated['requirements'] ?? null,
                'campaign_type'              => $validated['campaign_type'],
                'offered_commission_rate'    => $validated['offered_commission_rate'],
                'commission_type'            => $validated['commission_type'],
                'budget_per_marketer'  => isset($validated['budget_per_marketer_display'])
                    ? (int) round($validated['budget_per_marketer_display'] * 100) : null,
                'total_budget'         => isset($validated['total_budget_display'])
                    ? (int) round($validated['total_budget_display'] * 100) : null,
                'starts_at'                  => $validated['starts_at'],
                'ends_at'                    => $validated['ends_at'],
                'invitation_deadline'        => $validated['invitation_deadline'] ?? null,
                'attribution_model'          => $validated['attribution_model'],
                'whatsapp_sharing_enabled'   => $validated['whatsapp_sharing_enabled'] ?? false,
                'status'                     => VendorCampaignOfferStatus::Draft,
            ]);

            foreach ($validated['package_ids'] as $i => $packageId) {
                TravelAgencyCampaignOfferPackage::create([
                    'travel_agency_campaign_offer_id' => $offer->id,
                    'travel_package_id'               => $packageId,
                    'position'                         => $i + 1,
                    'commission_override'              => $overridesMap->get($packageId)['rate'] ?? null,
                ]);
            }
        });

        return redirect()->route('travel-agency.campaigns.show', $offer->id)
            ->with('success', __('travel.campaigns.offer_created'));
    }

    public function show(TravelAgencyCampaignOffer $offer): View
    {
        abort_if($offer->travel_agency_id !== $this->agencyId(), 404);

        $offer->load([
            'packages.package',
            'invitations.marketer',
            'invitations.resultingCampaign',
        ]);

        $invitationStats = [
            'invited'  => $offer->invitations->count(),
            'accepted' => $offer->invitations->where('status', VendorCampaignInvitationStatus::Accepted)->count(),
            'declined' => $offer->invitations->whereIn('status', [VendorCampaignInvitationStatus::Declined, VendorCampaignInvitationStatus::Expired, VendorCampaignInvitationStatus::Revoked])->count(),
        ];

        return view('travel-agency.campaigns.show', compact('offer', 'invitationStats'));
    }

    public function submitForReview(TravelAgencyCampaignOffer $offer): JsonResponse
    {
        abort_if($offer->travel_agency_id !== $this->agencyId(), 404);

        if ($offer->status !== VendorCampaignOfferStatus::Draft) {
            return response()->json(['success' => false, 'message' => __('travel.campaigns.submit_only_draft')], 422);
        }

        $offer->update(['status' => VendorCampaignOfferStatus::PendingAdmin]);

        Notification::send(Admin::permission('campaign_offers.view')->get(), new TravelAgencyCampaignOfferSubmitted($offer));

        return response()->json(['success' => true, 'message' => __('travel.campaigns.submitted_success'), 'status' => VendorCampaignOfferStatus::PendingAdmin->value]);
    }

    public function pauseOffer(TravelAgencyCampaignOffer $offer): JsonResponse
    {
        abort_if($offer->travel_agency_id !== $this->agencyId(), 404);

        if ($offer->status !== VendorCampaignOfferStatus::Active) {
            return response()->json(['success' => false, 'message' => __('travel.campaigns.not_active')], 422);
        }

        $offer->update(['status' => VendorCampaignOfferStatus::Paused]);

        MarketerCampaign::whereIn('id', $offer->invitations()
            ->where('status', VendorCampaignInvitationStatus::Accepted->value)
            ->pluck('resulting_campaign_id'))
            ->update(['status' => 'paused']);

        return response()->json(['success' => true, 'message' => __('travel.campaigns.paused_success'), 'status' => VendorCampaignOfferStatus::Paused->value]);
    }

    public function resumeOffer(TravelAgencyCampaignOffer $offer): JsonResponse
    {
        abort_if($offer->travel_agency_id !== $this->agencyId(), 404);

        if ($offer->status !== VendorCampaignOfferStatus::Paused) {
            return response()->json(['success' => false, 'message' => __('travel.campaigns.not_paused')], 422);
        }

        $offer->update(['status' => VendorCampaignOfferStatus::Active]);

        MarketerCampaign::whereIn('id', $offer->invitations()
            ->where('status', VendorCampaignInvitationStatus::Accepted->value)
            ->pluck('resulting_campaign_id'))
            ->update(['status' => 'active']);

        return response()->json(['success' => true, 'message' => __('travel.campaigns.resumed_success'), 'status' => VendorCampaignOfferStatus::Active->value]);
    }

    public function destroy(TravelAgencyCampaignOffer $offer): JsonResponse
    {
        abort_if($offer->travel_agency_id !== $this->agencyId(), 404);

        if ($offer->status !== VendorCampaignOfferStatus::Draft) {
            return response()->json(['success' => false, 'message' => __('travel.campaigns.delete_only_draft')], 422);
        }

        $offer->delete();

        return response()->json(['success' => true, 'message' => __('travel.campaigns.deleted_success')]);
    }

    public function invite(Request $request, TravelAgencyCampaignOffer $offer): JsonResponse
    {
        abort_if($offer->travel_agency_id !== $this->agencyId(), 404);

        if (!in_array($offer->status, [VendorCampaignOfferStatus::Active, VendorCampaignOfferStatus::Draft, VendorCampaignOfferStatus::PendingAdmin], true)) {
            return response()->json(['success' => false, 'message' => __('travel.campaigns.invite_forbidden_status')], 422);
        }

        $validated = $request->validate([
            'marketer_ids'   => 'required|array|min:1|max:50',
            'marketer_ids.*' => 'uuid|exists:vendors,id',
            'vendor_note'    => 'nullable|string|max:1000',
        ]);

        $activeMarketers = \App\Models\Vendor::whereIn('id', $validated['marketer_ids'])
            ->whereNotNull('marketer_type')
            ->pluck('id')
            ->all();

        $skipped = array_diff($validated['marketer_ids'], $activeMarketers);
        $created = 0;

        foreach ($activeMarketers as $marketerId) {
            $invitation = TravelAgencyCampaignInvitation::firstOrCreate(
                ['travel_agency_campaign_offer_id' => $offer->id, 'marketer_id' => $marketerId],
                [
                    'status'      => VendorCampaignInvitationStatus::Pending,
                    'vendor_note' => $validated['vendor_note'] ?? null,
                    'expires_at'  => $offer->invitation_deadline,
                ]
            );

            if ($invitation->wasRecentlyCreated) {
                $created++;
                // Only fan-out immediately when the offer is already active;
                // pending_admin offers send invitations on approval instead.
                if ($offer->status === VendorCampaignOfferStatus::Active) {
                    Notification::send($invitation->marketer, new TravelAgencyCampaignInvitationReceived($invitation));
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('travel.campaigns.invited_success', ['count' => $created]),
            'skipped' => count($skipped),
        ]);
    }

    public function revokeInvitation(TravelAgencyCampaignInvitation $invitation): JsonResponse
    {
        abort_if($invitation->offer->travel_agency_id !== $this->agencyId(), 404);

        if ($invitation->status !== VendorCampaignInvitationStatus::Pending) {
            return response()->json(['success' => false, 'message' => __('travel.campaigns.withdraw_forbidden_responded')], 422);
        }

        $invitation->update(['status' => VendorCampaignInvitationStatus::Revoked]);

        return response()->json(['success' => true, 'message' => __('travel.campaigns.invite_withdrawn')]);
    }

    public function searchMarketers(Request $request): JsonResponse
    {
        $q    = $request->input('q', '');
        $type = $request->input('type');

        return response()->json(
            \App\Models\Vendor::whereNotNull('marketer_type')
                ->where(fn($query) => $query
                    ->where('name', 'like', "%{$q}%")
                    ->orWhere('store_name', 'like', "%{$q}%"))
                ->when($type, fn($q, $t) => $q->where('marketer_type', $t))
                ->select(['id', 'name', 'store_name', 'marketer_type', 'commission_rate'])
                ->limit(20)
                ->get()
                ->map(fn($m) => [
                    'id'             => $m->id,
                    'name'           => $m->store_name ?: $m->name,
                    'type'           => ucfirst($m->marketer_type ?? ''),
                    'avatar_initial' => mb_substr($m->store_name ?: $m->name, 0, 1),
                ])
        );
    }

    public function searchPackages(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $packages = TravelPackage::where('travel_agency_id', $this->agencyId())
            ->when($q !== '', fn($query) => $query->where(fn($sub) => $sub
                ->where('title_en', 'like', "%{$q}%")
                ->orWhere('title_ar', 'like', "%{$q}%")
                ->orWhere('destination_country', 'like', "%{$q}%")
                ->orWhere('destination_city', 'like', "%{$q}%")))
            ->whereIn('status', ['active', 'pending_review'])
            ->limit(20)
            ->get(['id', 'title_ar', 'title_en', 'destination_country', 'destination_city', 'price', 'currency', 'departure_date', 'return_date']);

        return response()->json($packages->map(fn($p) => [
            'id'          => $p->id,
            'name'        => $p->title_ar ?: $p->title_en,
            'destination' => trim(($p->destination_city ? $p->destination_city . '، ' : '') . $p->destination_country),
            'price'       => $p->priceFormatted(),
            'dates'       => $p->departure_date?->format('d M Y') . ' — ' . $p->return_date?->format('d M Y'),
        ]));
    }
}
