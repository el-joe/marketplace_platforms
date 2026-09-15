<?php

namespace App\Notifications\Vendor;

use App\Models\SubOrder;
use App\Notifications\BaseDatabaseBroadcastNotification;

class CodRemittanceConfirmedNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly SubOrder $subOrder)
    {
    }

    public function notificationType(): string
    {
        return 'cod_remittance_confirmed';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'COD Remittance Confirmed',
            'message' => "Cash-on-delivery collection for sub-order ({$this->subOrder->sub_order_number}) has been remitted and confirmed.",
            'sub_order_id' => $this->subOrder->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
