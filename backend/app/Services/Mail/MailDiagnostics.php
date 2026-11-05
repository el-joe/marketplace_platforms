<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Reports what the application will really do with outgoing mail (the effective
 * configuration after the `mail_*` settings rows have overridden .env) and
 * sends a real test message.
 */
class MailDiagnostics
{
    /** Mailers that never reach an inbox. */
    private const NON_DELIVERING = ['log', 'array'];

    private const LOOPBACK_HOSTS = ['', '127.0.0.1', 'localhost', '::1', '0.0.0.0'];

    public function effectiveMailer(): string
    {
        return (string) config('mail.default');
    }

    public function isNonDelivering(): bool
    {
        return in_array($this->effectiveMailer(), self::NON_DELIVERING, true);
    }

    /**
     * @return array{mailer: string, host: ?string, port: ?int, from_address: string, from_name: string}
     */
    public function summary(): array
    {
        $mailer = $this->effectiveMailer();
        $isSmtp = config("mail.mailers.{$mailer}.transport") === 'smtp';

        return [
            'mailer' => $mailer,
            'host' => $isSmtp ? (string) config("mail.mailers.{$mailer}.host") : null,
            'port' => $isSmtp ? (int) config("mail.mailers.{$mailer}.port") : null,
            'from_address' => (string) config('mail.from.address'),
            'from_name' => (string) config('mail.from.name'),
        ];
    }

    /**
     * Human-readable problems that would stop mail reaching real inboxes.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $summary = $this->summary();
        $warnings = [];

        if ($this->isNonDelivering()) {
            $warnings[] = __('admin.mail_diagnostics.warning_non_delivering', ['mailer' => $summary['mailer']]);
        } elseif ($summary['host'] !== null && in_array(strtolower($summary['host']), self::LOOPBACK_HOSTS, true)) {
            $warnings[] = __('admin.mail_diagnostics.warning_loopback_host', ['host' => $summary['host'] ?: '—']);
        }

        $fromDomain = strtolower((string) substr(strrchr($summary['from_address'], '@') ?: '', 1));
        if ($summary['from_address'] === '' || in_array($fromDomain, ['example.com', 'example.org', 'example.net'], true)) {
            $warnings[] = __('admin.mail_diagnostics.warning_placeholder_from', ['from' => $summary['from_address'] ?: '—']);
        }

        return $warnings;
    }

    /**
     * Send a real message synchronously and report the real outcome.
     *
     * @return array{ok: bool, message: string, mailer: string}
     */
    public function sendTest(string $recipient): array
    {
        $mailer = $this->effectiveMailer();

        try {
            Mail::raw(
                __('admin.mail_diagnostics.test_body', ['app' => config('app.name')]),
                function ($message) use ($recipient): void {
                    $message->to($recipient)->subject(__('admin.mail_diagnostics.test_subject', ['app' => config('app.name')]));
                }
            );
        } catch (Throwable $e) {
            Log::error('Test email failed', [
                'flow' => 'settings_test_email',
                'recipient' => $recipient,
                'mailer' => $mailer,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'mailer' => $mailer,
                'message' => __('admin.mail_diagnostics.test_failed', ['error' => $e->getMessage()]),
            ];
        }

        if ($this->isNonDelivering()) {
            return [
                'ok' => false,
                'mailer' => $mailer,
                'message' => __('admin.mail_diagnostics.test_not_delivering', ['mailer' => $mailer]),
            ];
        }

        return [
            'ok' => true,
            'mailer' => $mailer,
            'message' => __('admin.mail_diagnostics.test_sent', ['email' => $recipient]),
        ];
    }
}
