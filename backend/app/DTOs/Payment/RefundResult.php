<?php

namespace App\DTOs\Payment;

readonly class RefundResult
{
    public function __construct(
        public bool    $success,
        public ?string $refundTransactionId = null,
        public int|float $refundedAmountCents = 0,
        public ?string $errorMessage = null,
        public array   $rawResponse = [],
    ) {}
}
