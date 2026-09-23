<?php

namespace App\Notifications\Marketer;

use App\Models\FlashSaleMarketerInvitation;
use App\Notifications\BaseDatabaseBroadcastNotification;
use Illuminate\Broadcasting\PrivateChannel;

class FlashSaleInvitationNotification extends BaseDatabaseBroadcastNotification
{
    public function __construct(
        public readonly FlashSaleMarketerInvitation $invitation,
        public readonly string $marketerAdminId,
    ) {}

    public function notificationType(): string
    {
        return 'flash_sale_invitation_received';
    }

    public function notificationData(object $notifiable): array
    {
        $flashSale = $this->invitation->flashSale;

        return [
            'invitation_id'         => $this->invitation->id,
            'flash_sale_id'         => $this->invitation->flash_sale_id,
            'extra_commission_rate' => $this->invitation->extra_commission_rate,
            'extra_commission_mode' => $this->invitation->extra_commission_mode,
            'extra_commission_flat_amount' => $this->invitation->extra_commission_flat_amount,
            'extra_commission_label' => $this->invitation->commissionLabel(),
            'title'                 => 'دعوة تخفيضات سريعة',
            'message'               => 'تمت دعوتك للترويج لتخفيضات "' . ($flashSale?->name_ar ?? '') . '" بعمولة إضافية ' . $this->invitation->commissionLabel() . '.',
            'url'                   => route('marketer.flash-sales.index'),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('marketer.' . $this->marketerAdminId)];
    }
}
