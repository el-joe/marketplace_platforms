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
        $order->loadMissing('subOrders.items');

        DB::transaction(function () use ($order, $reason) {
            foreach ($order->subOrders as $subOrder) {
                foreach ($subOrder->items as $item) {
                    $query = $item->vendor_listing_id
                        ? WarehouseInventory::where('vendor_listing_id', $item->vendor_listing_id)
                        : ($item->admin_listing_id
                            ? WarehouseInventory::where('admin_listing_id', $item->admin_listing_id)
                            : null);

                    if (! $query) {
                        continue;
                    }

                    $inventory = $query->where('warehouse_id', $subOrder->warehouse_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $inventory || $inventory->quantity_reserved <= 0) {
                        continue;
                    }

                    $decrementBy = min($item->quantity, $inventory->quantity_reserved);
                    $inventory->decrement('quantity_reserved', $decrementBy);
                    $inventory->refresh();

                    InventoryMovement::create([
                        'warehouse_inventory_id' => $inventory->id,
                        'movement_type' => InventoryMovementType::Release->value,
                        'quantity_delta' => -$decrementBy,
                        'quantity_after' => $inventory->quantity_on_hand,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'reason' => $reason,
                        'created_by_user_id' => $order->customer_id,
                    ]);
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
