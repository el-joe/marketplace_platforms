<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedListing;
use App\Models\Marketer;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $marketer = Auth::guard('marketer_api')->user()->marketer;
        $invitationIds = MarketerCampaignInvitation::where('marketer_id', $marketer->id)->pluck('id');

        return response()->json([
            'success' => true,
            'data' => [
                'pending_invitations' => MarketerCampaignInvitation::where('marketer_id', $marketer->id)->where('status', 'pending')->count(),
                'active_campaigns'    => MarketerCampaignInvitation::where('marketer_id', $marketer->id)->where('status', 'accepted')->count(),
                'total_conversions'   => MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)->count(),
                'total_earnings'      => MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)->where('commissioned', true)->sum('commission_amount'),
                'pending_earnings'    => MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)->where('commissioned', false)->sum('commission_amount'),
                'classified_listings_count' => ClassifiedListing::where('seller_type', Marketer::class)->where('seller_id', $marketer->id)->count(),
                'classified_conversions_count' => \App\Models\ClassifiedInquiry::whereHas('listing', fn ($q) => $q->withTrashed()->where('seller_type', Marketer::class)->where('seller_id', $marketer->id))->where('status', 'closed')->count(),
            ],
        ]);
    }
}
