<?php

namespace App\Services\Checkout;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\WarehouseInventory;
use App\Models\WarrantyPurchase;
use App\Services\Customer\CheckoutWalletService;
use App\Services\Customer\LoyaltyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * enhancement.md P-05 task 4: single place that undoes everything
 * place-order may have already committed for an order once we know the
 * payment did not (or will not) succeed — reserved stock, coupon usage,
 * loyalty points, wallet debit and warranty purchases. Reused by P-06's
 * cancellation engine.
 *
 * Idempotent: safe to call more than once for the same order (e.g. once
 * from the callback path and again from the expiry job) — every step only
 * acts on rows that are still in a "not yet released" state.
 */
class CheckoutRollbackService
{
    public function __construct(
        private readonly CouponUsageService $couponUsageService = new CouponUsageService(),
        private readonly CheckoutWalletService $checkoutWalletService = new CheckoutWalletService(),
        private readonly LoyaltyService $loyaltyService = new LoyaltyService(),
    ) {}

    public function rollback(Order $order, string $reason = 'Checkout rollback'): void
    {
        $this->releaseReservedInventory($order, $reason);
        $this->couponUsageService->releaseForOrder($order);
        $this->releaseLoyaltyPoints($order);
        $this->refundWallet($order);
        $this->cancelWarrantyPurchases($order);
    }

    public function releaseReservedInventory(Order $order, string $reason = 'Checkout rollback'): void
    {
        $order->loadMissing('subOrders.items.allocations');

        DB::transaction(function () use ($order, $reason) {
            $inventoryService = app(\App\Services\Inventory\InventoryService::class);

            foreach ($order->subOrders as $subOrder) {
                foreach ($subOrder->items as $item) {
                    // enhancement.md P-13: release the EXACT row(s) this
                    // item was reserved from (order_item_allocations),
                    // rather than re-deriving a row from listing +
                    // sub_order.warehouse_id — the previous approach broke
                    // for a listing split across more than one warehouse.
                    $openAllocations = $item->allocations->where('status', 'reserved');

                    if ($openAllocations->isEmpty()) {
                        continue;
                    }

                    $inventoryService->release($openAllocations, 'order', $order->id, actorType: 'customer', actorId: $order->customer_id, reason: $reason);
                }
            }
        });
    }

    private function releaseLoyaltyPoints(Order $order): void
    {
        if ((float) $order->loyalty_points_used <= 0) {
            return;
        }

        try {
            $this->loyaltyService->creditPointsBackForOrder($order);
        } catch (\Throwable $e) {
            Log::error('Failed to release loyalty points during rollback', [
                'order_id' => $order->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function refundWallet(Order $order): void
    {
        if ((int) $order->wallet_amount_used <= 0) {
            return;
        }

        try {
            $this->checkoutWalletService->refundToWallet($order->customer, $order, (int) $order->wallet_amount_used);
            $order->update(['wallet_amount_used' => 0]);
        } catch (\Throwable $e) {
            Log::error('Failed to refund wallet during rollback', [
                'order_id' => $order->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function cancelWarrantyPurchases(Order $order): void
    {
        WarrantyPurchase::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'active'])
            ->update(['status' => 'cancelled']);
    }
}
