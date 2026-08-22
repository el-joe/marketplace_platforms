<?php

namespace App\DTOs\Payment;

readonly class PaymentVerificationResult
{
    public function __construct(
        public bool    $success,
        public string  $status,  // 'succeeded'|'pending'|'failed'|'cancelled'
        public int     $amountCents,
        public string  $currency,
        public ?string $gatewayTransactionId = null,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
        public array   $rawResponse = [],
    ) {}
}
