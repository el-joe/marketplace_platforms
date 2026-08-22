<?php

namespace App\Notifications\Customer;

use App\Models\ReturnRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class ReturnStatusChangedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly ReturnRequest $returnRequest)
    {
    }

    public function notificationType(): string
    {
        return 'return_status_changed';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Return Request Update',
            'message' => "Your return request ({$this->returnRequest->return_number}) status changed to "
                . str_replace('_', ' ', $this->returnRequest->status->value) . '.',
            'url' => route('customer.returns.show', $this->returnRequest->id),
            'return_request_id' => $this->returnRequest->id,
            'status' => $this->returnRequest->status->value,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.' . $this->returnRequest->customer_id)];
    }
}
