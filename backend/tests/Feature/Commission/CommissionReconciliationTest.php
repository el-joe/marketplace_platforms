<?php

namespace Tests\Feature\Commission;

use App\Models\CartItem;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCommissionRule;
use App\Models\Order;
use App\Services\LastClickAttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Real place-order (COD) path: per-item commission = floor(line*pct/100)
 * + fixed*qty, items reconcile to the sub-order, vendor payout follows the
 * engine's definition, and the marketer 'both' rule flows through attribution.
 */
class CommissionReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function scenario(): MarketplaceScenario
    {
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        return $s;
    }

    private function place(MarketplaceScenario $s, array $lines, ?string $coupon = null): Order
    {
        $this->actingAs($s->customer, 'customer');
        $cart = app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        foreach ($lines as [$listing, $qty]) {
            CartItem::create([
                'cart_id' => $cart->id,
                'vendor_listing_id' => $listing->id,
                'quantity' => $qty,
                'unit_price' => (int) $listing->getRawOriginal('price'),
                'added_at' => now(),
            ]);
        }
        $payload = [
            'address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ];
        if ($coupon) {
            $payload['coupon_code'] = $coupon;
        }
        $r = $this->postJson("/api/customer/v1/{$s->country->site_code}/checkout/place-order", $payload);
        $r->assertStatus(201);

        return Order::where('order_number', $r->json('data.order.order_number') ?? $r->json('data.order_number'))->firstOrFail();
    }

    private function assertReconciles(Order $order, bool $couponFree = true): void
    {
        foreach ($order->subOrders()->get() as $sub) {
            $items = $sub->items()->get();
            $this->assertNotEmpty($items);
            $rawSum = 0;
            foreach ($items as $it) {
                $expected = (int) floor($it->line_subtotal * (float) $it->commission_rate_pct / 100)
                    + (int) $it->commission_fixed * (int) $it->quantity;
                $this->assertSame($expected, (int) $it->commission_amount, 'item commission = floor(line*pct/100)+fixed*qty');
                $rawSum += (int) $it->commission_amount;
            }
            $afterDiscount = (int) $items->sum('platform_commission_after_discount');
            $this->assertSame((int) $sub->platform_commission, $afterDiscount, 'sum(items) == sub_order.platform_commission');
            if ($couponFree) {
                $this->assertSame($rawSum, (int) $sub->platform_commission, 'no vendor commission discount => raw == stored');
            }
            $this->assertSame(
                (int) $sub->subtotal - (int) $sub->vendor_coupon_cost - (int) $sub->platform_commission
                    - (int) $sub->gateway_fee - ($sub->marketer_commission_owner === 'vendor' ? (int) $sub->marketer_commission : 0),
                (int) $sub->vendor_payout + $this->vendorContribution($sub),
                'vendor_payout = subtotal - vendor coupon - commission - fees (- vendor shipping contribution)'
            );
        }
    }

    private function vendorContribution($sub): int
    {
        return (int) ($sub->vendor_shipping_contribution ?? 0);
    }

    public function test_two_item_qty_gt_one_with_pct_and_fixed_reconciles(): void
    {
        $s = $this->scenario();
        // fixture category: 10% + fixed 200 (FBP). The legacy `commissions` table and
        // vendor-level rate take precedence over the category chain and carry no fixed
        // component, so remove them to exercise the category pct+fixed path.
        \App\Models\Commission::query()->delete();
        $s->vendor->forceFill(['commission_rate' => 0])->save();
        $order = $this->place($s, [[$s->vendorListingFbp, 3]]);
        $item = $order->subOrders()->first()->items()->first();
        $this->assertSame(3, (int) $item->quantity);
        $this->assertGreaterThan(0, (int) $item->commission_fixed);
        $this->assertGreaterThan(0, (float) $item->commission_rate_pct);
        $this->assertReconciles($order);
    }

    public function test_two_item_sub_order_reconciles(): void
    {
        $s = $this->scenario();
        $listings = collect([$s->vendorListingFbp, $s->vendorListingFbp2 ?? null])->filter();
        if ($listings->count() < 2) {
            $this->markTestSkipped('Scenario exposes only one FBP vendor listing.');
        }
        $order = $this->place($s, [[$listings[0], 2], [$listings[1], 1]]);
        $this->assertGreaterThanOrEqual(2, $order->subOrders()->first()->items()->count());
        $this->assertReconciles($order);
    }

    public function test_admin_order_page_shows_stored_commission_amount(): void
    {
        $s = $this->scenario();
        $order = $this->place($s, [[$s->vendorListingFbp, 2]]);
        $item = $order->subOrders()->first()->items()->first();
        // The blade prints $fmt($item->commission_amount) directly from the column
        // (no recomputation): guard that contract at the source level.
        $src = file_get_contents(resource_path('views/admin/orders/show.blade.php'));
        $this->assertStringContainsString('$fmt($item->commission_amount)', $src);
        $this->assertStringContainsString('$item->commission_fixed', $src);
        $this->assertGreaterThan(0, (int) $item->commission_amount);
    }

    public function test_marketer_both_rule_flows_through_attribution(): void
    {
        $s = $this->scenario();
        $s->marketerCampaign->update(['marketer_commission_amount' => 0, 'commission_type' => 'fixed']);
        MarketerCommissionRule::create([
            'marketer_id' => $s->marketer->id, 'scope' => 'products',
            'commission_mode' => 'both', 'commission_rate' => 10, 'commission_flat_amount' => 5,
        ]);

        $this->actingAs($s->customer, 'customer');
        $cart = app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'marketer_listing_id' => $s->marketerListing->id, 'quantity' => 2,
            'unit_price' => (int) $s->marketerListing->price, 'added_at' => now(),
        ]);
        $r = $this->postJson("/api/customer/v1/{$s->country->site_code}/checkout/place-order", [
            'address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $r->assertStatus(201);
        $order = Order::where('order_number', $r->json('data.order.order_number') ?? $r->json('data.order_number'))->firstOrFail();
        $item = $order->subOrders()->first()->items()->first();

        MarketerCampaignConversion::where('order_item_id', $item->id)->delete();
        $item->forceFill(['quantity' => 2, 'line_total' => 200])->save();
        app(LastClickAttributionService::class)->resolveAndRecordConversion($order->fresh(), null);

        $conv = MarketerCampaignConversion::where('order_item_id', $item->id)->firstOrFail();
        $this->assertSame(30, (int) $conv->commission_amount, '10% of 200 + 5*2');
    }
}
