<?php

namespace Tests\Support;

use App\Mail\CredentialLinkMail;
use App\Models\Admin;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * Shared setup for the credential-email tests: a "real" mailer so no-warning assertions hold,
 * admins with explicit permissions, and helpers to make the transport fail.
 */
trait CredentialMailFixtures
{
    protected function useDeliveringMailer(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.mailprovider.test',
            'mail.from.address' => 'no-reply@platform.test',
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function adminWith(array $permissions): Admin
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $admin = Admin::factory()->create();
        $admin->givePermissionTo($permissions);

        return $admin;
    }

    /**
     * Make the next Mail::to()->send() throw, as an unreachable / rejecting SMTP server would.
     */
    protected function makeMailerFail(string $reason = 'Connection refused'): void
    {
        Mail::shouldReceive('to')->andThrow(new TransportException($reason));
    }

    protected function sentCredentialMail(string $kind): CredentialLinkMail
    {
        $sent = Mail::sent(CredentialLinkMail::class, fn (CredentialLinkMail $mail) => $mail->kind === $kind);
        $this->assertCount(1, $sent, "Expected exactly one {$kind} credential email");

        return $sent->first();
    }

    /**
     * Assert the failure was logged with context and without any secret.
     */
    protected function assertCredentialFailureLogged(string $flow, string $recipient): void
    {
        Log::shouldHaveReceived('error')->withArgs(function ($message, $context = []) use ($flow, $recipient): bool {
            return $message === 'Credential email failed'
                && ($context['flow'] ?? null) === $flow
                && ($context['recipient'] ?? null) === $recipient
                && ! empty($context['error'])
                && ! str_contains(json_encode(array_diff_key($context, ['flow' => 1])), 'token')
                && ! str_contains(strtolower(json_encode(array_diff_key($context, ['flow' => 1]))), 'password');
        })->once();
    }
}
