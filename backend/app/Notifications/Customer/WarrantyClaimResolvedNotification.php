<?php

namespace App\Notifications\Customer;

use App\Models\WarrantyClaim;
use Illuminate\Broadcasting\PrivateChannel;

class WarrantyClaimResolvedNotification extends BaseCustomerNotification
{
    private static array $resolutionLabels = [
        'repair' => 'Repair',
        'replace' => 'Replacement',
        'refund' => 'Refund',
        'no_action' => 'No Action',
    ];

    private static array $resolutionLabelsAr = [
        'repair' => 'إصلاح',
        'replace' => 'استبدال',
        'refund' => 'استرداد',
        'no_action' => 'لا يوجد إجراء',
    ];

    public function __construct(
        private readonly WarrantyClaim $warrantyClaim,
    ) {}

    public function notificationType(): string
    {
        return 'warranty_claim_resolved';
    }

    public function notificationData(object $notifiable): array
    {
        $resolutionLabel = self::$resolutionLabels[$this->warrantyClaim->resolution] ?? ucfirst((string) $this->warrantyClaim->resolution);
        $resolutionLabelAr = self::$resolutionLabelsAr[$this->warrantyClaim->resolution] ?? $resolutionLabel;

        return [
            'title' => 'Warranty Claim Resolved',
            'title_ar' => 'تم حل مطالبة الضمان',
            'message' => "Your warranty claim #{$this->warrantyClaim->claim_number} has been resolved: {$resolutionLabel}.",
            'message_ar' => "تم حل مطالبة الضمان رقم #{$this->warrantyClaim->claim_number}: {$resolutionLabelAr}.",
            'url' => route('customer.warranty-claims.show', $this->warrantyClaim->id),
            'warranty_claim_id' => $this->warrantyClaim->id,
            'claim_number' => $this->warrantyClaim->claim_number,
            'resolution' => $this->warrantyClaim->resolution,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('customer.' . $this->warrantyClaim->customer_id)];
    }
}
