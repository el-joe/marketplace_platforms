<?php

namespace App\Services\Marketer;

use App\Models\MarketerCampaignInvitation;
use App\Support\Money;
use Illuminate\Support\Facades\URL;

/**
 * Builds the WhatsApp invitation text (ar/en) and its signed accept / reject URLs.
 * The links expire together with the invitation.
 */
class InvitationWhatsAppMessage
{
    /** @return array{accept: string, reject: string, show: string} */
    public function links(MarketerCampaignInvitation $invitation): array
    {
        $expires = $invitation->expires_at ?? now()->addHours(max(1, (int) $invitation->acceptance_window_hours));
        $args = ['invitation' => $invitation->id];

        return [
            'accept' => URL::temporarySignedRoute('marketer.invitations.whatsapp.accept', $expires, $args),
            'reject' => URL::temporarySignedRoute('marketer.invitations.whatsapp.reject', $expires, $args),
            'show' => URL::temporarySignedRoute('marketer.invitations.whatsapp.show', $expires, $args),
        ];
    }

    public function build(MarketerCampaignInvitation $invitation, ?string $locale = null): string
    {
        $locale = $locale ?: (string) config('app.locale');
        $invitation->loadMissing('marketer', 'campaign.vendor');
        $campaign = $invitation->campaign;
        $links = $this->links($invitation);

        $fixed = $invitation->influencer_fixed_status && $invitation->influencer_fixed_status !== 'none'
            ? Money::number($invitation->influencer_fixed_amount)
            : 0.0;
        $commission = $fixed > 0
            ? __('marketer.invitations.whatsapp_commission_fixed', ['amount' => Money::format($fixed), 'currency' => $campaign->currency], $locale)
            : __('marketer.invitations.whatsapp_commission_percentage', [], $locale);

        return __('marketer.invitations.whatsapp_message', [
            'name' => $invitation->marketer->name,
            'product' => $campaign->getPromotedTitle(),
            'commission' => $commission,
            'fees' => __('marketer.invitations.whatsapp_fees_note', [], $locale),
            'hours' => $invitation->acceptance_window_hours,
            'deadline' => $invitation->expires_at?->copy()->locale($locale)->isoFormat('D MMM YYYY HH:mm') ?? '-',
            'accept_link' => $links['accept'],
            'reject_link' => $links['reject'],
        ], $locale);
    }
}
