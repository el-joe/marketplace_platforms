<?php

namespace App\Notifications\Customer;

use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Base for all customer-facing notifications.
 *
 * Adds 'mail' to the via() channels and provides a default toMail()
 * derived from notificationData(). Concrete subclasses implement
 * notificationType(), notificationData(), and broadcastOn() exactly
 * as they would for any BaseDatabaseBroadcastNotification.
 *
 * broadcastOn() should return [new PrivateChannel('customer.' . $customerId)]
 * where $customerId is accessible from the model stored in the constructor.
 *
 * NOTE: SMS and WhatsApp channels are intentionally omitted. No gateway
 * integration (Twilio, Vonage, Infobip, etc.) exists in this codebase yet.
 * Add those channels here once a gateway service is configured.
 */
abstract class BaseCustomerNotification extends BaseDatabaseBroadcastNotification
{
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->notificationData($notifiable);
        $locale = $notifiable->locale ?? 'ar';

        $title = $locale === 'ar' ? ($data['title_ar'] ?? $data['title'] ?? 'إشعار') : ($data['title'] ?? 'Notification');
        $message = $locale === 'ar' ? ($data['message_ar'] ?? $data['message'] ?? '') : ($data['message'] ?? '');

        $mail = (new MailMessage)
            ->subject($title)
            ->line($message);

        if (!empty($data['url'])) {
            $mail->action($locale === 'ar' ? 'عرض التفاصيل' : 'View Details', $data['url']);
        }

        return $mail;
    }
}
