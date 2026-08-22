<?php

namespace App\Notifications\Vendor;

use App\Models\MarketerCampaign;
use App\Models\Vendor;
use App\Notifications\BaseDatabaseBroadcastNotification;
use App\Models\VendorAdmin;

class MarketerReplacedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly MarketerCampaign $campaign,
        private readonly Vendor $oldMarketer,
        private readonly Vendor $newMarketer,
    ) {}

    public function notificationType(): string
    {
        return 'marketer_replaced';
    }

    public function notificationData(object $notifiable): array
    {
        $vendorId = $notifiable instanceof VendorAdmin ? $notifiable->vendor_id : null;

        if ($vendorId === $this->oldMarketer->id) {
            $message = "تم استبدالك في حملة \"{$this->campaign->title}\" بماركتر آخر.";
        } elseif ($vendorId === $this->newMarketer->id) {
            $message = "تمت دعوتك للانضمام لحملة \"{$this->campaign->title}\".";
        } else {
            $message = "تم العثور على ماركتر بديل لحملتك.";
        }

        return [
            'title'             => 'استبدال الماركتر',
            'message'           => $message,
            'url'               => route('partner.marketer-campaigns.show', $this->campaign->id),
            'campaign_id'       => $this->campaign->id,
            'old_marketer_name' => $this->oldMarketer->store_name,
            'new_marketer_name' => $this->newMarketer->store_name,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
