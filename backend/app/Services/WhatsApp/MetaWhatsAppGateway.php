<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** WhatsApp Cloud API driver. */
class MetaWhatsAppGateway implements WhatsAppGateway
{
    private ?string $error = null;

    public function isConfigured(): bool
    {
        return filled($this->phoneNumberId()) && filled($this->token());
    }

    public function send(string $to, string $message): bool
    {
        $this->error = null;
        $number = preg_replace('/\D+/', '', $to);

        if ($number === '') {
            $this->error = 'invalid_number';

            return false;
        }

        if (! $this->isConfigured()) {
            $this->error = 'whatsapp_not_configured';

            return false;
        }

        try {
            $response = Http::withToken((string) $this->token())
                ->timeout(15)
                ->post(rtrim((string) config('whatsapp.meta.api_url'), '/').'/'.$this->phoneNumberId().'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $number,
                    'type' => 'text',
                    'text' => ['preview_url' => true, 'body' => $message],
                ]);
        } catch (\Throwable $e) {
            $this->error = 'transport: '.$e->getMessage();
            Log::warning('WhatsApp send failed', ['to' => $number, 'error' => $e->getMessage()]);

            return false;
        }

        if ($response->failed()) {
            $this->error = 'http_'.$response->status().': '.mb_substr($response->body(), 0, 300);
            Log::warning('WhatsApp send failed', ['to' => $number, 'status' => $response->status(), 'body' => $response->body()]);

            return false;
        }

        return true;
    }

    public function lastError(): ?string
    {
        return $this->error;
    }

    private function phoneNumberId(): mixed
    {
        return setting('whatsapp_meta_phone_number_id') ?: config('whatsapp.meta.phone_number_id');
    }

    private function token(): mixed
    {
        return setting('whatsapp_meta_access_token') ?: config('whatsapp.meta.access_token');
    }
}
