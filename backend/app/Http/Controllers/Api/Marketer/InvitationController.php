<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignInvitation;
use App\Services\MarketerCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvitationController extends Controller
{
    public function __construct(private MarketerCampaignService $service) {}

    public function index()
    {
        $marketer = Auth::guard('marketer_api')->user()->marketer;
        $invitations = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->with(['campaign.country'])
            ->latest()
            ->paginate(20);
        return response()->json(['success' => true, 'data' => $invitations]);
    }

    public function accept(Request $request, MarketerCampaignInvitation $invitation)
    {
        $marketer = Auth::guard('marketer_api')->user()->marketer;
        abort_unless($invitation->marketer_id === $marketer->id, 403);
        abort_unless($invitation->isPending(), 422);
        $this->service->acceptInvitation($invitation, $request->input('note'));
        return response()->json(['success' => true, 'message' => 'تم قبول الدعوة.']);
    }

    public function reject(Request $request, MarketerCampaignInvitation $invitation)
    {
        $marketer = Auth::guard('marketer_api')->user()->marketer;
        abort_unless($invitation->marketer_id === $marketer->id, 403);
        abort_unless($invitation->isPending(), 422);
        $this->service->rejectInvitation($invitation, $request->input('reason'));
        return response()->json(['success' => true, 'message' => 'تم رفض الدعوة.']);
    }
}
