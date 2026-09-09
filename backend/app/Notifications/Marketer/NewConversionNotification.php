<?php

namespace App\Notifications\Marketer;

use App\Models\MarketerCampaignConversion;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class NewConversionNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        public readonly MarketerCampaignConversion $conversion,
        public readonly string $marketerAdminId,
    ) {}

    public function notificationType(): string
    {
        return 'new_conversion';
    }

    public function notificationData(object $notifiable): array
    {
        $campaign = $this->conversion->campaign;

        return [
            'conversion_id'     => $this->conversion->id,
            'campaign_id'       => $this->conversion->campaign_id,
            'order_id'          => $this->conversion->order_id,
            'commission_amount' => $this->conversion->commission_amount,
            'currency'          => $this->conversion->currency,
            'title'             => 'عملية بيع جديدة',
            'message'           => 'تم تسجيل عملية بيع جديدة عبر حملة "' . $campaign?->getPromotedTitle() . '" بعمولة ' . $this->conversion->commission_amount . ' ' . $this->conversion->currency . '.',
            'url'               => route('marketer.orders.show', $this->conversion->order_id),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('marketer.' . $this->marketerAdminId)];
    }
}
