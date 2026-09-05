<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReportController extends Controller
{
    private function marketer(): \App\Models\Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    public function index(): View
    {
        $marketer      = $this->marketer();
        $invitationIds = MarketerCampaignInvitation::where('marketer_id', $marketer->id)->pluck('id');

        $conversions = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)
            ->with([
                'campaign.vendor',
                'campaign.vendorListing.productVariant.product',
                'campaign.adminListing.productVariant.product',
                'order',
                'invitation',
            ])
            ->latest()
            ->paginate(20);

        $conversionQuery = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds);

        $stats = [
            'total_conversions' => (clone $conversionQuery)->count(),
            'total_earnings'    => (clone $conversionQuery)->where('commissioned', true)->sum('commission_amount'),
            'pending_earnings'  => (clone $conversionQuery)->where('commissioned', false)->sum('commission_amount'),
            'total_campaigns'   => MarketerCampaignInvitation::where('marketer_id', $marketer->id)
                ->where('status', 'accepted')
                ->distinct('campaign_id')
                ->count('campaign_id'),
        ];

        $campaignBreakdown = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'accepted')
            ->with([
                'campaign.vendorListing.productVariant.product',
                'campaign.adminListing.productVariant.product',
                'campaign.country',
            ])
            ->withCount('conversions')
            ->withSum('conversions', 'commission_amount')
            ->get();

        $monthlyEarnings = (clone $conversionQuery)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(commission_amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->limit(12)
            ->get();

        return view('marketer.reports.index', compact(
            'marketer',
            'conversions',
            'stats',
            'campaignBreakdown',
            'monthlyEarnings'
        ));
    }
}
