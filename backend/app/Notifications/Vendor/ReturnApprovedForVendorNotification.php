<?php

namespace App\Notifications\Vendor;

use App\Models\ReturnRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;

class ReturnApprovedForVendorNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly ReturnRequest $returnRequest)
    {
    }

    public function notificationType(): string
    {
        return 'return_approved_for_vendor';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Return Request Approved',
            'message' => "A return request ({$this->returnRequest->return_number}) on one of your orders has been approved and will be picked up.",
            'url' => route('partner.returns.show', $this->returnRequest->return_number),
            'return_request_id' => $this->returnRequest->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
