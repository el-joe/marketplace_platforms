<?php

namespace App\Notifications\Vendor;

use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\VendorAdmin;
use App\Notifications\BaseDatabaseBroadcastNotification;

class MarketerReplacedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly MarketerCampaign $campaign,
        private readonly Marketer $oldMarketer,
        private readonly Marketer $newMarketer,
    ) {}

    public function notificationType(): string
    {
        return 'marketer_replaced';
    }

    public function notificationData(object $notifiable): array
    {
        // This notification goes to the VENDOR (campaign owner), not the marketer
        $message = "تم العثور على ماركتر بديل لحملتك \"{$this->campaign->title}\".";

        return [
            'title'             => 'استبدال الماركتر',
            'message'           => $message,
            'url'               => route('partner.marketer-campaigns.show', $this->campaign->id),
            'campaign_id'       => $this->campaign->id,
            'old_marketer_name' => $this->oldMarketer->name,
            'new_marketer_name' => $this->newMarketer->name,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
