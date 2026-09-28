<?php

namespace App\Notifications;

use App\Models\CouponParticipationRequest;
use App\Models\VendorAdmin;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to the requesting vendor/marketer when the admin approves or rejects
 * their coupon participation request (client feature request #3.2).
 */
class CouponParticipationRequestDecisionNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly CouponParticipationRequest $participationRequest,
        private readonly string $notifiableId,
        private readonly string $guard,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function notificationType(): string
    {
        return 'coupon_participation_request_'.$this->participationRequest->status;
    }

    public function notificationData(object $notifiable): array
    {
        $isVendor = $notifiable instanceof VendorAdmin;
        $approved = in_array($this->participationRequest->status, [CouponParticipationRequest::STATUS_APPROVED, CouponParticipationRequest::STATUS_PAID], true);

        return [
            'title' => $approved ? 'تم قبول طلب المشاركة' : 'تم رفض طلب المشاركة',
            'message' => $approved
                ? 'تم قبول طلبك للمشاركة في قسيمة برسوم اشتراك.'
                : 'تم رفض طلبك للمشاركة في قسيمة برسوم اشتراك.',
            'url' => $isVendor ? route('partner.coupon-participation.index') : route('marketer.coupon-participation.index'),
            'invitation_id' => $this->participationRequest->invitation_id,
            'request_id' => $this->participationRequest->id,
            'status' => $this->participationRequest->status,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->guard.'.'.$this->notifiableId)];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->notificationData($notifiable);

        return (new MailMessage)
            ->subject($data['title'])
            ->line($data['message'])
            ->action('عرض التفاصيل', $data['url']);
    }
}
