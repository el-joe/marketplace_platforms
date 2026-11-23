<?php

namespace Tests\Feature\Checkout;

use App\Enums\VendorListingStatus;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductLinkCommissionRule;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Models\WarehouseInventory;
use App\Services\Customer\CartService;
use App\Services\LastClickAttributionService;
use App\Services\ProductLink\ProductShareLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * WS12 #24: a customer who arrives through the vendor's own product link / QR code earns that vendor a discount on
 * the platform commission of that product, under the country's rule. Everything else keeps the normal commission.
 */
class ProductLinkCommissionDiscountTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private string $sessionId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->sessionId = (string) Str::uuid();
    }

    private function rule(array $attributes = []): ProductLinkCommissionRule
    {
        return ProductLinkCommissionRule::create(array_merge([
            'country_id' => $this->scenario->country->id,
            'fulfillment_models' => ['fbm', 'fbn', 'cross_dock'],
            'discount_type' => ProductLinkCommissionRule::TYPE_PERCENT,
            'discount_percent' => 50,
            'discount_fixed' => 0,
            'attribution_window_days' => 7,
            'is_active' => true,
        ], $attributes));
    }

    private function clickVendorLink(VendorListing $listing, string $channel = 'link'): void
    {
        $token = app(ProductShareLinkService::class)->tokenFor($listing);

        $this->getJson("/api/s/{$token}?session_id={$this->sessionId}&c={$channel}")
            ->assertOk()
            ->assertJsonPath('attributed', true);
    }

    private function buy(VendorListing $listing, int $quantity = 1): OrderItem
    {
        $this->actingAs($this->scenario->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($this->scenario->customer, $this->scenario->country->id, $this->scenario->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $listing->id,
            'quantity' => $quantity,
            'unit_price' => (float) $listing->getRawOriginal('price'),
            'added_at' => now(),
        ]);

        $response = $this->withHeader('X-Session-Id', $this->sessionId)
            ->postJson("/api/customer/v1/{$this->scenario->country->site_code}/checkout/place-order", [
                'address_id' => $this->scenario->customerAddress->id,
                'country_payment_gateway_id' => $this->scenario->countryPaymentGateways['cod']->id,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertStatus(201);

        $order = Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))->firstOrFail();

        return $order->items()->sole();
    }

    private function assertNormalCommission(OrderItem $item): void
    {
        $this->assertNull($item->source_link_channel);
        $this->assertEquals(0, $item->source_link_discount);
        $this->assertGreaterThan(0, (float) $item->commission_amount);
        $this->assertEquals((float) $item->commission_amount, (float) $item->platform_commission_after_discount);
    }

    public function test_percent_rule_halves_the_platform_commission_of_the_qr_attributed_item(): void
    {
        $rule = $this->rule();
        $this->clickVendorLink($this->scenario->vendorListingFbp, 'qr');

        $item = $this->buy($this->scenario->vendorListingFbp);
        $raw = (float) $item->commission_amount;

        $this->assertSame('qr', $item->source_link_channel);
        $this->assertSame($rule->id, $item->source_link_rule_id);
        $this->assertSame('percent', $item->source_link_rule_snapshot['discount_type']);
        $this->assertEqualsWithDelta(round($raw / 2, 2), (float) $item->source_link_discount, 0.0001);
        $this->assertEqualsWithDelta($raw - (float) $item->source_link_discount, (float) $item->platform_commission_after_discount, 0.0001);
        $this->assertEqualsWithDelta((float) $item->platform_commission_after_discount, (float) $item->subOrder->platform_commission, 0.0001);
    }

    public function test_fixed_rule_takes_the_amount_off_per_unit(): void
    {
        $this->rule(['discount_type' => 'fixed', 'discount_percent' => 0, 'discount_fixed' => 10]);
        $this->clickVendorLink($this->scenario->vendorListingFbp);

        $item = $this->buy($this->scenario->vendorListingFbp, 2);

        $this->assertSame('link', $item->source_link_channel);
        $this->assertEquals(20, (float) $item->source_link_discount);
        $this->assertEqualsWithDelta((float) $item->commission_amount - 20, (float) $item->platform_commission_after_discount, 0.0001);
    }

    public function test_both_rule_adds_the_percentage_and_the_fixed_amount(): void
    {
        $this->rule(['discount_type' => 'both', 'discount_percent' => 10, 'discount_fixed' => 5]);
        $this->clickVendorLink($this->scenario->vendorListingFbp);

        $item = $this->buy($this->scenario->vendorListingFbp);
        $raw = (float) $item->commission_amount;

        $this->assertEqualsWithDelta(round($raw * 0.10 + 5, 2), (float) $item->source_link_discount, 0.0001);
    }

    public function test_zero_rule_waives_the_whole_platform_commission_and_the_vendor_keeps_it(): void
    {
        $this->rule(['discount_type' => 'zero', 'discount_percent' => 0]);
        $this->clickVendorLink($this->scenario->vendorListingFbn);

        $item = $this->buy($this->scenario->vendorListingFbn);

        $this->assertEquals((float) $item->commission_amount, (float) $item->source_link_discount);
        $this->assertEquals(0, (float) $item->platform_commission_after_discount);
        $this->assertEquals(0, (float) $item->subOrder->platform_commission);
    }

    public function test_the_link_opens_the_product_page_with_the_vendors_own_offer_selected(): void
    {
        $listing = $this->scenario->vendorListingFbp;
        $token = app(ProductShareLinkService::class)->tokenFor($listing);
        $frontend = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        $this->getJson("/api/s/{$token}?session_id={$this->sessionId}")
            ->assertOk()
            ->assertJsonPath('destination', $frontend.'/products/'.$listing->id);

        $this->get("/api/s/{$token}?session_id={$this->sessionId}")->assertRedirect($frontend.'/products/'.$listing->id);
    }

    public function test_the_link_of_a_listing_no_longer_on_sale_falls_back_to_the_product_page(): void
    {
        $listing = $this->scenario->vendorListingFbp;
        $listing->update(['status' => VendorListingStatus::Paused]);
        $token = app(ProductShareLinkService::class)->tokenFor($listing);
        $frontend = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        $this->getJson("/api/s/{$token}?session_id={$this->sessionId}")
            ->assertOk()
            ->assertJsonPath('destination', $frontend.'/products/'.$listing->productVariant->product->slug);
    }

    public function test_a_normal_visit_or_a_forged_token_keeps_the_normal_commission(): void
    {
        $this->rule();
        $token = app(ProductShareLinkService::class)->tokenFor($this->scenario->vendorListingFbp);
        $forged = substr($token, 0, 32).str_repeat('0', 16);

        $this->getJson("/api/s/{$forged}?session_id={$this->sessionId}")->assertOk()->assertJsonPath('attributed', false);

        $this->assertNormalCommission($this->buy($this->scenario->vendorListingFbp));
    }

    public function test_a_purchase_after_the_attribution_window_keeps_the_normal_commission(): void
    {
        $this->rule(['attribution_window_days' => 3]);
        $this->clickVendorLink($this->scenario->vendorListingFbp);

        $this->travel(4)->days();

        $this->assertNormalCommission($this->buy($this->scenario->vendorListingFbp));
    }

    public function test_another_vendors_offer_of_the_same_product_is_not_discounted(): void
    {
        $this->rule();
        $this->clickVendorLink($this->scenario->vendorListingFbp);

        $otherVendor = Vendor::factory()->create(['country_id' => $this->scenario->country->id]);
        $otherListing = $this->scenario->vendorListingFbp->replicate();
        $otherListing->id = (string) Str::uuid();
        $otherListing->vendor_id = $otherVendor->id;
        $otherListing->save();
        WarehouseInventory::create([
            'vendor_listing_id' => $otherListing->id,
            'warehouse_id' => $this->scenario->vendorWarehouse->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $this->assertNormalCommission($this->buy($otherListing));
    }

    public function test_a_sale_model_outside_the_rule_keeps_the_normal_commission(): void
    {
        $this->rule(['fulfillment_models' => ['fbn']]);
        $this->clickVendorLink($this->scenario->vendorListingFbp); // fbm

        $this->assertNormalCommission($this->buy($this->scenario->vendorListingFbp));
    }

    public function test_a_marketer_link_clicked_after_the_vendor_link_wins_and_cancels_the_discount(): void
    {
        $this->rule();
        $this->clickVendorLink($this->scenario->vendorListingFbp);

        $this->travel(1)->minutes();
        $invitation = $this->scenario->marketerCampaignInvitation;
        app(LastClickAttributionService::class)->recordClick((string) $invitation->referral_code, $this->sessionId, $invitation);

        $this->assertNormalCommission($this->buy($this->scenario->vendorListingFbp));
    }

    public function test_the_vendor_link_clicked_after_a_marketer_link_still_earns_the_discount(): void
    {
        $this->rule();
        $invitation = $this->scenario->marketerCampaignInvitation;
        app(LastClickAttributionService::class)->recordClick((string) $invitation->referral_code, $this->sessionId, $invitation);

        $this->travel(1)->minutes();
        $this->clickVendorLink($this->scenario->vendorListingFbp);

        $item = $this->buy($this->scenario->vendorListingFbp);
        $this->assertGreaterThan(0, (float) $item->source_link_discount);
    }
}
