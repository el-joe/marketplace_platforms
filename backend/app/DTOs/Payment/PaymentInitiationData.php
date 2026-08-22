<?php

namespace App\DTOs\Payment;

readonly class PaymentInitiationData
{
    public function __construct(
        public string  $orderId,
        public string  $orderNumber,
        public int     $amountCents,
        public string  $currency,
        public string  $customerId,
        public string  $customerEmail,
        public ?string $customerPhone,
        public string  $successUrl,
        public string  $cancelUrl,
        public ?string $webhookUrl = null,
        public array   $metadata = [],
    ) {}
}
