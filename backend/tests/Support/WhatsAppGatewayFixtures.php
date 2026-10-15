<?php

namespace Tests\Support;

use App\Contracts\WhatsAppGateway;

/** Shared in-memory WhatsApp gateway fake for the marketer campaign tests. */
class WhatsAppGatewayFixtures
{
    public static function fakeGateway(): WhatsAppGateway
    {
        return new class implements WhatsAppGateway
        {
            public array $sent = [];

            public function send(string $to, string $message): bool
            {
                $this->sent[] = compact('to', 'message');

                return true;
            }

            public function lastError(): ?string
            {
                return null;
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };
    }
}
