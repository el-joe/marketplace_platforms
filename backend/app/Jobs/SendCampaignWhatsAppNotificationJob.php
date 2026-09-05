<?php

namespace App\Jobs;

use App\Models\MarketerCampaignInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCampaignWhatsAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $invitationId)
    {
    }

    public function handle(): void
    {
        $invitation = MarketerCampaignInvitation::with(['marketer', 'campaign.vendor'])->find($this->invitationId);
        if (!$invitation || $invitation->whatsapp_sent) {
            return;
        }

        $whatsapp = $invitation->marketer->whatsapp_for_campaigns;
        if (!$whatsapp) {
            return;
        }

        $acceptUrl = route('marketer.invitations.accept', $invitation->id);
        $rejectUrl = route('marketer.invitations.reject', $invitation->id);

        $message = "مرحباً {$invitation->marketer->name}،\n"
            . "لديك دعوة حملة ترويجية جديدة!\n"
            . "رابط القبول: {$acceptUrl}\n"
            . "رابط الرفض: {$rejectUrl}\n"
            . "تنتهي الدعوة خلال: {$invitation->acceptance_window_hours} ساعة";

        // VERIFY: send via the platform's WhatsApp provider (Twilio / Meta Cloud API)
        // \App\Services\WhatsAppService::send($whatsapp, $message);

        $invitation->update(['whatsapp_sent' => true, 'whatsapp_sent_at' => now()]);
    }
}
