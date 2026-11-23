<?php

namespace App\Mail\Customer;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Storefront "forgot password" email: a single-use link to /{country}-{lang}/reset-password.
 *
 * Rendered in the language the customer used on the storefront (set via ->locale()).
 * Sent synchronously through CredentialDeliveryService so failures are logged, not thrown.
 */
class CustomerPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $recipientEmail,
        public readonly string $resetUrl,
        public readonly int $expiresInMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.customer_password_reset.subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.customer.password-reset');
    }
}
