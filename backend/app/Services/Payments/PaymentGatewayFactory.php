<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Models\CountryPaymentGateway;
use App\Models\PaymentGateway;

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

    /**
     * Test-only seam: enhancement.md P-00's Tests\Support\FakePaymentGateway
     * is scripted to return success/decline/exception outcomes without
     * hitting a real gateway. Feature tests call
     * PaymentGatewayFactory::fake($fake) to make every make() call return it
     * regardless of gateway code — never set outside tests.
     */
    private static ?PaymentGatewayInterface $fake = null;

    public static function fake(?PaymentGatewayInterface $gateway): void
    {
        self::$fake = $gateway;
    }

    public static function make(CountryPaymentGateway $config): PaymentGatewayInterface
    {
        if (self::$fake !== null) {
            return self::$fake;
        }

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

    /**
     * True when the gateway's `payment_gateways.type` is 'offline' — i.e. it
     * is not COD and has no automated redirect/webhook confirmation, so it
     * requires customer proof upload + admin approval (see
     * OFFLINE_PAYMENT_APPROVAL_PLAN.md).
     */
    public static function isOffline(string $gatewayCode): bool
    {
        return PaymentGateway::where('code', $gatewayCode)->value('type') === 'offline';
    }
}
