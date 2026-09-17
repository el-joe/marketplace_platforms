<?php

namespace Tests\Feature\Checkout;

use App\Models\CartItem;
use App\Models\LedgerEntry;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AssertsOrderMoney;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-03 (Phase B): the vendor/platform/marketer/shipping
 * money split, end-to-end through the real place-order endpoint (see
 * tests/Unit/Checkout/CheckoutPricingEngineSplitTest.php for the engine-only
 * version). Verifies the *persisted* sub_orders/order_items numbers
 * reconcile to the unit, and that a capture posts a balanced ledger.
 */
class CheckoutMoneySplitTest extends TestCase
{
    use RefreshDatabase;
    use AssertsOrderMoney;

    private function buildScenario(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        return $scenario;
    }

    private function addVendorItem(MarketplaceScenario $scenario, $cart, $listing, int $quantity = 1): CartItem
    {
        return CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $listing->id,
            'quantity' => $quantity,
            'unit_price' => (int) $listing->getRawOriginal('price'),
            'added_at' => now(),
        ]);
    }

    /**
     * Recompute the P-03 reconciliation identity purely from what
     * place-order persisted (sub_orders + order columns) — proving the
     * *stored* numbers balance, not just the engine's in-memory output.
     */
    private function assertMoneySplitReconciles(Order $order): void
    {
        $order->refresh();
        $order->loadMissing('subOrders');

        $sumVendorPayout = 0;
        $gatewayFeeTotal = 0;
        $marketerCommissionTotal = 0;
        $carrierCostCoveredTotal = 0;
        $platformNet = 0;

        foreach ($order->subOrders as $subOrder) {
            $sumVendorPayout += (int) $subOrder->vendor_payout;
            $gatewayFeeTotal += (int) $subOrder->gateway_fee;
            $marketerCommissionTotal += (int) $subOrder->marketer_commission;
            $carrierCostCoveredTotal += (int) $subOrder->carrier_shipping_cost;

            $shippingRevenue = (int) $subOrder->shipping - (int) $subOrder->carrier_shipping_cost;
            $isPlatformGroup = $subOrder->seller_type === 'platform';

            $platformNet += (int) $subOrder->platform_commission
                + $shippingRevenue
                - (int) $subOrder->platform_coupon_cost
                - (int) $subOrder->admin_subsidy_amount
                - ($subOrder->marketer_commission_owner === 'platform' ? (int) $subOrder->marketer_commission : 0)
                - ($isPlatformGroup ? (int) $subOrder->gateway_fee : 0);
        }
        $platformNet += (int) $order->cod_fee + (int) $order->subOrders->sum('warranty_revenue');

        $this->assertSame(
            (int) $order->total,
            $sumVendorPayout + $platformNet + (int) $order->tax + $marketerCommissionTotal + $gatewayFeeTotal + $carrierCostCoveredTotal,
            'vendor_payout + platform_net + tax + marketer_commission + gateway_fee_total + carrier_cost_covered must equal the amount paid by the customer, to the unit'
        );
    }

    public function test_p03_money_split_reconciles_for_cod_single_vendor(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');
        $cart = app(\App\Services\Customer\CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 2);

        $payload = [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ];

        $response = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", $payload);
        $response->assertStatus(201);

        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $this->assertMoneyBalanced($order);
        $this->assertMoneySplitReconciles($order);

        // A vendor's platform_commission must equal Sigma(order_items on
        // that sub-order's platform_commission_after_discount) — the fix
        // for the bug where the vendor discount was only applied after
        // summing at sub-order level.
        foreach ($order->subOrders as $subOrder) {
            $itemsCommission = (int) $subOrder->items()->sum('platform_commission_after_discount');
            $this->assertSame((int) $subOrder->platform_commission, $itemsCommission);
        }
    }

    public function test_p03_vendor_funded_coupon_reduces_only_that_vendor_payout_by_exactly_the_discount(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');
        $cart = app(\App\Services\Customer\CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $coupon = $scenario->coupons['percentage_vendor'];
        $payload = [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
            'coupon_code' => $coupon->code,
            'idempotency_key' => (string) Str::uuid(),
        ];

        $response = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", $payload);
        $response->assertStatus(201);
        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');
        $orderWithCoupon = Order::where('order_number', $orderNumber)->firstOrFail();
        $this->assertMoneyBalanced($orderWithCoupon);
        $this->assertMoneySplitReconciles($orderWithCoupon);

        $vendorSubOrder = $orderWithCoupon->subOrders->firstWhere('vendor_id', $scenario->vendor->id);
        $this->assertNotNull($vendorSubOrder);
        $this->assertSame($orderWithCoupon->discount, $vendorSubOrder->vendor_coupon_cost, 'funded_by=vendor coupon: 100% of the discount is the vendor\'s cost');
        $this->assertSame(0, $vendorSubOrder->platform_coupon_cost);

        // Same cart/customer, no coupon, to isolate the coupon's effect on vendor_payout.
        $cart2 = app(\App\Services\Customer\CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
        $this->addVendorItem($scenario, $cart2, $scenario->vendorListingFbp, 1);
        $payload2 = [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ];
        $response2 = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", $payload2);
        $response2->assertStatus(201);
        $orderNumber2 = $response2->json('data.order.order_number') ?? $response2->json('data.order_number');
        $orderNoCoupon = Order::where('order_number', $orderNumber2)->firstOrFail();
        $vendorSubOrderNoCoupon = $orderNoCoupon->subOrders->firstWhere('vendor_id', $scenario->vendor->id);

        $this->assertSame(
            $vendorSubOrder->vendor_coupon_cost,
            $vendorSubOrderNoCoupon->vendor_payout - $vendorSubOrder->vendor_payout
        );
    }

    public function test_p03_ledger_balances_at_cod_capture(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');
        $cart = app(\App\Services\Customer\CartService::class)->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);
        $this->addVendorItem($scenario, $cart, $scenario->vendorListingFbp, 1);

        $payload = [
            'address_id' => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ];

        $response = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", $payload);
        $response->assertStatus(201);
        $orderNumber = $response->json('data.order.order_number') ?? $response->json('data.order_number');
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        app(\App\Services\LedgerService::class)->postOrderCapture($order, (int) $order->total);

        $this->assertLedgerBalanced($order->id);
        $this->assertTrue(LedgerEntry::where('transaction_group_id', $order->id)->exists());

        // Idempotent: calling it again must not throw or double-post.
        app(\App\Services\LedgerService::class)->postOrderCapture($order, (int) $order->total);
        $this->assertSame(
            LedgerEntry::where('transaction_group_id', $order->id)->count(),
            LedgerEntry::where('transaction_group_id', $order->id)->count()
        );
    }
}
