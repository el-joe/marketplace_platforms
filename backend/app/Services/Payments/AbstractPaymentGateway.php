<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\CountryPaymentGateway;
use App\Models\Currency;
use App\Support\Money;
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

    /** Decimal places of the currency's minor unit (2 for EGP/SAR/USD, 3 for OMR/KWD); defaults to 2. */
    protected function decimalPlaces(string $currency): int
    {
        static $cache = [];

        $code = strtoupper($currency);
        if (! array_key_exists($code, $cache)) {
            $cache[$code] = (int) (Currency::where('code', $code)->value('decimal_places') ?? 2);
        }

        return $cache[$code];
    }

    /** Platform amount (decimal, base currency units) -> the integer minor units Stripe/Thawani expect. Gateway boundary only. */
    protected function toMinorUnits(int|float $amount, string $currency): int
    {
        return (int) round($amount * (10 ** $this->decimalPlaces($currency)));
    }

    /** Integer minor units reported by a gateway -> platform decimal amount (int when whole). */
    protected function fromMinorUnits(int|float $minorUnits, string $currency): int|float
    {
        return Money::number(round($minorUnits / (10 ** $this->decimalPlaces($currency)), 4));
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
