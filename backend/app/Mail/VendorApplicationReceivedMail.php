<?php

namespace App\Mail;

use App\Models\Vendor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VendorApplicationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Vendor $vendor)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.vendor_application_received.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.vendor.application-received',
        );
    }
}
