<?php

namespace App\Jobs;

use App\Contracts\WhatsAppGateway;
use App\Models\MarketerCampaignInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Optional (setting marketer_whatsapp_notify_vendor): tells the seller what the influencer did. */
class SendVendorInvitationResponseWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $invitationId, public readonly string $outcome) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return (array) config('whatsapp.backoff', [60, 300, 900]);
    }

    public function handle(WhatsAppGateway $whatsapp): void
    {
        if (! filter_var(setting('marketer_whatsapp_notify_vendor', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $invitation = MarketerCampaignInvitation::with(['marketer', 'campaign.vendor'])->find($this->invitationId);
        $number = $invitation?->campaign?->vendor?->whatsapp_number;
        if (! $invitation || ! $number || ! $whatsapp->isConfigured()) {
            return;
        }

        $locale = (string) (setting('whatsapp_message_locale') ?: config('app.locale'));
        $key = in_array($this->outcome, ['accepted', 'rejected', 'timed_out'], true) ? $this->outcome : 'rejected';

        $ok = $whatsapp->send($number, __('marketer.invitations.whatsapp_vendor_'.$key, [
            'marketer' => $invitation->marketer->name,
            'product' => $invitation->campaign->getPromotedTitle(),
        ], $locale));

        if (! $ok) {
            $this->release($this->backoff()[min($this->attempts() - 1, count($this->backoff()) - 1)] ?? 60);
        }
    }
}
