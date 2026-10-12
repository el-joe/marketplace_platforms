<?php

namespace App\DTOs;

readonly class PaymentResult
{
    public function __construct(
        public bool $success,
        public string $status,       // pending/succeeded/failed/cancelled
        public string $gatewayTransactionId,
        public int|float $amountCents,
        public string $currency,
        public int|float $gatewayFeeCents,
        public ?string $failureCode,
        public ?string $failureMessage,
        public array $rawResponse,
    ) {
    }

    public static function success(
        string $txId,
        int|float $amount,
        string $currency,
        int|float $fee = 0,
        array $raw = []
    ): self {
        return new self(
            success: true,
            status: 'succeeded',
            gatewayTransactionId: $txId,
            amountCents: $amount,
            currency: $currency,
            gatewayFeeCents: $fee,
            failureCode: null,
            failureMessage: null,
            rawResponse: $raw,
        );
    }

    public static function failure(
        string $code,
        string $message,
        array $raw = []
    ): self {
        return new self(
            success: false,
            status: 'failed',
            gatewayTransactionId: '',
            amountCents: 0,
            currency: '',
            gatewayFeeCents: 0,
            failureCode: $code,
            failureMessage: $message,
            rawResponse: $raw,
        );
    }
}
