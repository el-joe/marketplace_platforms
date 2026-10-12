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

    /** GET of the signed accept/reject link: never changes state (link previews/prefetch), only asks for confirmation. */
    public function confirmAccept(MarketerCampaignInvitation $invitation): View
    {
        return $this->confirm($invitation, 'accept');
    }

    public function confirmReject(MarketerCampaignInvitation $invitation): View
    {
        return $this->confirm($invitation, 'reject');
    }

    public function accept(Request $request, MarketerCampaignInvitation $invitation): View
    {
        $request->merge(['action' => 'accept']);

        return $this->respond($request, $invitation);
    }

    public function reject(Request $request, MarketerCampaignInvitation $invitation): View
    {
        $request->merge(['action' => 'reject']);

        return $this->respond($request, $invitation);
    }

    private function confirm(MarketerCampaignInvitation $invitation, string $action): View
    {
        $invitation->load('campaign.vendor', 'marketer');

        return view('marketer.invitations.whatsapp', [
            'invitation' => $invitation,
            'outcome' => ($invitation->isPending() && ! $invitation->isTimedOut()) ? null : 'unavailable',
            'action' => $action,
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
