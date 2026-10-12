<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Log;

/** Default driver: records the message in the log and reports "not delivered" (never pretends it was sent). */
class LogWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $to, string $message): bool
    {
        Log::info('WhatsApp (log driver) message not delivered', ['to' => preg_replace('/\D+/', '', $to), 'message' => $message]);

        return false;
    }

    public function lastError(): ?string
    {
        return 'whatsapp_not_configured';
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
