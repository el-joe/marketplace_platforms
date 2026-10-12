<?php

namespace App\Notifications\Marketer;

use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class PromotionQuotaBehindNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        public readonly int $done,
        public readonly int $target,
        public readonly string $marketerAdminId,
    ) {}

    public function notificationType(): string
    {
        return 'promotion_quota_behind';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => __('marketer.quota.reminder_title'),
            'message' => __('marketer.quota.reminder_message', ['done' => $this->done, 'target' => $this->target]),
            'url' => route('marketer.invitations.index'),
            'done' => $this->done,
            'target' => $this->target,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('marketer.'.$this->marketerAdminId)];
    }
}
