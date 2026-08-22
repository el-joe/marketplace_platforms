<?php

namespace App\Notifications\Vendor;

use App\Models\ReturnRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;

class ReturnInspectedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly ReturnRequest $returnRequest)
    {
    }

    public function notificationType(): string
    {
        return 'return_inspected';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'Return Inspection Completed',
            'message' => "Return ({$this->returnRequest->return_number}) has been inspected. Result: "
                . ($this->returnRequest->inspection_result?->value ?? 'n/a')
                . ', liability: ' . ($this->returnRequest->liability?->value ?? 'n/a') . '.',
            'url' => route('partner.returns.show', $this->returnRequest->return_number),
            'return_request_id' => $this->returnRequest->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
