<?php

namespace App\Notifications\Vendor;

use App\Models\MarketerCampaign;
use App\Notifications\BaseDatabaseBroadcastNotification;

class CampaignPendingAdminNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly MarketerCampaign $campaign) {}

    public function notificationType(): string
    {
        return 'campaign_pending_admin';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'           => 'حملتك قيد المراجعة',
            'message'         => 'حملتك قيد المراجعة من الأدمن، سيتم الرد خلال 36 ساعة.',
            'url'             => route('partner.marketer-campaigns.show', $this->campaign->id),
            'campaign_id'     => $this->campaign->id,
            'campaign_title'  => $this->campaign->title,
            'product_name'    => $this->productName(),
            'auto_approve_at' => $this->campaign->auto_approve_at,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }

    private function productName(): ?string
    {
        $product = $this->campaign->vendorListing?->productVariant?->product;

        return $product?->name_ar ?? $product?->name_en;
    }
}
