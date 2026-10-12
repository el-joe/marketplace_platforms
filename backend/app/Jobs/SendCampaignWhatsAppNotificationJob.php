<?php

namespace App\Jobs;

use App\Contracts\WhatsAppGateway;
use App\Models\MarketerCampaignInvitation;
use App\Services\Marketer\InvitationWhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends the invitation (with signed accept/reject links) to marketers.whatsapp_for_campaigns.
 * whatsapp_sent is set only when the gateway accepted the message. Transient failures are retried
 * with backoff; every failure is stored on the invitation and shown to admins on the campaign page.
 */
class SendCampaignWhatsAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly string $invitationId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return (array) config('whatsapp.backoff', [60, 300, 900, 3600]);
    }

    public function handle(WhatsAppGateway $whatsapp, InvitationWhatsAppMessage $messages): void
    {
        $invitation = MarketerCampaignInvitation::with(['marketer', 'campaign.vendor'])->find($this->invitationId);
        if (! $invitation || $invitation->whatsapp_sent || ! $invitation->isPending() || $invitation->isTimedOut()) {
            return;
        }

        $number = $invitation->marketer->whatsapp_for_campaigns;
        if (! $number) {
            $this->recordFailure($invitation, 'missing_whatsapp_number');

            return;
        }

        $invitation->increment('whatsapp_attempts');

        if (! $whatsapp->isConfigured()) {
            // Permanent: nothing to retry until an admin configures a provider.
            $this->recordFailure($invitation, $whatsapp->lastError() ?: 'whatsapp_not_configured');

            return;
        }

        $locale = (string) (setting('whatsapp_message_locale') ?: config('app.locale'));

        if (! $whatsapp->send($number, $messages->build($invitation, $locale))) {
            $this->recordFailure($invitation, $whatsapp->lastError() ?: 'send_failed');
            Log::warning('Campaign WhatsApp invitation not delivered', ['invitation' => $invitation->id, 'error' => $whatsapp->lastError()]);

            $this->release($this->backoff()[min($this->attempts() - 1, count($this->backoff()) - 1)] ?? 60);

            return;
        }

        $invitation->update([
            'whatsapp_sent' => true,
            'whatsapp_sent_at' => now(),
            'whatsapp_last_error' => null,
            'whatsapp_failed_at' => null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        MarketerCampaignInvitation::whereKey($this->invitationId)->update([
            'whatsapp_last_error' => mb_substr($e->getMessage(), 0, 500),
            'whatsapp_failed_at' => now(),
        ]);
    }

    private function recordFailure(MarketerCampaignInvitation $invitation, string $error): void
    {
        $invitation->update([
            'whatsapp_last_error' => mb_substr($error, 0, 500),
            'whatsapp_failed_at' => now(),
        ]);
    }
}
