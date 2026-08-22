<?php

namespace App\Notifications\Vendor;

use App\Models\VendorProductCertification;
use App\Notifications\BaseDatabaseBroadcastNotification;

class ProductCertificationApproved extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly VendorProductCertification $certification) {}

    public function notificationType(): string
    {
        return 'vendor_product_certification_approved';
    }

    public function notificationData(object $notifiable): array
    {
        $productName = $this->certification->product?->name_en;
        $countryName = $this->certification->country?->name_en;

        return [
            'title' => 'Certification Approved',
            'message' => "Your certification for {$productName} in {$countryName} was approved.",
            'url' => route('partner.product-certifications.index'),
            'certification_id' => $this->certification->id,
        ];
    }

    public function broadcastOn(): array
    {
        return [];
    }
}
