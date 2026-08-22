<?php

namespace App\Notifications\Admin;

use App\Models\Order;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

// VERIFY trigger point: fired from OrderInterventionService::flagFraud() (manual admin action)
// and FraudDetectionJob::handle() once automated scoring is implemented.
// If automated scoring is added, ensure admins with 'orders.flag_fraud' permission receive this.
class FraudPatternDetected extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly Order $order,
        private readonly string $reason,
    ) {}

    public function notificationType(): string
    {
        return 'fraud_pattern_detected';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'    => 'Fraud Pattern Detected',
            'message'  => "Order #{$this->order->order_number} has been flagged for potential fraud. Reason: {$this->reason}",
            'order_id' => $this->order->id,
            'link'     => route('admin.orders.show', $this->order->id),
        ];
    }

    public function broadcastOn(mixed $notifiable = null): array
    {
        if (! $notifiable) {
            return [];
        }

        return [new PrivateChannel('admin.' . $notifiable->id)];
    }
}
