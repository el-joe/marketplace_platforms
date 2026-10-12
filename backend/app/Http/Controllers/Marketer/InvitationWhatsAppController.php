<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaignInvitation;
use App\Services\MarketerCampaignService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Passwordless accept/reject page reached from the WhatsApp invitation message.
 * Both routes are protected by a signed URL that expires with the invitation.
 */
class InvitationWhatsAppController extends Controller
{
    public function __construct(private MarketerCampaignService $service) {}

    public function show(MarketerCampaignInvitation $invitation): View
    {
        return view('marketer.invitations.whatsapp', [
            'invitation' => $invitation->load('campaign.vendor', 'marketer'),
            'outcome' => null,
        ]);
    }

    public function respond(Request $request, MarketerCampaignInvitation $invitation): View
    {
        $data = $request->validate([
            'action' => ['required', 'in:accept,reject'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $invitation->load('campaign.vendor', 'marketer');

        if ($invitation->isTimedOut() || ! $invitation->isPending()) {
            return view('marketer.invitations.whatsapp', ['invitation' => $invitation, 'outcome' => 'unavailable']);
        }

        try {
            if ($data['action'] === 'accept') {
                $this->service->acceptInvitation($invitation);
            } else {
                $this->service->rejectInvitation($invitation, $data['reason'] ?? null);
            }
        } catch (\RuntimeException $e) {
            return view('marketer.invitations.whatsapp', [
                'invitation' => $invitation,
                'outcome' => 'error',
                'error' => $e->getMessage(),
            ]);
        }

        return view('marketer.invitations.whatsapp', [
            'invitation' => $invitation->fresh(['campaign.vendor', 'marketer']),
            'outcome' => $data['action'] === 'accept' ? 'accepted' : 'rejected',
        ]);
    }
}
