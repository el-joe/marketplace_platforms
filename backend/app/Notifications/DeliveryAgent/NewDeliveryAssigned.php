<?php

namespace App\Notifications\DeliveryAgent;

use App\Models\DeliveryAssignment;
use App\Notifications\BaseDatabaseBroadcastNotification;
use App\Notifications\Channels\VendorPushChannel;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * The most time-sensitive notification in the platform — the agent must see
 * it immediately, so it also pushes via FCM (VendorPushChannel::sendToToken()
 * always sends with high Android/APNs priority).
 */
class NewDeliveryAssigned extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly DeliveryAssignment $assignment) {}

    public function via(object $notifiable): array
    {
        return array_merge(parent::via($notifiable), [VendorPushChannel::class]);
    }

    public function notificationType(): string
    {
        return 'new_delivery_assigned';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'         => 'تم تعيين توصيل جديد لك',
            'message'       => 'لديك طلب توصيل جديد — رقم الشحنة #' . $this->assignment->shipment_id . '. يرجى الاطلاع عليه.',
            'url'           => route('delivery.assignments.show', $this->assignment->id),
            'assignment_id' => $this->assignment->id,
            'shipment_id'   => $this->assignment->shipment_id,
            'assigned_at'   => $this->assignment->assigned_at?->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('delivery-agent.' . $this->assignment->agent_id)];
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->notificationData($notifiable);

        return [
            'title' => $data['title'],
            'body'  => $data['message'],
            'data'  => [
                'screen'        => 'assignment_detail',
                'id'            => $this->assignment->id,
                'assignment_id' => $this->assignment->id,
            ],
        ];
    }
}
