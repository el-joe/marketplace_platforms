<?php

namespace App\Services;

use App\Contracts\WhatsAppGateway;
use App\Services\WhatsApp\LogWhatsAppGateway;
use App\Services\WhatsApp\MetaWhatsAppGateway;

/**
 * Default WhatsAppGateway binding: delegates to the driver chosen by the
 * `whatsapp_provider` setting (fallback config('whatsapp.driver')):
 *  - "meta": WhatsApp Cloud API (MetaWhatsAppGateway)
 *  - "log" : (default) nothing is delivered, message only logged, send() returns false
 * Tests swap the whole contract with a fake (`$this->app->instance(WhatsAppGateway::class, ...)`).
 */
class WhatsAppService implements WhatsAppGateway
{
    private ?WhatsAppGateway $last = null;

    public function driver(): WhatsAppGateway
    {
        return match ((string) (setting('whatsapp_provider') ?: config('whatsapp.driver', 'log'))) {
            'meta' => new MetaWhatsAppGateway,
            default => new LogWhatsAppGateway,
        };
    }

    public function isConfigured(): bool
    {
        return $this->driver()->isConfigured();
    }

    public function send(string $to, string $message): bool
    {
        $this->last = $this->driver();

        return $this->last->send($to, $message);
    }

    public function lastError(): ?string
    {
        return $this->last?->lastError();
    }
}
