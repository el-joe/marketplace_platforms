<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Enums\TravelPackageStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use App\Models\MarketerCampaign;
use App\Models\TravelPackage;
use App\Services\Marketer\CampaignWizardService;
use App\Services\Marketer\MarketerCommissionCalculator;
use App\Services\MarketerCampaignQuoteService;
use App\Services\MarketerCampaignService;
use App\Support\Marketer\CampaignBudgetRules;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSubject;
use App\Support\Money;
use App\Traits\HasExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Travel agency "Campaigns": the agency promotes its own trips through the same MarketerCampaign engine, wizard and
 * money rules as a product or classified vendor (owner_type travel_agency, owner_id = the agency, no vendor).
 * Affiliates and brokers earn on confirmed bookings; influencers are paid their fixed amount when the campaign completes.
 */
class CampaignController extends Controller
{
    use HasExport;
    use ResolvesTravelAgency;

    public function __construct(
        private readonly MarketerCampaignService $campaigns,
        private readonly CampaignWizardService $wizard,
        private readonly MarketerCampaignQuoteService $quotes,
    ) {}

    private function ownCampaigns()
    {
        return MarketerCampaign::where('owner_type', MarketerCampaign::OWNER_TRAVEL_AGENCY)
            ->where('owner_id', $this->agencyId());
    }

    private function ownCampaign(string $id): MarketerCampaign
    {
        return $this->ownCampaigns()->findOrFail($id);
    }

    /** The agency's trips that can be promoted right now (same rule MarketerCampaignService enforces on submit). */
    private function eligibleTrips()
    {
        return TravelPackage::where('travel_agency_id', $this->agencyId())
            ->where('status', TravelPackageStatus::Active)
            ->where(fn ($q) => $q->whereNull('departure_date')->orWhereDate('departure_date', '>=', today()))
            ->where(fn ($q) => $q->whereNull('available_seats')->orWhereColumn('available_seats', '>', 'seats_booked'))
            ->orderBy('departure_date')
            ->get();
    }

    private function ownTrip(string $id): CampaignSubject
    {
        $trip = TravelPackage::where('travel_agency_id', $this->agencyId())->with(['agency', 'media'])->find($id);
        abort_if(! $trip, 404, __('travel.campaigns.errors.trip_not_found'));

        return CampaignSubject::of($trip);
    }

