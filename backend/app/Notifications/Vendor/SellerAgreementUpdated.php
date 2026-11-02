<?php

namespace App\Notifications\Vendor;

use App\Models\ClassifiedContractTemplate;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class SellerAgreementUpdated extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly ClassifiedContractTemplate $template,
        private readonly string $vendorAdminId,
    ) {}

    public function notificationType(): string
    {
        return 'seller_agreement_updated';
    }

    public function notificationData(object $notifiable): array
    {
        $isNotice = $this->template->acceptance_mode === ClassifiedContractTemplate::ACCEPTANCE_NOTICE;

        return [
            'title' => __('notifications.vendor.agreement_updated_title'),
            'message' => $isNotice
                ? __('notifications.vendor.agreement_notice_message', ['version' => $this->template->version, 'date' => $this->template->effective_at?->format('d/m/Y')])
                : __('notifications.vendor.agreement_accept_message', ['version' => $this->template->version]),
            'template_id' => $this->template->id,
            'version' => $this->template->version,
            'url' => route('partner.legal.index'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('vendor.'.$this->vendorAdminId)];
    }
}
