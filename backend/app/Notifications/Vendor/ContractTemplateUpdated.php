<?php

namespace App\Notifications\Vendor;

use App\Models\ClassifiedContractTemplate;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class ContractTemplateUpdated extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly ClassifiedContractTemplate $template,
        private readonly string $vendorAdminId,
    ) {}

    public function notificationType(): string
    {
        return 'contract_template_updated';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title' => __('notifications.vendor.contract_updated_title'),
            'message' => __('notifications.vendor.contract_updated_message', [
                'name' => $this->template->name,
                'version' => $this->template->version,
            ]),
            'template_id' => $this->template->id,
            'version' => $this->template->version,
            'url' => route('partner.contracts.pending'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('vendor.'.$this->vendorAdminId)];
    }
}
