<?php

namespace Tests\Feature\Checkout;

use App\Models\CartItem;
use App\Models\Country;
use App\Models\Setting;
use App\Services\Customer\CodValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** F11 — COD limits. Limit applies to the non-Nawi (partner) subtotal, excl. shipping. */
class CodLimitsTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): MarketplaceScenario
    {
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->actingAs($s->customer, 'customer');

        return $s;
    }

    private function cart(MarketplaceScenario $s)
    {
        return app(\App\Services\Customer\CartService::class)
            ->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
    }

    private function vendorItem($cart, $listing, int $price, int $qty = 1): CartItem
    {
        return CartItem::create(['cart_id' => $cart->id, 'vendor_listing_id' => $listing->id,
            'quantity' => $qty, 'unit_price' => $price, 'added_at' => now()]);
    }

    private function adminItem($cart, $s, int $price): CartItem
    {
        return CartItem::create(['cart_id' => $cart->id, 'admin_listing_id' => $s->adminListing->id,
            'quantity' => 1, 'unit_price' => $price, 'added_at' => now()]);
    }

    private function set(string $key, $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'category' => 'orders', 'is_encrypted' => false, 'is_public' => false]);
        \Illuminate\Support\Facades\Cache::forget('setting:'.$key);
    }

    private function errors($cart, $s): array
    {
        return app(CodValidationService::class)->validate($cart->fresh()->items()->get()->all(), $s->country->id);
    }

    public function test_boundary_equal_ok_and_plus_one_blocked(): void
    {
        $s = $this->scenario();
        $this->set('cod_global_max_amount', 50000);
        $cart = $this->cart($s);
        $item = $this->vendorItem($cart, $s->vendorListingFbp, 50000);
        $this->assertSame([], $this->errors($cart, $s));
        $item->update(['unit_price' => 50001]);
        $this->assertCount(1, $this->errors($cart, $s));
    }

    public function test_zero_or_missing_setting_is_unlimited(): void
    {
        $s = $this->scenario();
        $cart = $this->cart($s);
        $this->vendorItem($cart, $s->vendorListingFbp, 99999999);
        $this->set('cod_global_max_amount', 0);
        $this->assertSame([], $this->errors($cart, $s));
        Setting::where('key', 'cod_global_max_amount')->delete();
        \Illuminate\Support\Facades\Cache::forget('setting:cod_global_max_amount');
        $this->assertSame([], $this->errors($cart, $s));
    }

    public function test_nawi_items_exempt_and_mixed_cart_counts_only_partner_subtotal(): void
    {
        $s = $this->scenario();
        $this->set('cod_global_max_amount', 1000);
        $cart = $this->cart($s);
        $this->adminItem($cart, $s, 900000);
        $this->assertSame([], $this->errors($cart, $s));
        $this->vendorItem($cart, $s->vendorListingFbp, 1000);
        $this->assertSame([], $this->errors($cart, $s));
        $cart->items()->whereNotNull('vendor_listing_id')->update(['unit_price' => 1001]);
        $this->assertCount(1, $this->errors($cart, $s));
    }

    public function test_supermall_category_has_separate_limit(): void
    {
        $s = $this->scenario();
        $this->set('cod_global_max_amount', 100);
        $this->set('cod_supermall_max_amount', 5000);
        $this->set('cod_supermall_category_id', $s->category->id);
        $cart = $this->cart($s);
        // product is in the supermall category: uses supermall limit, not global
        $item = $this->vendorItem($cart, $s->vendorListingFbp, 5000);
        $this->assertSame([], $this->errors($cart, $s));
        $item->update(['unit_price' => 5001]);
        $errs = $this->errors($cart, $s);
        $this->assertCount(1, $errs);
    }

    public function test_international_line_blocks_cod(): void
    {
        $s = $this->scenario();
        $other = Country::factory()->create();
        $cart = $this->cart($s);
        $this->vendorItem($cart, $s->vendorListingFbp, 100);
        $s->vendorListingFbp->update(['country_id' => $other->id]);
        $this->assertNotEmpty($this->errors($cart, $s));
    }

    public function test_place_order_cod_over_limit_rejected_server_side(): void
    {
        $s = $this->scenario();
        $this->set('cod_global_max_amount', 500);
        $cart = $this->cart($s);
        $this->vendorItem($cart, $s->vendorListingFbp, 501);
        $r = $this->postJson("/api/customer/v1/{$s->country->site_code}/checkout/place-order", [
            'address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $r->assertStatus(422);
        $this->assertSame(0, \App\Models\Order::count());
    }

    public function test_payment_options_marks_cod_unavailable_over_limit(): void
    {
        $s = $this->scenario();
        $this->set('cod_global_max_amount', 500);
        $cart = $this->cart($s);
        $this->vendorItem($cart, $s->vendorListingFbp, 501);
        $r = $this->getJson("/api/customer/v1/{$s->country->site_code}/checkout/payment-options");
        $r->assertOk();
        $cod = collect($r->json('data.payment_options'))
            ->first(fn ($g) => ($g['gateway_code'] ?? null) === 'cod');
        $this->assertNotNull($cod, $r->getContent());
        $this->assertFalse($cod['is_available']);
    }

    public function test_settings_service_validates_cod_keys(): void
    {
        $this->set('cod_global_max_amount', 0);
        $svc = app(\App\Services\SettingsService::class);
        $this->assertArrayHasKey('cod_global_max_amount', $svc->validateGroup('orders', ['cod_global_max_amount' => '-5']));
        $this->assertArrayHasKey('cod_global_max_amount', $svc->validateGroup('orders', ['cod_global_max_amount' => 'abc']));
        $this->assertSame([], $svc->validateGroup('orders', ['cod_global_max_amount' => '50000']));
    }
}
