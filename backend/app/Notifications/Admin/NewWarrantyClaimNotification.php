<?php

namespace App\Notifications\Admin;

use App\Models\WarrantyClaim;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

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
            'message' => "A customer has submitted warranty claim #{$this->warrantyClaim->claim_number}.",
            'url' => '/admin/warranty-claims/' . $this->warrantyClaim->id,
            'warranty_claim_id' => $this->warrantyClaim->id,
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (!$notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.' . $notifiable->id)];
    }
}
