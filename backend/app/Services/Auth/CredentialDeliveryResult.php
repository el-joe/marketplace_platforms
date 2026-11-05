<?php

namespace App\Services\Auth;

/**
 * Outcome of one attempt to email credentials / a set-password link.
 *
 * `sent` only says the mail transport accepted the message. When the effective
 * mailer is `log` or `array` nothing is delivered, which `nonDeliveringMailer` records.
 */
final class CredentialDeliveryResult
{
    public function __construct(
        public readonly bool $sent,
        public readonly string $recipient,
        public readonly ?string $error = null,
        public readonly ?string $nonDeliveringMailer = null,
    ) {}

    public static function failed(string $recipient, string $error): self
    {
        return new self(false, $recipient, $error);
    }

    /**
     * True only when the mail was handed to a transport that really delivers.
     */
    public function delivered(): bool
    {
        return $this->sent && $this->nonDeliveringMailer === null;
    }

    /**
     * Admin-facing warning, or null when the email went out.
     */
    public function warning(): ?string
    {
        if (! $this->sent) {
            return __('mail.credentials.send_failed', ['email' => $this->recipient]);
        }

        if ($this->nonDeliveringMailer !== null) {
            return __('mail.credentials.non_delivering', ['mailer' => $this->nonDeliveringMailer, 'email' => $this->recipient]);
        }

        return null;
    }

    /**
     * Fields to merge into a JSON response.
     *
     * @return array{email_sent: bool, warning?: string}
     */
    public function toResponseFields(): array
    {
        $fields = ['email_sent' => $this->delivered()];

        if (($warning = $this->warning()) !== null) {
            $fields['warning'] = $warning;
        }

        return $fields;
    }
}
