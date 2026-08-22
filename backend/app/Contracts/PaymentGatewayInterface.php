<?php

namespace App\Contracts;

use App\DTOs\PaymentResult;
use App\DTOs\RefundResult;
use App\Models\Order;
use App\Models\PaymentTransaction;

interface PaymentGatewayInterface
{
    /** Unique code matching payment_gateways.code (e.g. 'thawani', 'paytabs', 'cod') */
    public function getCode(): string;

    /** Human-readable name */
    public function getName(): string;

    /** Supported method_types this gateway handles */
    public function getSupportedMethodTypes(): array;

    /** Charge a payment — creates PaymentTransaction */
    public function charge(
        Order $order,
        int $amountCents,
        string $currency,
        string $paymentToken,
        string $idempotencyKey
    ): PaymentResult;

    /** Refund a previous transaction */
    public function refund(
        PaymentTransaction $transaction,
        int $amountCents,
        string $reason
    ): RefundResult;

    /** Void/cancel an authorization */
    public function void(PaymentTransaction $transaction): PaymentResult;

    /** Verify webhook signature */
    public function verifyWebhook(string $payload, string $signature): bool;

    /** Parse webhook and return PaymentResult */
    public function parseWebhook(array $payload): PaymentResult;

    /**
     * Test connection with current credentials.
     * Returns: { success: bool, latency_ms: int, message: string }
     */
    public function testConnection(): array;
}
