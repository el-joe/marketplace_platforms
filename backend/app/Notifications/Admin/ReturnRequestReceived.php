<?php

namespace App\Notifications\Admin;

use App\Models\ReturnRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class ReturnRequestReceived extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly ReturnRequest $returnRequest) {}

    public function notificationType(): string
    {
        return 'return_request_received';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Return Request Received',
            'message' => "Return request #{$this->returnRequest->return_number} submitted for order #{$this->returnRequest->order->order_number}",
            'url' => route('admin.returns.show', $this->returnRequest),
            'return_request_id' => $this->returnRequest->id,
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.'.$notifiable->id)];
    }
}
