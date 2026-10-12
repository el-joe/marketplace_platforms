<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin WhatsApp sender. Provider is chosen by the `whatsapp_provider` setting:
 *  - "meta"  : WhatsApp Cloud API (needs whatsapp_meta_phone_number_id + whatsapp_meta_access_token)
 *  - "log"   : (default) nothing is delivered, the message is only logged and send() returns false
 *
 * send() returns true only when the provider accepted the message, so callers can
 * record "sent" honestly.
 */
class WhatsAppService
{
    public function isConfigured(): bool
    {
        return $this->provider() === 'meta'
            && filled(setting('whatsapp_meta_phone_number_id'))
            && filled(setting('whatsapp_meta_access_token'));
    }

    public function send(string $to, string $message): bool
    {
        $number = preg_replace('/\D+/', '', $to);
        if ($number === '') {
            return false;
        }

        if (! $this->isConfigured()) {
            Log::info('WhatsApp not configured; message not delivered', ['to' => $number, 'message' => $message]);

            return false;
        }

        $response = Http::withToken((string) setting('whatsapp_meta_access_token'))
            ->timeout(15)
            ->post('https://graph.facebook.com/v20.0/'.setting('whatsapp_meta_phone_number_id').'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $number,
                'type' => 'text',
                'text' => ['preview_url' => true, 'body' => $message],
            ]);

        if ($response->failed()) {
            Log::warning('WhatsApp send failed', ['to' => $number, 'status' => $response->status(), 'body' => $response->body()]);

            return false;
        }

        return true;
    }

    private function provider(): string
    {
        return (string) setting('whatsapp_provider', 'log');
    }
}
