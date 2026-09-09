<?php

namespace App\Notifications\Marketer;

use App\Models\FlashSaleMarketerInvitation;
use Illuminate\Notifications\Notification;

class FlashSaleInvitationNotification extends Notification
{
    public function __construct(public readonly FlashSaleMarketerInvitation $invitation) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'flash_sale_invitation_received',
            'invitation_id' => $this->invitation->id,
            'flash_sale_id' => $this->invitation->flash_sale_id,
            'extra_commission_rate' => $this->invitation->extra_commission_rate,
        ];
    }
}
