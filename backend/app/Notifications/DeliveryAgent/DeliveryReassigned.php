<?php

namespace App\Notifications\DeliveryAgent;

use App\Models\DeliveryAssignment;
use App\Notifications\BaseDatabaseBroadcastNotification;
use App\Notifications\Channels\VendorPushChannel;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Sent to the OLD agent when a supervisor reassigns their delivery away.
 * For the new agent, fire NewDeliveryAssigned separately.
 */
class DeliveryReassigned extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly DeliveryAssignment $assignment,
        private readonly string $oldAgentId,
    ) {}

    public function via(object $notifiable): array
    {
        return array_merge(parent::via($notifiable), [VendorPushChannel::class]);
    }

    public function notificationType(): string
    {
        return 'delivery_reassigned_away';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'         => 'تم إعادة تعيين طلب التوصيل',
            'message'       => 'طلب التوصيل رقم الشحنة #' . $this->assignment->shipment_id . ' لم يعد مُعيَّناً لك. تم إسناده إلى مندوب آخر.',
            'url'           => route('delivery.assignments.index'),
            'assignment_id' => $this->assignment->id,
            'shipment_id'   => $this->assignment->shipment_id,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('delivery-agent.' . $this->oldAgentId)];
    }

    public function toPush(object $notifiable): array
    {
        $data = $this->notificationData($notifiable);

        return [
            'title' => $data['title'],
            'body'  => $data['message'],
            'data'  => ['screen' => 'assignments'],
        ];
    }
}
