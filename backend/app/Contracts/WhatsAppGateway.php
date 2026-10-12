<?php

namespace App\Contracts;

/**
 * Outbound WhatsApp text delivery. Implementations must return true ONLY when the
 * provider accepted the message, so callers can record "sent" honestly, and expose
 * why the last attempt failed (shown to admins on the campaign page).
 */
interface WhatsAppGateway
{
    public function send(string $to, string $message): bool;

    /** Machine/human readable reason of the last failed send(), null after a success. */
    public function lastError(): ?string;

    /** False for drivers that cannot really deliver (log driver / missing credentials): retrying is pointless. */
    public function isConfigured(): bool;
}
