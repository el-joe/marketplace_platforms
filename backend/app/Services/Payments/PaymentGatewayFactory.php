<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\CountryPaymentGateway;

class PaymentGatewayFactory
{
    /** Maps gateway code → implementation class */
    private static array $map = [
        'thawani'       => ThawaniGateway::class,
        'paytabs'       => PaytabsGateway::class,
        'bank_transfer' => BankTransferGateway::class,
        // 'stripe'     => StripeGateway::class,
        // 'cod'        => CodGateway::class,    // no-op, no API
        // 'wallet'     => WalletGateway::class, // handled internally
    ];

    public static function make(CountryPaymentGateway $config): PaymentGatewayInterface
    {
        $code = $config->gateway?->code;

        if (!$code || !isset(self::$map[$code])) {
            throw new \InvalidArgumentException(
                "No gateway implementation registered for code: {$code}"
            );
        }

        return new (self::$map[$code])($config);
    }

    public static function supports(string $code): bool
    {
        return isset(self::$map[$code]);
    }

    /** Codes that require a browser redirect to an external checkout page */
    public static function redirectCodes(): array
    {
        return ['thawani', 'paytabs'];
    }

    /** Codes that need no API and are handled internally */
    public static function internalCodes(): array
    {
        return ['cod', 'wallet', 'bank_transfer'];
    }
}