    private function filtered(Request $request)
    {
        return $this->ownCampaigns()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->query('search').'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)
                    ->orWhereHas('travelPackage', fn ($t) => $t->where('title_en', 'like', $term)->orWhere('title_ar', 'like', $term)));
            });
    }

    public function index(Request $request): View
    {
        $campaigns = $this->filtered($request)
            ->with(['travelPackage:id,title_ar,title_en,slug,currency', 'country'])
            ->withCount('invitations')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $all = $this->ownCampaigns();
        $stats = [
            'total' => (clone $all)->count(),
            'active' => (clone $all)->whereIn('status', ['active', 'auto_approved'])->count(),
            'conversions' => (int) (clone $all)->withCount('conversions')->get()->sum('conversions_count'),
            'spent' => (clone $all)->sum('commission_budget_spent'),
        ];

        return view('travel-agency.campaigns.index', compact('campaigns', 'stats'));
    }

    public function export(Request $request): Response|StreamedResponse
    {
        $campaigns = $this->filtered($request)->with('travelPackage:id,title_ar,title_en')->withCount('invitations')->latest()->get();

        $headers = array_map(fn ($k) => __("travel.campaigns.export.$k"), ['campaign', 'trip', 'budget', 'used', 'marketers', 'status', 'created']);
        $rows = $campaigns->map(fn (MarketerCampaign $c) => [
            $c->title ?: '#'.substr($c->id, 0, 8),
            $c->travelPackage?->title_ar ?: $c->travelPackage?->title_en,
            $c->budgetLabel(),
            Money::format($c->budgetUsed()).' '.$c->currency,
            $c->invitations_count,
            $c->status,
            $c->created_at?->toDateString(),
        ]);

        $filename = 'campaigns-'.now()->toDateString();

        return match ($request->input('format', 'csv')) {
            'excel' => $this->exportExcel($filename, $headers, $rows),
            'word' => $this->exportWord($filename, __('travel.campaigns.export.sheet_title'), $rows),
            'csv' => $this->exportCsv($filename, $headers, $rows),
            default => abort(400, __('travel.export.invalid_format')),
        };
    }

    /** Step 0: pick the trip to promote. */
    public function create(): View
    {
        return view('travel-agency.campaigns.select-trip', ['trips' => $this->eligibleTrips()]);
    }

    /** The shared wizard, filled for one trip. */
    public function createForTrip(string $package): View|RedirectResponse
    {
        $subject = $this->ownTrip($package);

        try {
            $this->campaigns->assertEligibleSubject(CampaignOwner::travelAgency($this->agencyId()), $subject->source());
        } catch (\RuntimeException $e) {
            return redirect()->route('travel-agency.campaigns.create')->with('error', $e->getMessage());
        }

        $influencers = $this->quotes->influencerOptions($subject);
        $affiliateCount = $this->quotes->openAudienceCount($subject);
        $quote = $this->quotes->withWallet($this->quotes->quote($subject, []), $this->agencyId(), $subject->currency, 'travel_agency');

        return view('travel-agency.campaigns.create', compact('subject', 'influencers', 'affiliateCount', 'quote'));
    }

    /** Live cost summary of the wizard: everything is computed server-side. */
    public function quote(Request $request): JsonResponse
    {
        $request->validate([
            'trip_id' => ['required', 'uuid'],
            'marketer_ids' => ['nullable', 'array'],
            'marketer_ids.*' => ['uuid'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'open_to_affiliates' => ['nullable', 'boolean'],
        ]);

        $subject = $this->ownTrip($request->input('trip_id'));

        return response()->json($this->quotes->withWallet(
            $this->quotes->quote(
                $subject,
                array_values(array_filter((array) $request->input('marketer_ids', []), 'is_string')),
                (float) $request->input('budget', 0),
                $request->boolean('open_to_affiliates'),
            ),
            $this->agencyId(),
            $subject->currency,
            'travel_agency',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'travel_package_id' => ['required', 'uuid'],
            'marketer_ids' => ['nullable', 'array'],
            'marketer_ids.*' => ['uuid'],
            'max_commission_budget' => CampaignBudgetRules::budgetRules(),
            'open_to_affiliates' => ['nullable', 'boolean'],
            'ends_at' => CampaignBudgetRules::endsAtRules($request),
            'selected_ad_types' => ['nullable', 'array'],
            'selected_ad_types.*' => ['string', 'in:story,post,video'],
            'vendor_ad_notes' => ['nullable', 'string', 'max:2000'],
        ], CampaignBudgetRules::endsAtMessages());

        $subject = $this->ownTrip($request->input('travel_package_id'));

        try {
            ['campaign' => $campaign, 'quote' => $quote] = $this->wizard->submit(CampaignOwner::travelAgency($this->agencyId()), $subject, $request->all());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', __('travel.campaigns.flash.create_failed', ['error' => $e->getMessage()]));
        }

        return redirect()->route('travel-agency.campaigns.show', $campaign->id)
            ->with('success', __('travel.campaigns.flash.created', [
                'total' => Money::format($quote['campaign_grand_total']), 'currency' => $quote['currency'],
            ]));
    }

    public function show(string $id): View
    {
        $campaign = $this->ownCampaign($id);
        $campaign->load(['country', 'travelPackage', 'invitations.marketer.marketerProfile', 'conversions.travelBooking']);

        // How each marketer is paid, with the trip's current price as the worked example (read-only, from the calculator).
        $calculator = app(MarketerCommissionCalculator::class);
        $examplePrice = (float) ($campaign->travelPackage?->price ?? 0);
        $calculations = $campaign->invitations
            ->filter(fn ($i) => $i->marketer && in_array($i->status, ['pending', 'accepted'], true))
            ->mapWithKeys(fn ($i) => [$i->id => $calculator->compute($i->marketer, $campaign, $examplePrice, 1, $i)]);

        return view('travel-agency.campaigns.show', compact('campaign', 'calculations', 'examplePrice'));
    }

    public function pause(string $id): RedirectResponse
    {
        try {
            $this->campaigns->pauseByOwner($this->ownCampaign($id));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('travel.campaigns.flash.paused'));
    }

    public function resume(string $id): RedirectResponse
    {
        try {
            $this->campaigns->resumeByOwner($this->ownCampaign($id));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('travel.campaigns.flash.resumed'));
    }

    public function cancel(string $id): RedirectResponse
    {
        $campaign = $this->ownCampaign($id);

        if (in_array($campaign->status, MarketerCampaignService::TERMINAL_STATUSES, true)) {
            return back()->with('error', __('travel.campaigns.errors.cannot_cancel'));
        }

        $this->campaigns->cancelCampaign($campaign);

        return back()->with('success', __('travel.campaigns.flash.cancelled'));
    }
}
