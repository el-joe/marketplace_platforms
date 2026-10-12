<?php

namespace App\Notifications\Admin;

use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Admin log entry written whenever an invitation is rejected / expires and the
 * platform moves the promotion to another marketer (or finds nobody to move it to).
 */
class CampaignMarketerReassignedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly MarketerCampaign $campaign,
        private readonly Marketer $oldMarketer,
        private readonly ?Marketer $newMarketer,
        private readonly string $reason,
    ) {}

    public function notificationType(): string
    {
        return 'campaign_marketer_reassigned';
    }

    public function notificationData(object $notifiable): array
    {
        $message = $this->newMarketer
            ? "{$this->oldMarketer->name} ({$this->reason}) → {$this->newMarketer->name}"
            : "{$this->oldMarketer->name} ({$this->reason}) — no replacement marketer available";

        return [
            'title' => 'نقل ترويج بين المسوّقين',
            'message' => $message,
            'url' => route('admin.marketer-campaigns.show', $this->campaign->id),
            'campaign_id' => $this->campaign->id,
            'old_marketer_id' => $this->oldMarketer->id,
            'old_marketer_name' => $this->oldMarketer->name,
            'new_marketer_id' => $this->newMarketer?->id,
            'new_marketer_name' => $this->newMarketer?->name,
            'reason' => $this->reason,
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        return $notifiable ? [new PrivateChannel('admin.'.$notifiable->id)] : [];
    }
}
