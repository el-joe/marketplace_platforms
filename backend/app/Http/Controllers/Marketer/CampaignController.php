<?php

namespace App\Http\Controllers\Marketer;

use App\Exceptions\PeerPromotionException;
use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerListing;
use App\Services\MarketerCampaignService;
use App\Support\Marketer\CampaignSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function __construct(private readonly MarketerCampaignService $campaignService) {}

    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    /**
     * Active campaigns — accepted invitations for campaigns that are active/auto_approved.
     */
    public function active(): View
    {
        $marketer = $this->marketer();

        $invitations = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'accepted')
            ->whereHas('campaign', fn ($q) => $q->whereIn('status', ['active', 'auto_approved']))
            ->with([
                'campaign.vendor',
                'campaign.vendorListing.productVariant' => fn ($q) => $q->withTrashed(),
                'campaign.vendorListing.productVariant.product' => fn ($q) => $q->withTrashed(),
                'campaign.adminListing' => fn ($q) => $q->withTrashed(),
                'campaign.adminListing.productVariant' => fn ($q) => $q->withTrashed(),
                'campaign.adminListing.productVariant.product' => fn ($q) => $q->withTrashed(),
                'campaign.country',
                'campaign.classifiedListing',
                'campaign.travelPackage',
                'marketer',
                'samples',
            ])
            ->withCount('conversions')
            ->withSum('conversions', 'commission_amount')
            ->latest()
            ->paginate(20);

        return view('marketer.campaigns.active', compact('marketer', 'invitations'));
    }

    /**
     * Finished campaigns — accepted invitations for done/cancelled/rejected campaigns.
     */
    public function finished(): View
    {
        $marketer = $this->marketer();

        $invitations = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'accepted')
            ->whereHas('campaign', fn ($q) => $q->whereIn('status', ['done', 'cancelled', 'rejected']))
            ->with([
                'campaign.vendor',
                'campaign.vendorListing.productVariant' => fn ($q) => $q->withTrashed(),
                'campaign.vendorListing.productVariant.product' => fn ($q) => $q->withTrashed(),
                'campaign.adminListing' => fn ($q) => $q->withTrashed(),
                'campaign.adminListing.productVariant' => fn ($q) => $q->withTrashed(),
                'campaign.adminListing.productVariant.product' => fn ($q) => $q->withTrashed(),
                'campaign.country',
                'campaign.classifiedListing',
                'campaign.travelPackage',
                'marketer',
            ])
            ->withCount('conversions')
            ->withSum('conversions', 'commission_amount')
            ->latest()
            ->paginate(20);

        return view('marketer.campaigns.finished', compact('marketer', 'invitations'));
    }

    /**
     * Campaign page of one of the marketer's own accepted invitations, with the per-order history of the
     * affiliate marketing fees their sales generate (own rows only; no vendor wallet or settlement details).
     */
    public function show(string $invitation): View
    {
        $marketer = $this->marketer();

        $invitation = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'accepted')
            ->whereKey($invitation)
            ->with([
                'campaign.vendor',
                'campaign.country',
                'campaign.classifiedListing',
                'campaign.travelPackage',
                'marketer',
                'campaign.vendorListing.productVariant' => fn ($q) => $q->withTrashed(),
                'campaign.vendorListing.productVariant.product' => fn ($q) => $q->withTrashed(),
                'campaign.adminListing' => fn ($q) => $q->withTrashed(),
                'campaign.adminListing.productVariant' => fn ($q) => $q->withTrashed(),
                'campaign.adminListing.productVariant.product' => fn ($q) => $q->withTrashed(),
            ])
            ->withCount('conversions')
            ->withSum('conversions', 'commission_amount')
            ->firstOrFail();

        return view('marketer.campaigns.show', compact('marketer', 'invitation'));
    }

    /**
     * P13 (R11): other influencers' own FBN products an influencer may promote. Excludes his own
     * listings, flags owners he already promoted this calendar month.
     */
    public function peerProducts(Request $request): View
    {
        $marketer = $this->marketer();
        $q = trim((string) $request->query('q', ''));

        $listings = MarketerListing::query()
            ->active()
            ->whereNull('source_listing_id')
            ->where('listing_category', 'product')
            ->where('marketer_id', '!=', $marketer->id)
            ->whereHas('marketer.marketerJobs', fn ($j) => $j->where('key', 'influencer'))
            ->whereHas('warehouseInventories', fn ($w) => $w->where('quantity_available', '>', 0))
            ->when($marketer->country_id, fn ($x) => $x->where('country_id', $marketer->country_id))
            ->when($q !== '', fn ($x) => $x->whereHas('productVariant.product', fn ($p) => $p
                ->where('name_ar', 'like', "%{$q}%")->orWhere('name_en', 'like', "%{$q}%")))
            ->with(['marketer', 'productVariant.product'])
            ->withSum('warehouseInventories as stock_available', 'quantity_available')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $promotedOwnerIds = MarketerCampaign::query()
            ->whereNotNull('marketer_listing_id')
            ->where('requested_by_marketer_id', $marketer->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->pluck('owner_id')
            ->all();

        return view('marketer.campaigns.peer-products', compact('marketer', 'listings', 'promotedOwnerIds', 'q'));
    }

    /**
     * P13 (R11): the owner influencer sees which influencers promote HIS products.
     */
    public function promotedByOthers(): View
    {
        $marketer = $this->marketer();

        $campaigns = MarketerCampaign::query()
            ->whereNotNull('marketer_listing_id')
            ->where('owner_type', 'marketer')
            ->where('owner_id', $marketer->id)
            ->with(['promoter', 'marketerListing.productVariant.product', 'invitations'])
            ->withCount('conversions')
            ->withSum('conversions', 'commission_amount')
            ->latest()
            ->paginate(20);

        return view('marketer.campaigns.promoted-by-others', compact('marketer', 'campaigns'));
    }

    public function request(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'listing_id' => ['required', 'uuid'],
            'listing_type' => ['required', 'in:vendor_listing,admin_listing,marketer_listing'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $source = match ($validated['listing_type']) {
            'vendor_listing' => CampaignSource::vendorListing($validated['listing_id']),
            'marketer_listing' => CampaignSource::marketerListing($validated['listing_id']),
            default => CampaignSource::adminListing($validated['listing_id']),
        };

        try {
            $campaign = $this->campaignService->requestCampaign($this->marketer(), $source, $validated);
        } catch (PeerPromotionException $e) {
            return back()->withInput()->with('error', __('marketer.campaigns.peer.errors.'.$e->reason, $e->context));
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $redirect = redirect()->route('marketer.campaigns.active')
            ->with('success', $validated['listing_type'] === 'marketer_listing'
                ? __('marketer.campaigns.peer.started')
                : __('Campaign request submitted successfully.'));

        // D12: a peer promotion with no profile rule for the category earns nothing per sale; say so up front.
        if ($campaign->hasNoPeerCommission()) {
            $redirect->with('warning', __('marketer.campaigns.peer.no_commission_warning'));
        }

        return $redirect;
    }
}
