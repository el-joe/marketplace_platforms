<?php

namespace App\Notifications\Vendor;

use App\Models\PackagingSupplyRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use App\Notifications\Channels\VendorPushChannel;

class PackagingOrderRejected extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly PackagingSupplyRequest $packagingRequest) {}

    public function notificationType(): string
    {
        return 'packaging_order_rejected';
    }

    public function notificationData(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    public function via(object $notifiable): array
    {
        return ['database', VendorPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => $this->notificationType(),
            'title'          => 'Packaging Order Rejected',
            'body'           => "Your packaging order #{$this->packagingRequest->request_number} was not approved. Reason: {$this->packagingRequest->notes}",
            'action_url'     => route('partner.packaging-supplies.show-request', $this->packagingRequest->id),
            'request_number' => $this->packagingRequest->request_number,
            'vendor_name'    => $this->packagingRequest->vendor->name,
        ];
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title' => $data['title'],
            'body'  => $data['body'],
            'data'  => [
                'screen' => 'packaging_request_detail',
                'id'     => $this->packagingRequest->id,
                'type'   => class_basename(static::class),
            ],
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
