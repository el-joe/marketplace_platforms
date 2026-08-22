<?php

namespace App\Notifications\Vendor;

use App\Models\WarrantyClaim;
use App\Notifications\BaseDatabaseBroadcastNotification;

class NewWarrantyClaimNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly WarrantyClaim $warrantyClaim) {}

    public function notificationType(): string
    {
        return 'warranty_claim_submitted';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => 'New Warranty Claim',
            'message' => "A customer has submitted a warranty claim ({$this->warrantyClaim->claim_number}) for one of your orders.",
            'url' => '/partner/warranty-claims/' . $this->warrantyClaim->id,
            'warranty_claim_id' => $this->warrantyClaim->id,
        ];
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'push'];
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->notificationData($notifiable);

        return [
            'title' => $data['title'],
            'body' => $data['message'],
            'data' => [
                'screen' => 'warranty_claim_detail',
                'id' => $this->warrantyClaim->id,
                'type' => class_basename(static::class),
            ],
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
