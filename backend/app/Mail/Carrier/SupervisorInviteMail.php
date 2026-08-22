<?php

namespace App\Mail\Carrier;

use App\Models\ShippingCompany;
use App\Models\ShippingCompanySupervisor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupervisorInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ShippingCompanySupervisor $supervisor,
        public readonly ShippingCompany $company,
        public readonly string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.carrier_supervisor_invite.subject', ['company' => $this->company->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.carrier.supervisor-invite',
        );
    }
}
