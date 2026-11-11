<?php

namespace App\Notifications;

use App\Models\ProductRequest;
use App\Notifications\Concerns\ResolvesProductRequestUrl;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Tells the requesting vendor/marketer team member that their product request was
 * rejected, with the admin's reason. Database + broadcast only.
 */
class ProductRequestRejected extends BaseDatabaseBroadcastNotification
{
    use ResolvesProductRequestUrl;

    public function __construct(
        public readonly ProductRequest $productRequest,
        public readonly string $reason,
        public readonly string $broadcastChannel,
    ) {}

    /**
     * Build an instance addressed to one VendorAdmin / MarketerAdmin.
     */
    public static function for(object $notifiable, ProductRequest $productRequest, string $reason): self
    {
        return new self($productRequest, $reason, self::requesterChannelName($notifiable));
    }

    public function notificationType(): string
    {
        return 'product_request_rejected';
    }

    public function notificationData(object $notifiable): array
    {
        $params = fn (string $locale): array => [
            'number' => $this->productRequest->request_number,
            'name' => $this->productRequest->displayName($locale),
            'reason' => $this->reason,
        ];

        return [
            'title' => __('notifications.product_requests.rejected.title'),
            'message' => __('notifications.product_requests.rejected.message', $params(app()->getLocale())),
            'title_en' => __('notifications.product_requests.rejected.title', [], 'en'),
            'title_ar' => __('notifications.product_requests.rejected.title', [], 'ar'),
            'message_en' => __('notifications.product_requests.rejected.message', $params('en'), 'en'),
            'message_ar' => __('notifications.product_requests.rejected.message', $params('ar'), 'ar'),
            'url' => $this->requesterPanelUrl($this->productRequest),
            'product_request_id' => $this->productRequest->id,
            'request_number' => $this->productRequest->request_number,
            'rejection_reason' => $this->reason,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->broadcastChannel)];
    }
}
