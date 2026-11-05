<?php

namespace App\Models\Concerns;

use App\Services\Auth\CredentialDeliveryService;

/**
 * Routes the framework's reset notification through CredentialDeliveryService so the
 * emailed link points at the panel that owns the broker (the stock notification
 * looks for a `password.reset` route that this application does not define).
 *
 * A mail failure is logged with context but not thrown: the requester is anonymous
 * and must not learn whether the address exists.
 */
trait SendsPasswordResetLink
{
    /**
     * Name of the password broker (config/auth.php `passwords`) for this model.
     */
    abstract public function passwordResetBroker(): string;

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        app(CredentialDeliveryService::class)->sendResetToken($this, $this->passwordResetBroker(), (string) $token);
    }
}
