<?php

namespace App\Notifications\Customer;

use App\Models\ClassifiedInquiry;
use App\Models\Customer;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notifies the seller of a classified listing about a new buyer inquiry.
 *
 * The seller is polymorphic — it may be a Customer or a Vendor. This
 * notification is placed in the Customer\ namespace but is NOT limited
 * to Customer notifiables; it is dispatched via
 * $listing->seller->notify(new ClassifiedInquiryReceived($inquiry))
 * and therefore must handle both types in broadcastOn() and toMail().
 */
class ClassifiedInquiryReceived extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly ClassifiedInquiry $inquiry) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function notificationType(): string
    {
        return 'classified_inquiry_received';
    }

    public function notificationData(object $notifiable): array
    {
        $listing = $this->inquiry->listing;

        return [
            'title'      => 'New Inquiry on Your Listing',
            'title_ar'   => 'استفسار جديد على إعلانك',
            'message'    => "You have received a new inquiry on \"{$listing->title}\".",
            'message_ar' => "لقد تلقيت استفسارًا جديدًا على \"{$listing->title}\".",
            'url'        => $this->resolveListingUrl($notifiable),
            'inquiry_id' => $this->inquiry->id,
            'listing_id' => $listing->id,
            'listing_title' => $listing->title,
        ];
    }

    public function broadcastOn(): array
    {
        // Resolved when notifiable is available via toBroadcast().
        // Returning [] here; channel is set in broadcastChannels() below.
        return [];
    }

    /**
     * Laravel calls this when dispatching to broadcast channels.
     * Override so we can use the notifiable to pick the correct channel.
     */
    public function broadcastChannels(object $notifiable): array
    {
        if ($notifiable instanceof Customer) {
            return [new PrivateChannel('customer.' . $notifiable->id)];
        }

        // Vendor or any other Notifiable model
        return [new PrivateChannel('vendor.' . $notifiable->id)];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->notificationData($notifiable);
        $locale = $notifiable->locale ?? 'ar';

        $title = $locale === 'ar' ? ($data['title_ar'] ?? $data['title']) : $data['title'];
        $message = $locale === 'ar' ? ($data['message_ar'] ?? $data['message']) : $data['message'];

        return (new MailMessage)
            ->subject($title)
            ->line($message)
            ->action($locale === 'ar' ? 'عرض الاستفسار' : 'View Inquiry', $data['url']);
    }

    private function resolveListingUrl(object $notifiable): string
    {
        if ($notifiable instanceof Customer) {
            return route('customer.account.classified-listings.inquiries', [
                'listing_number' => $this->inquiry->listing->listing_number,
            ]);
        }

        // Vendor — route to partner classifieds if available
        return route('partner.classifieds.show', $this->inquiry->listing->listing_number);
    }
}
