<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;

class MarketerReportsController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_reports.view'),
            403
        );
        $vendor = Auth::guard('vendor')->user()->vendor;
        abort_unless($vendor->isMarketer(), 403);

        $invitationIds = MarketerCampaignInvitation::where('marketer_vendor_id', $vendor->id)->pluck('id');

        $conversions = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)
            ->with(['campaign.vendor', 'campaign.vendorListing.productVariant.product', 'order'])
            ->latest()
            ->paginate(20);

        $conversionQuery = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds);

        $stats = [
            'total_conversions' => (clone $conversionQuery)->count(),
            'total_earnings'    => (clone $conversionQuery)->sum('commission_amount'),
            'total_campaigns'   => MarketerCampaignInvitation::where('marketer_vendor_id', $vendor->id)
                ->where('status', 'accepted')->distinct('campaign_id')->count('campaign_id'),
        ];

        $campaignBreakdown = MarketerCampaignInvitation::where('marketer_vendor_id', $vendor->id)
            ->where('status', 'accepted')
            ->with(['campaign.vendorListing.productVariant.product', 'campaign.adminListing.productVariant.product'])
            ->withCount('conversions')
            ->withSum('conversions', 'commission_amount')
            ->get();

        $monthlyEarnings = (clone $conversionQuery)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(commission_amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->limit(12)
            ->get();

        return view('partner.marketer.reports', compact('conversions', 'stats', 'campaignBreakdown', 'monthlyEarnings'));
    }
}
