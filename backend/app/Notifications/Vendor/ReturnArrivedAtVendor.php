<?php

namespace App\Notifications\Vendor;

use App\Models\ReturnRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;

class ReturnArrivedAtVendor extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly ReturnRequest $returnRequest) {}

    public function notificationType(): string
    {
        return 'return_arrived_at_vendor';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Return Arrived',
            'message' => "Return for order #{$this->returnRequest->return_number} has arrived at your location.",
            'url' => route('partner.returns.show', $this->returnRequest->return_number),
            'return_request_id' => $this->returnRequest->id,
        ];
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
