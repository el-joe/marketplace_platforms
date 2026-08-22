<?php

namespace App\Notifications\Admin;

use App\Models\PackagingSupplyRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class NewPackagingOrderReceived extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly PackagingSupplyRequest $packagingRequest) {}

    public function notificationType(): string
    {
        return 'packaging_order_received';
    }

    public function notificationData(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => $this->notificationType(),
            'title'          => 'New Packaging Order',
            'body'           => "New packaging order #{$this->packagingRequest->request_number} from vendor {$this->packagingRequest->vendor->name}.",
            'action_url'     => route('admin.packaging.requests.show', $this->packagingRequest->id),
            'request_number' => $this->packagingRequest->request_number,
            'vendor_name'    => $this->packagingRequest->vendor->name,
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
