<?php

namespace App\Services\Marketer;

use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerInfluencerFeeCountrySetting;
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

        $commission = match ($campaign->commission_type) {
            'fixed' => __('marketer.invitations.commission_type_fixed', [], $locale),
            'tiered' => __('marketer.invitations.commission_type_tiered', [], $locale),
            'last_click' => __('marketer.invitations.commission_type_last_click', [], $locale),
            default => (string) $campaign->commission_type,
        };
        if ($campaign->commission_type === 'fixed' && (int) $campaign->marketer_commission_amount > 0) {
            $commission .= ' ('.number_format((int) $campaign->marketer_commission_amount).' '.$campaign->currency.')';
        }

        return __('marketer.invitations.whatsapp_message', [
            'name' => $invitation->marketer->name,
            'product' => $campaign->getPromotedTitle(),
            'commission' => $commission,
            'fees' => $this->feeSummary($invitation, $locale),
            'hours' => $invitation->acceptance_window_hours,
            'deadline' => $invitation->expires_at?->copy()->locale($locale)->isoFormat('D MMM YYYY HH:mm') ?? '-',
            'accept_link' => $links['accept'],
            'reject_link' => $links['reject'],
        ], $locale);
    }

    private function feeSummary(MarketerCampaignInvitation $invitation, string $locale): string
    {
        $fee = $invitation->marketer->isInfluencer()
            ? (int) (MarketerInfluencerFeeCountrySetting::where('country_id', $invitation->campaign->country_id)->value('fee_per_influencer') ?? 0)
            : 0;

        if ($fee <= 0) {
            return __('marketer.invitations.whatsapp_fees_none', [], $locale);
        }

        return setting('marketer_influencer_fee_payer', 'vendor') === 'vendor' && $invitation->campaign->vendor_id
            ? __('marketer.invitations.whatsapp_fees_seller', [], $locale)
            : __('marketer.invitations.whatsapp_fees_marketer', ['fee' => number_format($fee).' '.$invitation->campaign->currency], $locale);
    }
}
