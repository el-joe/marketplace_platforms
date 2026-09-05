<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $marketerAdmin = Auth::guard('marketer')->user();
        $marketer      = $marketerAdmin->marketer;

        $stats = Cache::remember("marketer.dashboard.{$marketer->id}", 300, function () use ($marketer) {
            $invitationIds = MarketerCampaignInvitation::where('marketer_id', $marketer->id)->pluck('id');

            $pendingInvitations = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
                ->where('status', 'pending')->count();

            $activeCampaigns = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
                ->where('status', 'accepted')->count();

            $totalConversions = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)->count();

            $totalEarnings = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)
                ->where('commissioned', true)->sum('commission_amount');

            $pendingEarnings = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)
                ->where('commissioned', false)->sum('commission_amount');

            $monthlyEarnings = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(commission_amount) as total")
                ->groupBy('month')
                ->orderBy('month')
                ->limit(12)
                ->get();

            return compact(
                'pendingInvitations',
                'activeCampaigns',
                'totalConversions',
                'totalEarnings',
                'pendingEarnings',
                'monthlyEarnings'
            );
        });

        $recentInvitations = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'pending')
            ->with(['campaign.vendorListing.productVariant.product', 'campaign.adminListing.productVariant.product', 'campaign.country'])
            ->latest()
            ->limit(5)
            ->get();

        return view('marketer.dashboard', compact('marketer', 'stats', 'recentInvitations'));
    }
}
