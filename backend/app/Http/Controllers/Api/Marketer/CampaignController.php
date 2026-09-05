<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignInvitation;
use Illuminate\Support\Facades\Auth;

class CampaignController extends Controller
{
    private function marketer()
    {
        return Auth::guard('marketer_api')->user()->marketer;
    }

    public function active()
    {
        $marketer = $this->marketer();
        $data = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'accepted')
            ->whereHas('campaign', fn ($q) => $q->whereIn('status', ['active', 'auto_approved']))
            ->with(['campaign.country'])
            ->withCount('conversions')
            ->withSum('conversions', 'commission_amount')
            ->latest()->paginate(20);
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function finished()
    {
        $marketer = $this->marketer();
        $data = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->where('status', 'accepted')
            ->whereHas('campaign', fn ($q) => $q->whereIn('status', ['done', 'cancelled', 'rejected']))
            ->with(['campaign.country'])
            ->withCount('conversions')
            ->withSum('conversions', 'commission_amount')
            ->latest()->paginate(20);
        return response()->json(['success' => true, 'data' => $data]);
    }
}
