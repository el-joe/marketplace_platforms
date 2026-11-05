<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Account email that carries a time-limited set-password link (never a password).
 *
 * Sent synchronously on purpose: callers need to know whether the transport accepted it.
 * The body is bilingual (Arabic + English) because the recipient's language is not known.
 *
 * Kinds: admin_welcome, admin_reset, vendor_approved, vendor_resend, vendor_team_invite, forgot_password.
 */
class CredentialLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string|null  $setPasswordUrl  null for brokers without a web page: the code is shown instead
     * @param  array<string, string>  $context  extra placeholders (e.g. store, role)
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $recipientName,
        public readonly string $recipientEmail,
        public readonly ?string $setPasswordUrl,
        public readonly string $resetCode,
        public readonly ?string $loginUrl,
        public readonly int $expiresInMinutes,
        public readonly array $context = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __("mail.credentials.kinds.{$this->kind}.subject", $this->placeholders(), 'ar')
                .' | '.__("mail.credentials.kinds.{$this->kind}.subject", $this->placeholders(), 'en'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.credentials.set-password',
            with: ['placeholders' => $this->placeholders()],
        );
    }

    /**
     * @return array<string, string|int>
     */
    public function placeholders(): array
    {
        return array_merge($this->context, [
            'name' => $this->recipientName,
            'email' => $this->recipientEmail,
            'hours' => max(1, (int) round($this->expiresInMinutes / 60)),
            'minutes' => $this->expiresInMinutes,
        ]);
    }
}
