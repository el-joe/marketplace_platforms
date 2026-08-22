<?php

namespace App\Notifications\Customer;

use App\Models\ReturnRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class ReturnApprovedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly ReturnRequest $returnRequest)
    {
    }

    public function notificationType(): string
    {
        return 'return_approved';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Return Request Approved',
            'message' => "Your return request ({$this->returnRequest->return_number}) has been approved.",
            'url' => route('customer.returns.show', $this->returnRequest->id),
            'return_request_id' => $this->returnRequest->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.' . $this->returnRequest->customer_id)];
    }
}
