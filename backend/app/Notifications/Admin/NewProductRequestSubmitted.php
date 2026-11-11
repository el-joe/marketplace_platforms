<?php

namespace App\Notifications\Admin;

use App\Models\ProductRequest;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Tells a reviewing admin that a vendor or marketer submitted a new product request.
 * One instance per admin (the broadcast channel is admin.{id}).
 */
class NewProductRequestSubmitted extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        public readonly ProductRequest $productRequest,
        public readonly string $adminId,
    ) {}

    public function notificationType(): string
    {
        return 'new_product_request_submitted';
    }

    public function notificationData(object $notifiable): array
    {
        $params = fn (string $locale): array => [
            'requester' => $this->productRequest->requesterName(),
            'name' => $this->productRequest->displayName($locale),
            'number' => $this->productRequest->request_number,
        ];

        return [
            'title' => __('notifications.product_requests.submitted.title'),
            'message' => __('notifications.product_requests.submitted.message', $params(app()->getLocale())),
            'title_en' => __('notifications.product_requests.submitted.title', [], 'en'),
            'title_ar' => __('notifications.product_requests.submitted.title', [], 'ar'),
            'message_en' => __('notifications.product_requests.submitted.message', $params('en'), 'en'),
            'message_ar' => __('notifications.product_requests.submitted.message', $params('ar'), 'ar'),
            'url' => route('admin.product-requests.show', $this->productRequest->id),
            'product_request_id' => $this->productRequest->id,
            'request_number' => $this->productRequest->request_number,
            'requester_type' => $this->productRequest->requester_type,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.'.$this->adminId)];
    }
}
