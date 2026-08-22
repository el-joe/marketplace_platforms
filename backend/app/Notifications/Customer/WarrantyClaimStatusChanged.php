<?php

namespace App\Notifications\Customer;

use App\Models\WarrantyClaim;
use Illuminate\Broadcasting\PrivateChannel;

class WarrantyClaimStatusChanged extends BaseCustomerNotification
{
    private static array $statusLabels = [
        'submitted' => 'Submitted',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'resolved' => 'Resolved',
    ];

    private static array $statusLabelsAr = [
        'submitted' => 'تم التقديم',
        'under_review' => 'قيد المراجعة',
        'approved' => 'تمت الموافقة',
        'rejected' => 'مرفوض',
        'resolved' => 'تم الحل',
    ];

    public function __construct(
        private readonly WarrantyClaim $warrantyClaim,
        private readonly string $previousStatus,
    ) {}

    public function notificationType(): string
    {
        return 'warranty_claim_status_changed';
    }

    public function notificationData(object $notifiable): array
    {
        $newLabel = self::$statusLabels[$this->warrantyClaim->status] ?? ucfirst($this->warrantyClaim->status);
        $newLabelAr = self::$statusLabelsAr[$this->warrantyClaim->status] ?? $newLabel;

        return [
            'title' => 'Warranty Claim Update',
            'title_ar' => 'تحديث مطالبة الضمان',
            'message' => "Your warranty claim #{$this->warrantyClaim->claim_number} status changed to: {$newLabel}.",
            'message_ar' => "تم تغيير حالة مطالبة الضمان رقم #{$this->warrantyClaim->claim_number} إلى: {$newLabelAr}.",
            'url' => route('customer.warranty-claims.show', $this->warrantyClaim->id),
            'warranty_claim_id' => $this->warrantyClaim->id,
            'claim_number' => $this->warrantyClaim->claim_number,
            'status' => $this->warrantyClaim->status,
            'previous_status' => $this->previousStatus,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.' . $this->warrantyClaim->customer_id)];
    }
}
