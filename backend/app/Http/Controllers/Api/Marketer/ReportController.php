<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Services\MarketingFeeReportService;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function index()
    {
        $marketer = Auth::guard('marketer_api')->user()->marketer;
        $invitationIds = MarketerCampaignInvitation::where('marketer_id', $marketer->id)->pluck('id');
        $conversions = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)
            ->latest()->paginate(20);
        $stats = [
            'total_conversions' => MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)->count(),
            'total_earnings' => MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)->where('commissioned', true)->sum('commission_amount'),
            'pending_earnings' => MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)->where('commissioned', false)->sum('commission_amount'),
        ];
        $report = app(MarketingFeeReportService::class);
        $affiliate = $report->marketerAffiliateTotals($marketer->id);
        $stats['fee_share'] = $report->marketerFeeShare($marketer->id)['totals'];
        $stats['affiliate'] = collect($affiliate['totals'])->map(fn ($t) => collect($t)->except('marketing_fees')->all())->all();

        return response()->json(['success' => true, 'stats' => $stats, 'data' => $conversions]);
    }
}
