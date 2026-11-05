<?php

namespace App\Services\Auth;

use App\Mail\CredentialLinkMail;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Single place that emails account access: a broker token wrapped in a
 * set-password / reset link. Failures are logged (recipient, flow, reason - never
 * a password or token) and returned so the caller can warn the person who triggered it.
 */
class CredentialDeliveryService
{
    /** Password broker => named web route that renders its set-password page. */
    private const RESET_ROUTES = [
        'admins' => 'admin.password.reset',
        'vendor_admins' => 'partner.auth.reset',
        'marketer_admins' => 'marketer.auth.reset',
    ];

    /** Password broker => named web route to the matching login page. */
    private const LOGIN_ROUTES = [
        'admins' => 'admin.login',
        'vendor_admins' => 'partner.login',
        'marketer_admins' => 'marketer.login',
    ];

    /**
     * Issue a fresh broker token for $user and email the set-password link.
     *
     * @param  array<string, string>  $context
     */
    public function sendSetPasswordLink(CanResetPassword $user, string $broker, string $flow, string $kind, array $context = []): CredentialDeliveryResult
    {
        $token = Password::broker($broker)->createToken($user);

        return $this->deliverLink($user, $broker, $token, $flow, $kind, $context);
    }

    /**
     * Mail the link for a token the broker already created (self-service "forgot password").
     */
    public function sendResetToken(CanResetPassword $user, string $broker, string $token): CredentialDeliveryResult
    {
        return $this->deliverLink($user, $broker, $token, 'forgot_password', 'forgot_password');
    }

    /**
     * Send any credential mailable synchronously, logging a failure instead of swallowing it.
     */
    public function deliver(string $recipient, Mailable $mailable, string $flow): CredentialDeliveryResult
    {
        try {
            Mail::to($recipient)->send($mailable);
        } catch (Throwable $e) {
            Log::error('Credential email failed', [
                'flow' => $flow,
                'recipient' => $recipient,
                'mailer' => config('mail.default'),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return CredentialDeliveryResult::failed($recipient, $e->getMessage());
        }

        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) {
            Log::warning('Credential email accepted by a non-delivering mailer', [
                'flow' => $flow,
                'recipient' => $recipient,
                'mailer' => $mailer,
            ]);

            return new CredentialDeliveryResult(true, $recipient, null, $mailer);
        }

        return new CredentialDeliveryResult(true, $recipient);
    }

    public function resetUrl(string $broker, string $token, string $email): ?string
    {
        $route = self::RESET_ROUTES[$broker] ?? null;

        if ($route === null || ! Route::has($route)) {
            return null;
        }

        return route($route, ['token' => $token, 'email' => $email]);
    }

    public function loginUrl(string $broker): ?string
    {
        $route = self::LOGIN_ROUTES[$broker] ?? null;

        return $route !== null && Route::has($route) ? route($route) : null;
    }

    /**
     * @param  array<string, string>  $context
     */
    private function deliverLink(CanResetPassword $user, string $broker, string $token, string $flow, string $kind, array $context = []): CredentialDeliveryResult
    {
        $email = $user->getEmailForPasswordReset();

        $mail = new CredentialLinkMail(
            kind: $kind,
            recipientName: (string) ($user->name ?? ''),
            recipientEmail: $email,
            setPasswordUrl: $this->resetUrl($broker, $token, $email),
            resetCode: $token,
            loginUrl: $this->loginUrl($broker),
            expiresInMinutes: (int) config("auth.passwords.{$broker}.expire", 60),
            context: $context,
        );

        return $this->deliver($email, $mail, $flow);
    }
}
