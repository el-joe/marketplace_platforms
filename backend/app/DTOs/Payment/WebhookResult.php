<?php

namespace App\DTOs\Payment;

readonly class WebhookResult
{
    public function __construct(
        public bool    $signatureValid,
        public ?string $eventType = null,
        public ?string $gatewayTransactionId = null,
        public ?string $orderReference = null,
        public ?string $resultingStatus = null,
        public array   $parsedPayload = [],
    ) {}
}
