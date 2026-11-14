<?php

namespace App\Notifications\Customer;

use App\Models\ReturnRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class ReturnRejectedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly ReturnRequest $returnRequest) {}

    public function notificationType(): string
    {
        return 'return_rejected';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Return Request Rejected',
            'message' => "Your return request ({$this->returnRequest->return_number}) was rejected: "
                .($this->returnRequest->rejection_reason ?? ''),
            'url' => route('customer.returns.show', ['country' => $this->returnRequest->order?->country?->site_code, 'return_number' => $this->returnRequest->return_number]),
            'return_request_id' => $this->returnRequest->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.'.$this->returnRequest->customer_id)];
    }
}
