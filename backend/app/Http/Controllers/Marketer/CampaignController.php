<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CampaignController extends Controller
{
    private function marketer(): \App\Models\Marketer
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
                'campaign.vendorListing.productVariant.product',
                'campaign.adminListing.productVariant.product',
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
                'campaign.vendorListing.productVariant.product',
                'campaign.adminListing.productVariant.product',
                'campaign.country',
            ])
            ->withCount('conversions')
            ->withSum('conversions', 'commission_amount')
            ->latest()
            ->paginate(20);

        return view('marketer.campaigns.finished', compact('marketer', 'invitations'));
    }
}
