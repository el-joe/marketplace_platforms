<?php

namespace App\Services\Payments;

/**
 * enhancement.md P-05 task 2: orders.payment_method is a narrow
 * enum('card','wallet','cod','bnpl','bank_transfer'), but place-order used
 * to write the raw payment_gateways.code (e.g. 'thawani', 'paytabs'),
 * which fails the insert under strict-mode MySQL for every card gateway.
 * The actual gateway used is now stored separately in
 * orders.payment_gateway_code — this maps a gateway code/type to the
 * generic enum bucket the `orders` row itself is allowed to hold.
 */
class PaymentMethodMapper
{
    private const CODE_MAP = [
        'wallet' => 'wallet',
        'cod' => 'cod',
        'bank_transfer' => 'bank_transfer',
        'bnpl' => 'bnpl',
    ];

    public static function toOrderPaymentMethod(?string $gatewayCode, ?string $gatewayType = null): string
    {
        if ($gatewayCode !== null && isset(self::CODE_MAP[$gatewayCode])) {
            return self::CODE_MAP[$gatewayCode];
        }

        // Any redirect/direct card processor (thawani, paytabs, stripe, ...)
        // that isn't one of the explicit non-card buckets above is a card
        // charge as far as the order record is concerned.
        if (in_array($gatewayType, ['redirect', 'direct'], true)) {
            return 'card';
        }

        return 'card';
    }
}
