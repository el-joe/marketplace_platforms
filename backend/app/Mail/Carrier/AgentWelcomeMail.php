<?php

namespace App\Mail\Carrier;

use App\Models\DeliveryAgent;
use App\Models\ShippingCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgentWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly DeliveryAgent $agent,
        public readonly ShippingCompany $company,
        public readonly string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.carrier_agent_welcome.subject', ['company' => $this->company->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.carrier.agent-welcome',
        );
    }
}
