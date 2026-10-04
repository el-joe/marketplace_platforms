<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerCampaignInvitation;
use App\Services\MarketerCampaignService;
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
                'campaign.tieredRules',
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
            ])
            ->withCount('conversions')
            ->withSum('conversions', 'commission_amount')
            ->latest()
            ->paginate(20);

        return view('marketer.campaigns.finished', compact('marketer', 'invitations'));
    }

    public function request(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'listing_id' => ['required', 'uuid'],
            'listing_type' => ['required', 'in:vendor_listing,admin_listing'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $source = $validated['listing_type'] === 'vendor_listing'
            ? CampaignSource::vendorListing($validated['listing_id'])
            : CampaignSource::adminListing($validated['listing_id']);

        $this->campaignService->requestCampaign($this->marketer(), $source, $validated);

        return redirect()->route('marketer.campaigns.active')
            ->with('success', __('Campaign request submitted successfully.'));
    }
}
