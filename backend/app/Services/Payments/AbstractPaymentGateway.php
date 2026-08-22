<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\CountryPaymentGateway;
use App\Models\PaymentGatewayWebhookLog;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected CountryPaymentGateway $config,
    ) {}

    protected function credentials(): array
    {
        return $this->config->getCredentials();
    }

    protected function isProduction(): bool
    {
        return $this->config->isProduction();
    }

    protected function settlementCurrency(): string
    {
        return $this->config->effective_currency;
    }

    protected function logWebhook(
        array   $payload,
        array   $headers,
        bool    $signatureValid,
        ?string $eventType = null,
        ?string $transactionId = null,
    ): PaymentGatewayWebhookLog {
        return PaymentGatewayWebhookLog::create([
            'country_payment_gateway_id' => $this->config->id,
            'gateway_code'               => $this->getCode(),
            'event_type'                 => $eventType,
            'payload'                    => $payload,
            'headers'                    => $headers,
            'signature_valid'            => $signatureValid,
            'payment_transaction_id'     => $transactionId,
        ]);
    }
}
