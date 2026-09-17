<?php

namespace App\DTOs\Refund;

/**
 * enhancement.md P-07 task 1: what a refund actually covers. RefundService
 * computes the refund amount from persisted P-03 line values according to
 * the scope's kind — it never recomputes pricing from scratch.
 *
 *  - items:    a sub-order plus a map of order_item_id => quantity being
 *              refunded. This is the normal "return N of M units" case.
 *              Shipping is added on top only if every item of the
 *              sub-order ends up refunded by this scope (or liability
 *              forces it — RefundService decides that, not this DTO).
 *  - shipping: refund only the sub-order's shipping fee (no items).
 *  - amount:   a raw, already-computed amount in base-currency cents
 *              (e.g. OrderCancellationService's pro-rated card portion).
 *              No line-item math is performed for this kind — the caller
 *              is asserting they already computed it correctly from
 *              persisted values.
 */
final class RefundScope
{
    public const KIND_ITEMS = 'items';

    public const KIND_SHIPPING = 'shipping';

    public const KIND_AMOUNT = 'amount';

    private function __construct(
        public readonly string $kind,
        public readonly ?string $subOrderId,
        /** @var array<string, int> order_item_id => quantity being refunded */
        public readonly array $itemQuantities,
        public readonly ?int $rawAmountCents,
    ) {}

    /**
     * @param  array<string, int>  $itemQuantities  order_item_id => quantity
     */
    public static function items(string $subOrderId, array $itemQuantities): self
    {
        return new self(self::KIND_ITEMS, $subOrderId, $itemQuantities, null);
    }

    public static function shipping(string $subOrderId): self
    {
        return new self(self::KIND_SHIPPING, $subOrderId, [], null);
    }

    public static function amount(int $amountCents, ?string $subOrderId = null): self
    {
        return new self(self::KIND_AMOUNT, $subOrderId, [], $amountCents);
    }
}
