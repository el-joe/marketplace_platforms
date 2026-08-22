<?php

namespace App\Mail;

use App\Models\TravelAgencyMember;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TravelAgencyTeamMemberInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly TravelAgencyMember $member,
        public readonly string $temporaryPassword,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.travel_agency_invite.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.travel-agency.team-member-invite',
        );
    }
}
