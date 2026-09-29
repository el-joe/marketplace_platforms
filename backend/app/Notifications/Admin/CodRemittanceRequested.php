<?php

namespace App\Notifications\Admin;

use App\Models\DeliveryAgentCodSettlement;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class CodRemittanceRequested extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly DeliveryAgentCodSettlement $settlement) {}

    public function notificationType(): string
    {
        return 'cod_remittance_requested';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'COD Remittance Requested',
            'message' => "COD remittance of {$this->settlement->net_to_remit} requested for delivery agent #{$this->settlement->agent_id}.",
            'url' => route('admin.delivery.cod-settlements.show', $this->settlement),
            'settlement_id' => $this->settlement->id,
            'agent_id' => $this->settlement->agent_id,
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
