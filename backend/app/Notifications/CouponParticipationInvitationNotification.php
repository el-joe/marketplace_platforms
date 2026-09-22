<?php

namespace App\Notifications;

use App\Models\CouponParticipationInvitation;
use App\Models\VendorAdmin;

/**
 * Sent to vendors and marketers when a new coupon participation invitation
 * opens (client feature request #3.2), mirroring
 * Notifications\Vendor\CampaignInvitationAcceptedNotification. Works for
 * both notifiable types (VendorAdmin / MarketerAdmin) since invitations are
 * open to both.
 */
class CouponParticipationInvitationNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(private readonly CouponParticipationInvitation $invitation) {}

    public function notificationType(): string
    {
        return 'coupon_participation_invitation_opened';
    }

    public function notificationData(object $notifiable): array
    {
        $isVendor = $notifiable instanceof VendorAdmin;

        return [
            'title' => 'دعوة مشاركة في قسيمة',
            'message' => "دعوة جديدة للمشاركة في قسيمة برسوم اشتراك: {$this->invitation->title}",
            'url' => $isVendor ? route('vendor.coupon-participation.index') : route('marketer.coupon-participation.index'),
            'invitation_id' => $this->invitation->id,
            'min_fee_amount' => $this->invitation->min_fee_amount,
            'currency' => $this->invitation->currency,
            'registration_deadline' => $this->invitation->registration_deadline,
        ];
    }

    public function broadcastOn(): array
    {
        // Mirrors CampaignInvitationAcceptedNotification, which also opts
        // out of the realtime broadcast channel and relies on the
        // 'database' channel only.
        return [];
    }
}
