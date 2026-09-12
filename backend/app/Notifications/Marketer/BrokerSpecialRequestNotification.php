<?php

namespace App\Notifications\Marketer;

use App\Models\CustomerSpecialRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class BrokerSpecialRequestNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly CustomerSpecialRequest $specialRequest,
        private readonly string $marketerAdminId,
    ) {}

    public function notificationType(): string
    {
        return 'broker_special_request';
    }

    public function notificationData(object $notifiable): array
    {
        return [
            'title'       => 'New Customer Request',
            'title_ar'    => 'طلب جديد من عميل',
            'message'     => "A customer is looking for: \"{$this->specialRequest->title_en}\" in " . ($this->specialRequest->city?->name_en ?? 'your area') . '.',
            'message_ar'  => "عميل يبحث عن: \"{$this->specialRequest->title_ar}\" في " . ($this->specialRequest->city?->name_ar ?? 'كل المدن') . '.',
            'url'         => route('marketer.special-requests.show', $this->specialRequest->id),
            'request_id'  => $this->specialRequest->id,
            'category_id' => $this->specialRequest->category_id,
            'city_id'     => $this->specialRequest->city_id,
            'budget'      => $this->specialRequest->budget,
            'currency'    => $this->specialRequest->budget_currency,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('marketer.' . $this->marketerAdminId)];
    }
}
