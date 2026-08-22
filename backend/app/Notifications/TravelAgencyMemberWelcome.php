<?php

namespace App\Notifications;

use App\Models\TravelAgencyMember;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TravelAgencyMemberWelcome extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly TravelAgencyMember $member,
        public readonly string $password,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.travel_agency_invite.subject'))
            ->greeting(__('mail.travel_agency_invite.greeting', ['name' => $this->member->name]))
            ->line(__('mail.travel_agency_invite.body', [
                'role' => $this->member->role === 'travel_agency_manager'
                    ? __('mail.travel_agency_invite.role_manager')
                    : __('mail.travel_agency_invite.role_staff'),
            ]))
            ->line(__('mail.travel_agency_invite.email_label') . ': ' . $this->member->email)
            ->line(__('mail.travel_agency_invite.password_label') . ': ' . $this->password)
            ->line(__('mail.travel_agency_invite.warning'))
            ->action(__('mail.travel_agency_invite.login_button'), route('travel-agency.login'))
            ->salutation(__('mail.travel_agency_invite.footer_disclaimer'));
    }
}
