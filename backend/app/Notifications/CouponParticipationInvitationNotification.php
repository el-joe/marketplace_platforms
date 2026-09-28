<?php

namespace App\Notifications;

use App\Models\CouponParticipationInvitation;
use App\Models\VendorAdmin;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to vendors and marketers when a new coupon participation invitation
 * opens (client feature request #3.2), mirroring
 * Notifications\Vendor\CampaignInvitationAcceptedNotification. Works for
 * both notifiable types (VendorAdmin / MarketerAdmin) since invitations are
 * open to both.
 */
class CouponParticipationInvitationNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        private readonly CouponParticipationInvitation $invitation,
        private readonly string $notifiableId,
        private readonly string $guard,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function notificationType(): string
    {
        return 'coupon_participation_invitation_opened';
    }

    public function notificationData(object $notifiable): array
    {
        $isVendor = $notifiable instanceof VendorAdmin;

        return [
            'title' => '⚠️ دعوة مشاركة في قسيمة — مقاعد محدودة',
            'message' => sprintf(
                '⚠️ تنبيه: دعوة للمشاركة في قسيمة "%s" برسوم اشتراك. الحد الأقصى للمشاركين: %d. آخر موعد: %s. يمكنك عرض رسوم أعلى من %s %s لزيادة فرص القبول.',
                $this->invitation->title ?? 'قسيمة جديدة',
                $this->invitation->max_participants,
                $this->invitation->registration_deadline->format('Y-m-d'),
                number_format($this->invitation->min_fee_amount),
                $this->invitation->currency
            ),
            'url' => $isVendor ? route('partner.coupon-participation.index') : route('marketer.coupon-participation.index'),
            'invitation_id' => $this->invitation->id,
            'min_fee_amount' => $this->invitation->min_fee_amount,
            'currency' => $this->invitation->currency,
            'registration_deadline' => $this->invitation->registration_deadline,
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->guard.'.'.$this->notifiableId)];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isVendor = $notifiable instanceof VendorAdmin;
        $url = $isVendor
            ? route('partner.coupon-participation.index')
            : route('marketer.coupon-participation.index');

        return (new MailMessage)
            ->subject('⚠️ دعوة مشاركة في قسيمة جديدة — مقاعد محدودة')
            ->greeting('تنبيه مهم!')
            ->line('لديك دعوة جديدة للمشاركة في قسيمة خصم برسوم اشتراك.')
            ->line('**العنوان:** '.($this->invitation->title ?? 'دعوة مشاركة في قسيمة'))
            ->line('**الحد الأدنى للرسوم:** '.number_format($this->invitation->min_fee_amount).' '.$this->invitation->currency)
            ->line('**الحد الأقصى للمشاركين:** '.$this->invitation->max_participants)
            ->line('**آخر موعد للتسجيل:** '.$this->invitation->registration_deadline->format('Y-m-d H:i'))
            ->action('المشاركة الآن', $url)
            ->line('⚠️ تنبيه: عدد المقاعد محدود. يمكنك عرض رسوم أعلى من الحد الأدنى لزيادة فرص قبول طلبك.');
    }
}
