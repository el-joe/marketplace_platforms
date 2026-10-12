<?php

namespace App\Jobs;

use App\Models\MarketerCampaignInvitation;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class SendCampaignWhatsAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $invitationId) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        $invitation = MarketerCampaignInvitation::with(['marketer', 'campaign.vendor'])->find($this->invitationId);
        if (! $invitation || $invitation->whatsapp_sent) {
            return;
        }

        $whatsappNumber = $invitation->marketer->whatsapp_for_campaigns;
        if (! $whatsappNumber) {
            return;
        }

        $link = URL::temporarySignedRoute(
            'marketer.invitations.whatsapp.show',
            $invitation->expires_at ?? now()->addHours($invitation->acceptance_window_hours),
            ['invitation' => $invitation->id],
        );

        $message = __('marketer.invitations.whatsapp_message', [
            'name' => $invitation->marketer->name,
            'link' => $link,
            'hours' => $invitation->acceptance_window_hours,
        ], config('app.locale'));

        if (! $whatsapp->send($whatsappNumber, $message)) {
            return;
        }

        $invitation->update(['whatsapp_sent' => true, 'whatsapp_sent_at' => now()]);
    }
}
