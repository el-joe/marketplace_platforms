<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerCampaignInvitation;
use App\Services\MarketerCampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(private MarketerCampaignService $service) {}

    private function marketer(): Marketer
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
            ->whereHas('campaign') // hide invitations whose campaign was deleted
            ->with([
                'campaign.vendor',
                'campaign.vendorListing.productVariant' => fn ($q) => $q->withTrashed(),
                'campaign.vendorListing.productVariant.product' => fn ($q) => $q->withTrashed(),
                'campaign.adminListing' => fn ($q) => $q->withTrashed(),
                'campaign.adminListing.productVariant' => fn ($q) => $q->withTrashed(),
                'campaign.adminListing.productVariant.product' => fn ($q) => $q->withTrashed(),
                'campaign.country',
                'campaign.classifiedListing',
                'campaign.travelPackage',
                'marketer',
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
        abort_unless($invitation->isPending(), 422, __('marketer.msg.invitation_not_acceptable'));

        $this->service->acceptInvitation($invitation, $request->input('note'));

        return back()->with('success', __('marketer.msg.invitation_accepted'));
    }

    /**
     * Reject a pending invitation.
     */
    public function reject(Request $request, MarketerCampaignInvitation $invitation): RedirectResponse
    {
        $marketer = $this->marketer();
        abort_unless($invitation->marketer_id === $marketer->id, 403);
        abort_unless($invitation->isPending(), 422, __('marketer.msg.invitation_not_rejectable'));

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->rejectInvitation($invitation, $request->input('reason'));

        return back()->with('success', __('marketer.msg.invitation_rejected'));
    }
}
