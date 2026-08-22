<?php

namespace App\DTOs;

readonly class RefundResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public string $gatewayTransactionId,
        public int $amountCents,
        public string $currency,
        public int $gatewayFeeCents,
        public ?string $failureCode,
        public ?string $failureMessage,
        public array $rawResponse,
        public string $refundTransactionId = '',
        public string $originalTransactionId = '',
    ) {
    }

    public static function success(
        string $refundTxId,
        string $originalTxId,
        int $amount,
        string $currency,
        array $raw = []
    ): self {
        return new self(
            success: true,
            status: 'succeeded',
            gatewayTransactionId: $refundTxId,
            amountCents: $amount,
            currency: $currency,
            gatewayFeeCents: 0,
            failureCode: null,
            failureMessage: null,
            rawResponse: $raw,
            refundTransactionId: $refundTxId,
            originalTransactionId: $originalTxId,
        );
    }

    public static function failure(
        string $code,
        string $message,
        string $currency = '',
        array $raw = []
    ): self {
        return new self(
            success: false,
            status: 'failed',
            gatewayTransactionId: '',
            amountCents: 0,
            currency: $currency,
            gatewayFeeCents: 0,
            failureCode: $code,
            failureMessage: $message,
            rawResponse: $raw,
        );
    }
}
