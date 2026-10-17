<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCampaignWhatsAppNotificationJob;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCampaignSample;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Services\Campaign\CampaignSampleService;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Marketer\MarketerCommissionCalculator;
use App\Services\MarketerCampaignQuoteService;
use App\Services\MarketerCampaignService;
use App\Services\ReferralCodeResolver;
use App\Support\Marketer\CampaignBudgetRules;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketerCampaignController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private MarketerCampaignService $service) {}

    public function index(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.view'), 403);

        $campaigns = MarketerCampaign::with([
            'vendor', 'country',
            'vendorListing.productVariant.product',
            'adminListing.productVariant.product',
            'travelPackage', 'classifiedListing',
            'marketerListing.productVariant.product', 'ownerMarketer', 'promoter',
            'invitations.marketer',
        ])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->source, fn ($q) => match ($request->source) {
                'peer' => $q->whereNotNull('marketer_listing_id'),
                'admin' => $q->whereNotNull('admin_listing_id'),
                'vendor' => $q->whereNotNull('vendor_listing_id'),
                default => $q,
            })
            ->when($request->country_id, fn ($q) => $q->where('country_id', $request->country_id))
            ->latest()
            ->paginate(20);

        $pendingCount = MarketerCampaign::where('status', 'pending_admin')->count();
        $countries = Country::orderBy('name_en')->get();

        return view('admin.marketer_campaigns.index', compact('campaigns', 'pendingCount', 'countries'));
    }

    public function show(MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.view'), 403);
        $marketerCampaign->load([
            'vendor', 'country',
            'vendorListing.productVariant.product',
            'adminListing.productVariant.product',
            'marketerListing.productVariant.product', 'ownerMarketer', 'promoter',
            'invitations.marketer.marketerProfile',
            'conversions.order',
            'samples.invitation.marketer',
            'samples.customAttributeValues',
        ]);

        $referralChains = app(ReferralCodeResolver::class)->chainsFor($marketerCampaign);

        // Marketers the vendor picked. Invitations only exist once the campaign is approved, so until then this
        // (plus the live quote of fees/commission/samples) is what the admin reviews.
        $requestedIds = array_values(array_filter((array) $marketerCampaign->requested_marketer_vendor_ids));
        $requestedMarketers = $requestedIds
            ? Marketer::whereIn('id', $requestedIds)->with(['marketerJobs', 'marketerProfile'])->get()
            : collect();

        $requestedQuote = null;
        $quoteRows = collect();
        if ($requestedMarketers->isNotEmpty() && $marketerCampaign->vendorListing) {
            $requestedQuote = app(MarketerCampaignQuoteService::class)->quote(
                $marketerCampaign->vendorListing,
                $requestedIds,
                (float) $marketerCampaign->max_commission_budget,
                (bool) $marketerCampaign->open_to_affiliates
            );
            $quoteRows = collect($requestedQuote['rows'])->keyBy('id');
        }

        // How each invited marketer is paid, with the promoted item's current price as the worked example.
        $calculator = app(MarketerCommissionCalculator::class);
        $examplePrice = (float) ($marketerCampaign->vendorListing?->price ?? $marketerCampaign->adminListing?->price ?? 0);
        $calculations = $marketerCampaign->invitations
            ->filter(fn ($i) => $i->marketer && in_array($i->status, ['pending', 'accepted'], true))
            ->mapWithKeys(fn ($i) => [$i->id => $calculator->compute($i->marketer, $marketerCampaign, $examplePrice, 1, $i)]);

        return view('admin.marketer_campaigns.show', compact(
            'marketerCampaign', 'referralChains', 'calculations', 'examplePrice',
            'requestedMarketers', 'requestedQuote', 'quoteRows'
        ));
    }

    public function approve(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.approve'), 403);

        abort_unless($marketerCampaign->status === 'pending_admin', 422,
            'هذه الحملة لا يمكن قبولها في حالتها الحالية.');

        try {
            $this->service->approveCampaign($marketerCampaign, auth()->guard('admin')->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم قبول الحملة وأصبحت نشطة.');
    }

    public function reject(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.reject'), 403);
        $request->validate(['rejection_reason' => 'required|string|max:500']);

        $this->service->rejectCampaign(
            $marketerCampaign,
            auth()->guard('admin')->user(),
            $request->rejection_reason
        );

        return back()->with('success', 'تم رفض الحملة.');
    }

    public function updateSampleStatus(
        Request $request,
        MarketerCampaign $marketerCampaign,
        MarketerCampaignSample $sample
    ) {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.approve'), 403);

        abort_unless($sample->campaign_id === $marketerCampaign->id, 403);

        $request->validate([
            'status' => 'required|in:pending,dispatched,delivered,returned',
        ]);

        $allowed = ['pending' => ['dispatched'], 'dispatched' => ['delivered'], 'delivered' => ['returned'], 'returned' => [], 'cancelled' => []];
        abort_unless(in_array($request->status, $allowed[$sample->status] ?? [], true), 422, 'انتقال حالة العينة غير مسموح.');

        // An influencer's sample can only go out once he accepted the invitation
        // (a pending / rejected / timed-out invitation never receives stock).
        if ($request->status === 'dispatched' && $sample->invitation_id) {
            abort_unless($sample->invitation?->status === 'accepted', 422, __('admin.marketer_campaigns.sample_invitation_not_accepted'));
        }

        // Dispatch = stock-out like a normal sale + admin commission snapshot,
        // both exactly once (a repeated request is a no-op).
        if ($request->status === 'dispatched') {
            try {
                app(CampaignSampleService::class)->dispatch($sample);
            } catch (InsufficientStockException $e) {
                return back()->with('error', $e->getMessage());
            }

            return back()->with('success', 'تم تحديث حالة العينة.');
        }

        $data = ['status' => $request->status];
        if ($request->status === 'delivered' && ! $sample->delivered_at) {
            $data['delivered_at'] = now();
        }

        $sample->update($data);

        return back()->with('success', 'تم تحديث حالة العينة.');
    }

    /**
     * P10 follow-up: re-send the WhatsApp invitation (same signed accept/reject links) of a PENDING invitation.
     * Cooldown: `marketer_whatsapp_resend_cooldown_minutes` (default 10) since the last resend, enforced with one
     * atomic conditional UPDATE so two concurrent clicks cannot both pass.
     */
    public function resendInvitationWhatsApp(MarketerCampaign $marketerCampaign, MarketerCampaignInvitation $invitation)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.approve'), 403);
        abort_unless($invitation->campaign_id === $marketerCampaign->id, 404);

        if (! $invitation->isPending() || $invitation->isTimedOut()) {
            return back()->with('error', __('admin.marketer_campaigns.whatsapp_resend_not_pending'));
        }

        $cooldown = max(0, (int) setting('marketer_whatsapp_resend_cooldown_minutes', 10));
        $claimed = MarketerCampaignInvitation::whereKey($invitation->id)
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('whatsapp_resent_at')->orWhere('whatsapp_resent_at', '<=', now()->subMinutes($cooldown)))
            ->update(['whatsapp_resent_at' => now(), 'whatsapp_resend_count' => DB::raw('whatsapp_resend_count + 1')]);

        if (! $claimed) {
            $readyAt = $invitation->fresh()->whatsapp_resent_at?->copy()->addMinutes($cooldown);
            $wait = $readyAt ? max(1, (int) ceil(now()->diffInSeconds($readyAt, false) / 60)) : 1;

            return back()->with('error', __('admin.marketer_campaigns.whatsapp_resend_cooldown', ['minutes' => $wait]));
        }

        SendCampaignWhatsAppNotificationJob::dispatch($invitation->id, true);

        return back()->with('success', __('admin.marketer_campaigns.whatsapp_resend_queued'));
    }

    public function inviteMarketers(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.create'), 403);

        $request->validate([
            'marketer_ids' => ['required', 'array', 'min:1'],
            'marketer_ids.*' => ['uuid', 'distinct', 'exists:marketers,id'],
        ]);

        $result = $this->service->inviteMarketers($marketerCampaign, $request->marketer_ids);

        $message = count($result['invited']).' marketer(s) invited.';
        if (! empty($result['skipped'])) {
            $message .= ' '.count($result['skipped']).' skipped (already invited or inactive).';
        }

        return back()->with('success', $message);
    }

    public function create()
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.create'), 403);

        $vendors = Vendor::orderBy('store_name')->get();
        $marketers = Marketer::where('global_status', 'active')->orderBy('name')->get();
        $countries = Country::orderBy('name_en')->get();
        $vendorCountries = $vendors->mapWithKeys(fn ($v) => [$v->id => $v->country_id]);
        $countryCurrencies = Country::pluck('currency_code', 'id');

        $oldVendorListing = null;
        if (old('vendor_listing_id')) {
            $oldVendorListing = VendorListing::with('productVariant.product')
                ->find(old('vendor_listing_id'));
        }

        return view('admin.marketer_campaigns.create', compact(
            'vendors', 'marketers', 'countries', 'vendorCountries', 'countryCurrencies', 'oldVendorListing'
        ));
    }

    public function store(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.create'), 403);

        $data = $request->validate([
            // enhancement.md P-14 task 2: admin can now create a platform
            // (admin-listing) campaign without choosing a vendor —
            // vendor_id is only required when admin_listing_id is absent.
            'vendor_id' => 'required_without:admin_listing_id|nullable|uuid|exists:vendors,id',
            'vendor_listing_id' => 'nullable|uuid|exists:vendor_listings,id',
            'admin_listing_id' => 'nullable|uuid|exists:admin_listings,id',
            'marketer_ids' => 'required|array|min:1',
            'marketer_ids.*' => 'uuid|exists:marketers,id',
            'country_id' => 'required|uuid|exists:countries,id',
            'currency' => 'required|string|size:3|exists:currencies,code',
            // Max Commission Budget: affiliates / brokers are paid from it per sale, influencers' fixed amounts are
            // reserved from it. Optional: blank / 0 = unlimited, which needs an end date.
            'max_commission_budget' => CampaignBudgetRules::budgetRules(),
            'open_to_affiliates' => 'nullable|boolean',
            'ends_at' => CampaignBudgetRules::endsAtRules($request, after: 'after:now'),
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ], CampaignBudgetRules::endsAtMessages());
        $data['max_commission_budget'] = (float) ($data['max_commission_budget'] ?? 0);

        try {
            if (! empty($data['admin_listing_id'])) {
                $owner = CampaignOwner::platform();
                $source = CampaignSource::adminListing($data['admin_listing_id']);
            } else {
                $vendor = Vendor::findOrFail($data['vendor_id']);
                $owner = CampaignOwner::vendor($vendor);
                $source = CampaignSource::vendorListing($data['vendor_listing_id']);
            }

            $data['open_to_affiliates'] = $request->boolean('open_to_affiliates');

            $campaign = $this->service->createCampaign($owner, $source, $data);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.marketer-campaigns.show', $campaign)
            ->with('success', 'تم إنشاء الحملة بنجاح.');
    }

    public function searchVendorListings(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.create'), 403);

        $request->validate([
            'vendor_id' => 'required|uuid|exists:vendors,id',
            'search' => 'nullable|string',
        ]);

        $listings = VendorListing::with('productVariant.product')
            ->where('vendor_id', $request->vendor_id)
            ->where('status', 'active')
            ->whereIn('fulfillment_model', (array) setting('marketer_campaign_allowed_fulfilment_models', MarketerCampaignService::DEFAULT_ALLOWED_FULFILMENT_MODELS))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('productVariant.product', fn ($q2) => $q2->where('name_en', 'like', "%{$request->search}%")
            )
            )
            ->limit(20)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'text' => ($l->productVariant?->product?->name_en ?? '—').' — '.$l->id,
            ]);

        return response()->json(['results' => $listings]);
    }

    public function financials(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.view'), 403);

        $countryId = $request->input('country_id');
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $query = MarketerCampaign::query()
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->whereBetween('created_at', [$dateFrom, $dateTo.' 23:59:59']);

        // Per currency: campaign fees (the platform's revenue from campaigns), the budgets vendors committed, and how
        // much of them went to affiliates / brokers (spent) and influencers (reserved / paid).
        $summaryByCurrency = $query->clone()
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->select('currency')
            ->selectRaw('COUNT(*) as total_campaigns')
            ->selectRaw('SUM(max_commission_budget) as total_budget')
            ->selectRaw('SUM(commission_budget_spent) as commissions_spent')
            ->selectRaw('SUM(influencer_fixed_reserved) as influencer_fixed_total')
            ->selectRaw('SUM(CASE WHEN campaign_fee_status = \'paid\' THEN marketing_fee_total ELSE 0 END) as collected_fees')
            ->selectRaw('SUM(CASE WHEN campaign_fee_status = \'pending\' THEN marketing_fee_total ELSE 0 END) as pending_fees')
            ->groupBy('currency')
            ->get();

        $campaigns = $query->clone()
            ->with(['vendor', 'country'])
            ->withCount('conversions')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $countries = Country::orderBy('name_en')->get();

        return view('admin.marketer_campaigns.financials', compact(
            'summaryByCurrency', 'campaigns', 'countries', 'dateFrom', 'dateTo'
        ));
    }

    public function approveRequest(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.approve'), 403);

        $request->validate([
            'max_commission_budget' => ['required', 'numeric', 'min:0.01', 'max:999999999', 'decimal:0,2'],
        ]);

        abort_unless($marketerCampaign->status === 'marketer_requested', 422,
            'هذه الحملة لا يمكن قبول طلبها في حالتها الحالية.');

        try {
            $this->service->approveMarketerRequest($marketerCampaign, $request->only(['max_commission_budget']));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.marketer-campaigns.index')
            ->with('success', 'تم قبول طلب الماركتر وأصبحت الحملة نشطة.');
    }
}
