<?php

namespace Tests\Feature\Checkout;

use App\Models\CartItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** F13 — cart coupon carry-over, wallet pre-checks, idempotency, prepare/order parity. */
class CheckoutFixesF13Test extends TestCase
{
    use RefreshDatabase;

    private function scenario(int $price = 10000): array
    {
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->actingAs($s->customer, 'customer');
        $cart = app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        CartItem::create(['cart_id' => $cart->id, 'vendor_listing_id' => $s->vendorListingFbp->id,
            'quantity' => 1, 'unit_price' => $price, 'added_at' => now()]);

        return [$s, $cart];
    }

    private function url(MarketplaceScenario $s, string $p): string
    {
        return "/api/customer/v1/{$s->country->site_code}/checkout/{$p}";
    }

    private function body(MarketplaceScenario $s, string $gw, array $x = []): array
    {
        return $x + ['address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways[$gw]->id,
            'idempotency_key' => (string) Str::uuid()];
    }

    public function test_prepare_uses_cart_coupon_when_code_omitted_and_null_when_absent(): void
    {
        [$s, $cart] = $this->scenario();
        $r = $this->postJson($this->url($s, 'prepare'), $this->body($s, 'cod'));
        $this->assertNull($r->json('data.coupon'), $r->getContent());

        $c = $s->coupons['percentage_platform'];
        $cart->update(['coupon_id' => $c->id]);
        $r = $this->postJson($this->url($s, 'prepare'), $this->body($s, 'cod'));
        $this->assertSame($c->code, $r->json('data.coupon.code'), $r->getContent());
        $this->assertGreaterThan(0, (int) $r->json('data.coupon.discount'));
    }

    public function test_expired_cart_coupon_does_not_discount(): void
    {
        [$s, $cart] = $this->scenario();
        $c = $s->coupons['percentage_platform'];
        $c->update(['valid_until' => now()->subDay()]);
        $cart->update(['coupon_id' => $c->id]);
        $r = $this->postJson($this->url($s, 'prepare'), $this->body($s, 'cod'));
        $this->assertTrue($r->status() === 422 || empty($r->json('data.coupon')), $r->getContent());
    }

    public function test_wallet_insufficient_errors_before_order_creation(): void
    {
        [$s] = $this->scenario(100000);
        $s->customerWallet->update(['balance' => 100]);
        $r = $this->postJson($this->url($s, 'place-order'), $this->body($s, 'wallet'));
        $r->assertStatus(422);
        $this->assertSame(0, Order::count(), 'no orphan order');
        $this->assertSame(100, (int) $s->customerWallet->fresh()->balance);
    }

    public function test_wallet_sufficient_deducts_and_double_submit_is_idempotent(): void
    {
        [$s] = $this->scenario(10000);
        $s->customerWallet->update(['balance' => 10000000]);
        $b = $this->body($s, 'wallet');
        $r1 = $this->postJson($this->url($s, 'place-order'), $b);
        $r1->assertSuccessful();
        $after = (int) $s->customerWallet->fresh()->balance;
        $this->assertLessThan(10000000, $after);
        $this->postJson($this->url($s, 'place-order'), $b);
        $this->assertSame(1, Order::count());
        $this->assertSame($after, (int) $s->customerWallet->fresh()->balance);
    }

    public function test_partial_wallet_with_cod_is_rejected_or_deducts_exactly(): void
    {
        [$s] = $this->scenario(10000);
        $r = $this->postJson($this->url($s, 'place-order'), $this->body($s, 'cod', ['wallet_amount_used' => 1000]));
        if ($r->status() < 300) {
            $this->assertSame(50000 - 1000, (int) $s->customerWallet->fresh()->balance);
        } else {
            $this->assertSame(0, Order::count());
            $this->assertSame(50000, (int) $s->customerWallet->fresh()->balance);
        }
    }

    public function test_prepare_total_matches_final_order_total(): void
    {
        [$s, $cart] = $this->scenario(12345);
        $cart->update(['coupon_id' => $s->coupons['percentage_platform']->id]);
        $s->customerWallet->update(['balance' => 10000000]);
        $p = $this->postJson($this->url($s, 'prepare'), $this->body($s, 'wallet'));
        $p->assertSuccessful();
        $r = $this->postJson($this->url($s, 'place-order'), $this->body($s, 'wallet'));
        $r->assertSuccessful();
        $this->assertSame((int) $p->json('data.order_summary.total'), (int) Order::latest('created_at')->first()->total, $r->getContent());
    }
}
