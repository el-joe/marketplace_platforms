<?php

namespace App\Notifications\Admin;

use App\Models\Dispute;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class DisputeOpened extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly Dispute $dispute) {}

    public function notificationType(): string
    {
        return 'dispute_opened';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'      => 'New Dispute Opened',
            'message'    => "Dispute #{$this->dispute->dispute_number} has been opened by a customer and requires attention.",
            'dispute_id' => $this->dispute->id,
            'link'       => route('admin.disputes.show', $this->dispute->id),
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.' . $notifiable->id)];
    }
}
