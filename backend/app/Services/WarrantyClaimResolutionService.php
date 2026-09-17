<?php

namespace App\Services;

use App\DTOs\Refund\RefundScope;
use App\Enums\RefundReason;
use App\Models\OrderItem;
use App\Models\SubOrder;
use App\Models\WarrantyClaim;
use Illuminate\Support\Str;

/**
 * enhancement.md P-09 task 5: turn a warranty claim's resolution
 * (`repair` / `replace` / `refund` / `no_action`) into the actual side
 * effect instead of only flipping a status column.
 *
 *  - refund: goes through RefundService (P-07) — the one place refund
 *    money math and ledger reversal live — rather than crediting the
 *    wallet directly.
 *  - replace: creates a zero-price replacement sub-order + order item for
 *    the same product/variant/quantity, so the customer gets a new unit
 *    without being charged again. Kept intentionally simple: no carrier
 *    assignment or shipment is created here, only the order-side record a
 *    fulfilment operator would then act on.
 *  - repair: no money or stock movement; the caller is expected to also
 *    log a status/message timeline entry (already done by both admin and
 *    partner controllers).
 */
class WarrantyClaimResolutionService
{
    public function __construct(private readonly RefundService $refundService)
    {
    }

    public function refund(WarrantyClaim $claim, ?string $approvedByAdminId = null): void
    {
        $orderItem = $claim->orderItem()->with('order', 'subOrder')->first();

        if (! $orderItem || ! $orderItem->subOrder) {
            return;
        }

        $this->refundService->refund(
            order: $orderItem->order,
            scope: RefundScope::items($orderItem->sub_order_id, [$orderItem->id => $orderItem->quantity]),
            reason: RefundReason::Other->value,
            liability: 'platform',
            destination: 'wallet',
            approvedByAdminId: $approvedByAdminId,
            reasonNotes: "Warranty claim #{$claim->claim_number}",
        );
    }

    public function replace(WarrantyClaim $claim): SubOrder
    {
        /** @var OrderItem $orderItem */
        $orderItem = $claim->orderItem()->with('order', 'subOrder')->firstOrFail();
        $originalSubOrder = $orderItem->subOrder;
        $order = $orderItem->order;

        $replacementSubOrder = SubOrder::create([
            'order_id' => $order->id,
            'sub_order_number' => 'WR-'.strtoupper(Str::random(10)),
            'vendor_id' => $orderItem->vendor_id,
            'seller_type' => $originalSubOrder->seller_type ?? ($orderItem->vendor_id ? 'vendor' : 'platform'),
            'warehouse_id' => $originalSubOrder->warehouse_id,
            'status' => 'placed',
            'fulfillment_model' => $originalSubOrder->fulfillment_model,
            'subtotal' => 0,
            'shipping' => 0,
            'tax' => 0,
            'platform_commission' => 0,
            'vendor_coupon_cost' => 0,
            'platform_coupon_cost' => 0,
            'marketer_commission' => 0,
            'warranty_revenue' => 0,
            'gateway_fee' => 0,
            'vendor_payout' => 0,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'sub_order_id' => $replacementSubOrder->id,
            'product_variant_id' => $orderItem->product_variant_id,
            'vendor_listing_id' => $orderItem->vendor_listing_id,
            'admin_listing_id' => $orderItem->admin_listing_id,
            'marketer_listing_id' => $orderItem->marketer_listing_id,
            'product_snapshot' => $orderItem->product_snapshot,
            'vendor_id' => $orderItem->vendor_id,
            'sku' => $orderItem->sku,
            'quantity' => $orderItem->quantity,
            'unit_price' => 0,
            'unit_cost_price' => $orderItem->unit_cost_price,
            'line_subtotal' => 0,
            'line_discount' => 0,
            'line_tax' => 0,
            'line_total' => 0,
            'commission_rate_pct' => 0,
            'commission_amount' => 0,
            'fulfillment_status' => 'pending',
        ]);

        return $replacementSubOrder;
    }
}
