<?php

namespace App\DTOs\Payment;

readonly class PaymentInitiationResult
{
    public function __construct(
        public bool    $success,
        public ?string $redirectUrl = null,
        public ?string $gatewayTransactionId = null,
        public ?string $sessionId = null,
        public ?string $errorMessage = null,
        public array   $rawResponse = [],
    ) {}
}
