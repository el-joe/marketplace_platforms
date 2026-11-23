<?php

namespace App\Services\Customer;

use App\Enums\CustomerStatus;
use App\Mail\Customer\CustomerPasswordResetMail;
use App\Models\Country;
use App\Models\Customer;
use App\Services\Auth\CredentialDeliveryService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;

/**
 * Storefront self-service password reset on the `customers` broker.
 *
 * The request step never reveals whether an account exists: unknown, inactive or
 * email-less accounts and throttled requests are silently skipped by the caller's
 * generic response.
 */
class CustomerPasswordResetService
{
    private const BROKER = 'customers';

    public function __construct(private readonly CredentialDeliveryService $delivery) {}

    /**
     * Email a reset link when $emailOrPhone matches an active customer with an email address.
     */
    public function sendResetLink(string $emailOrPhone, Country $country, string $locale): void
    {
        $field = filter_var($emailOrPhone, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $customer = Customer::query()
            ->where($field, $emailOrPhone)
            ->where('status', CustomerStatus::Active)
            ->first();

        if ($customer === null || blank($customer->email)) {
            return;
        }

        Password::broker(self::BROKER)->sendResetLink(
            ['email' => $customer->email],
            function (Customer $customer, string $token) use ($country, $locale): void {
                $mail = (new CustomerPasswordResetMail(
                    recipientName: (string) $customer->name,
                    recipientEmail: $customer->email,
                    resetUrl: $this->resetUrl($country, $locale, $token, $customer->email),
                    expiresInMinutes: (int) config('auth.passwords.'.self::BROKER.'.expire', 60),
                ))->locale($locale);

                $this->delivery->deliver($customer->email, $mail, 'customer_forgot_password');
            },
        );
    }

    /**
     * Reset the password with a broker token; returns the broker status constant.
     *
     * @param  array{email: string, token: string, password: string, password_confirmation: string}  $credentials
     */
    public function reset(array $credentials): string
    {
        return Password::broker(self::BROKER)->reset(
            $credentials,
            function (Customer $customer, string $password): void {
                $customer->forceFill(['password' => $password])->save();

                event(new PasswordReset($customer));
            },
        );
    }

    /**
     * Storefront page: {frontend}/{country}-{lang}/reset-password?token=…&email=…
     */
    public function resetUrl(Country $country, string $locale, string $token, string $email): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');
        $segment = strtolower($country->site_code).'-'.$locale;

        return $base.'/'.$segment.'/reset-password?'.http_build_query(['token' => $token, 'email' => $email]);
    }
}
