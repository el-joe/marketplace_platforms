<?php

namespace Tests\Support;

use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\WarehouseInventory;
use PHPUnit\Framework\Assert;

/**
 * Reusable assertion helpers for order-lifecycle tests (stock, order money
 * totals, and double-entry ledger balance). Use inside a Pest/PHPUnit test
 * via `use Tests\Support\AssertsOrderMoney;`.
 */
trait AssertsOrderMoney
{
    /**
     * Assert a listing's warehouse inventory has the expected on-hand and
     * reserved quantities. $listing may be a VendorListing or AdminListing
     * (or any model with vendor_listing_id/admin_listing_id inventory rows),
     * or you may pass a WarehouseInventory directly.
     */
    protected function assertStock($listing, int $onHand, int $reserved): void
    {
        $inventory = $listing instanceof WarehouseInventory
            ? $listing
            : WarehouseInventory::query()
                ->where('vendor_listing_id', $listing->id)
                ->orWhere('admin_listing_id', $listing->id)
                ->first();

        Assert::assertNotNull($inventory, 'No warehouse_inventories row found for listing.');

        Assert::assertSame(
            $onHand,
            (int) $inventory->quantity_on_hand,
            "Expected on_hand={$onHand}, got {$inventory->quantity_on_hand}"
        );

        Assert::assertSame(
            $reserved,
            (int) $inventory->quantity_reserved,
            "Expected reserved={$reserved}, got {$inventory->quantity_reserved}"
        );
    }

    /**
     * Assert an order's money is internally consistent:
     *  - total == subtotal - discount - loyalty + shipping + cod_fee + tax + warranty_total
     *  - sum(sub_orders.subtotal) == order.subtotal
     *  - sum(order_items.line_discount) == order.discount (coupon part)
     *  - sum(sub_orders.tax) == order.tax
     */
    protected function assertMoneyBalanced(Order $order): void
    {
        $order->refresh();
        $order->loadMissing(['subOrders', 'items']);

        $expectedTotal = $order->subtotal
            - $order->discount
            - $order->loyalty_discount
            + $order->shipping
            + $order->cod_fee
            + $order->tax
            + $order->warranty_total;

        Assert::assertSame(
            (int) $expectedTotal,
            (int) $order->total,
            "order.total ({$order->total}) does not equal subtotal-discount-loyalty+shipping+cod_fee+tax+warranty_total ({$expectedTotal})"
        );

        $subOrdersSubtotal = (int) $order->subOrders->sum('subtotal');
        Assert::assertSame(
            (int) $order->subtotal,
            $subOrdersSubtotal,
            "sum(sub_orders.subtotal) ({$subOrdersSubtotal}) does not equal order.subtotal ({$order->subtotal})"
        );

        $itemsDiscount = (int) $order->items->sum('line_discount');
        Assert::assertSame(
            (int) $order->discount,
            $itemsDiscount,
            "sum(order_items.line_discount) ({$itemsDiscount}) does not equal order.discount ({$order->discount})"
        );

        $subOrdersTax = (int) $order->subOrders->sum('tax');
        Assert::assertSame(
            (int) $order->tax,
            $subOrdersTax,
            "sum(sub_orders.tax) ({$subOrdersTax}) does not equal order.tax ({$order->tax})"
        );
    }

    /**
     * Assert a transaction group's ledger entries balance: sum(debit) == sum(credit).
     */
    protected function assertLedgerBalanced(string $transactionGroupId): void
    {
        $entries = LedgerEntry::query()
            ->where('transaction_group_id', $transactionGroupId)
            ->get();

        Assert::assertNotEmpty($entries, "No ledger_entries found for transaction_group_id={$transactionGroupId}");

        $debit = (int) $entries->sum('debit');
        $credit = (int) $entries->sum('credit');

        Assert::assertSame(
            $debit,
            $credit,
            "Ledger unbalanced for group {$transactionGroupId}: debit={$debit}, credit={$credit}"
        );
    }
}
