<?php

namespace App\Notifications\Customer;

use App\Models\Dispute;
use Illuminate\Broadcasting\PrivateChannel;

class DisputeStatusChanged extends BaseCustomerNotification
{
    private static array $statusLabels = [
        'open'             => 'Open',
        'seller_responded' => 'Seller Responded',
        'under_review'     => 'Under Review',
        'escalated'        => 'Escalated',
        'resolved'         => 'Resolved',
        'closed'           => 'Closed',
    ];

    private static array $statusLabelsAr = [
        'open'             => 'مفتوح',
        'seller_responded' => 'رد البائع',
        'under_review'     => 'قيد المراجعة',
        'escalated'        => 'تم التصعيد',
        'resolved'         => 'تم الحل',
        'closed'           => 'مغلق',
    ];

    public function __construct(
        private readonly Dispute $dispute,
        private readonly string $previousStatus,
    ) {}

    public function notificationType(): string
    {
        return 'dispute_status_changed';
    }

    public function notificationData(object $notifiable): array
    {
        $newLabel = self::$statusLabels[$this->dispute->status->value] ?? ucfirst($this->dispute->status->value);
        $newLabelAr = self::$statusLabelsAr[$this->dispute->status->value] ?? $newLabel;

        return [
            'title'           => 'Dispute Update',
            'title_ar'        => 'تحديث حالة النزاع',
            'message'         => "Your dispute #{$this->dispute->dispute_number} status changed to: {$newLabel}.",
            'message_ar'      => "تم تغيير حالة نزاعك رقم #{$this->dispute->dispute_number} إلى: {$newLabelAr}.",
            'url'             => route('customer.disputes.show', $this->dispute->dispute_number),
            'dispute_id'      => $this->dispute->id,
            'dispute_number'  => $this->dispute->dispute_number,
            'status'          => $this->dispute->status->value,
            'previous_status' => $this->previousStatus,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.' . $this->dispute->customer_id)];
    }
}
