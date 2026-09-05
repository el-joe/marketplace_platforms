<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignInvitation;
use App\Services\MarketerCampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(private MarketerCampaignService $service) {}

    private function marketer(): \App\Models\Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    /**
     * All invitations for this marketer (pending, accepted, rejected, timed_out).
     */
    public function index(): View
    {
        $marketer = $this->marketer();

        $invitations = MarketerCampaignInvitation::where('marketer_id', $marketer->id)
            ->with([
                'campaign.vendor',
                'campaign.vendorListing.productVariant.product',
                'campaign.adminListing.productVariant.product',
                'campaign.country',
            ])
            ->latest()
            ->paginate(20);

        return view('marketer.invitations.index', compact('marketer', 'invitations'));
    }

    /**
     * Accept a pending invitation.
     */
    public function accept(Request $request, MarketerCampaignInvitation $invitation): RedirectResponse
    {
        $marketer = $this->marketer();
        abort_unless($invitation->marketer_id === $marketer->id, 403);
        abort_unless($invitation->isPending(), 422, 'الدعوة لم تعد قابلة للقبول.');

        $this->service->acceptInvitation($invitation, $request->input('note'));

        return back()->with('success', 'تم قبول دعوة الحملة بنجاح. يمكنك الآن مشاركة رابط الإحالة.');
    }

    /**
     * Reject a pending invitation.
     */
    public function reject(Request $request, MarketerCampaignInvitation $invitation): RedirectResponse
    {
        $marketer = $this->marketer();
        abort_unless($invitation->marketer_id === $marketer->id, 403);
        abort_unless($invitation->isPending(), 422, 'الدعوة لم تعد قابلة للرفض.');

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->rejectInvitation($invitation, $request->input('reason'));

        return back()->with('success', 'تم رفض الدعوة.');
    }
}
