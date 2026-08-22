<?php

namespace App\Contracts\Payments;

use App\DTOs\Payment\ConnectionTestResult;
use App\DTOs\Payment\PaymentInitiationData;
use App\DTOs\Payment\PaymentInitiationResult;
use App\DTOs\Payment\PaymentVerificationResult;
use App\DTOs\Payment\RefundResult;
use App\DTOs\Payment\WebhookResult;

interface PaymentGatewayInterface
{
    public function initiate(PaymentInitiationData $data): PaymentInitiationResult;

    public function verify(string $transactionReference): PaymentVerificationResult;

    public function refund(string $transactionReference, int $amountCents, string $reason): RefundResult;

    public function handleWebhook(array $payload, array $headers): WebhookResult;

    public function testConnection(): ConnectionTestResult;

    /** Unique strategy key — e.g. 'thawani', 'paytabs' */
    public function getCode(): string;
}
