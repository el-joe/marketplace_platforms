<?php

namespace App\Notifications;

use App\Models\TravelAgencyMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TravelAgencyMemberPasswordReset extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly TravelAgencyMember $member,
        public readonly string $tempPassword,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.travel_agency_password_reset.subject'))
            ->greeting(__('mail.travel_agency_password_reset.greeting', ['name' => $this->member->name]))
            ->line(__('mail.travel_agency_password_reset.body'))
            ->line(__('mail.travel_agency_password_reset.email_label') . ': ' . $this->member->email)
            ->line(__('mail.travel_agency_password_reset.password_label') . ': ' . $this->tempPassword)
            ->line(__('mail.travel_agency_password_reset.warning'))
            ->action(__('mail.travel_agency_password_reset.login_button'), route('travel-agency.login'))
            ->salutation(__('mail.travel_agency_password_reset.footer_disclaimer'));
    }
}
