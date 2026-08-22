<?php

namespace App\Notifications\Admin;

use App\Models\MarketerCampaign;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class NewCampaignPendingNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly MarketerCampaign $campaign) {}

    public function notificationType(): string
    {
        return 'new_campaign_pending';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'           => 'حملة ترويجية جديدة تحتاج مراجعة',
            'message'         => "حملة ترويجية جديدة تحتاج مراجعة من {$this->campaign->vendor?->store_name}.",
            'url'             => route('admin.marketer-campaigns.show', $this->campaign->id),
            'campaign_id'     => $this->campaign->id,
            'vendor_name'     => $this->campaign->vendor?->store_name,
            'product_name'    => $this->productName(),
            'auto_approve_at' => $this->campaign->auto_approve_at,
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.' . $notifiable->id)];
    }

    private function productName(): ?string
    {
        $product = $this->campaign->vendorListing?->productVariant?->product;

        return $product?->name_ar ?? $product?->name_en;
    }
}
