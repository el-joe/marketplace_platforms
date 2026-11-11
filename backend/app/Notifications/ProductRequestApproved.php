<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\ProductRequest;
use App\Notifications\Concerns\ResolvesProductRequestUrl;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the requesting vendor/marketer team member that their product request was
 * approved, naming the product exactly as the admin created it in the catalog.
 * Sent to VendorAdmin or MarketerAdmin notifiables (one instance per recipient,
 * see ProductRequestService::notifyRequester()).
 */
class ProductRequestApproved extends BaseDatabaseBroadcastNotification
{
    use ResolvesProductRequestUrl;

    public function __construct(
        public readonly ProductRequest $productRequest,
        public readonly Product $product,
        public readonly string $broadcastChannel,
    ) {}

    /**
     * Build an instance addressed to one VendorAdmin / MarketerAdmin.
     */
    public static function for(object $notifiable, ProductRequest $productRequest, Product $product): self
    {
        return new self($productRequest, $product, self::requesterChannelName($notifiable));
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function notificationType(): string
    {
        return 'product_request_approved';
    }

    public function notificationData(object $notifiable): array
    {
        $params = fn (string $locale): array => [
            'number' => $this->productRequest->request_number,
            'product' => $this->productName($locale),
        ];

        return [
            'title' => __('notifications.product_requests.approved.title'),
            'message' => __('notifications.product_requests.approved.message', $params(app()->getLocale())),
            'title_en' => __('notifications.product_requests.approved.title', [], 'en'),
            'title_ar' => __('notifications.product_requests.approved.title', [], 'ar'),
            'message_en' => __('notifications.product_requests.approved.message', $params('en'), 'en'),
            'message_ar' => __('notifications.product_requests.approved.message', $params('ar'), 'ar'),
            'url' => $this->requesterPanelUrl($this->productRequest),
            'product_request_id' => $this->productRequest->id,
            'request_number' => $this->productRequest->request_number,
            'product_id' => $this->product->id,
            'product_name_en' => $this->product->name_en,
            'product_name_ar' => $this->product->name_ar,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->broadcastChannel)];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $params = [
            'number' => $this->productRequest->request_number,
            'product' => $this->productName(app()->getLocale()),
        ];

        return (new MailMessage)
            ->subject(__('notifications.product_requests.approved.mail_subject', $params))
            ->greeting(__('notifications.product_requests.approved.mail_greeting', ['name' => $notifiable->name ?? '']))
            ->line(__('notifications.product_requests.approved.mail_line', $params))
            ->line(__('notifications.product_requests.approved.mail_line_next'))
            ->action(__('notifications.product_requests.approved.mail_action'), $this->requesterPanelUrl($this->productRequest));
    }

    /**
     * Final catalog product name (not the requested one) in the given locale.
     */
    private function productName(string $locale): string
    {
        return $locale === 'ar'
            ? ($this->product->name_ar ?: $this->product->name_en)
            : ($this->product->name_en ?: $this->product->name_ar);
    }
}
