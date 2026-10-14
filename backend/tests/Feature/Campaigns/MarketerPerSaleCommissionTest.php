<?php

namespace Tests\Feature\Campaigns;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerCommissionRule;
use App\Models\MarketerJob;
use App\Models\MarketerMarketerJob;
use App\Models\Order;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * D3/D11 regression: the per-sale commission recorded at order time.
 *  - affiliates: campaign-typed amount, else profile rule, else the category default (fixed / percentage / both);
 *  - influencers: campaign-typed amount or profile rule only (the category tab gives them a fee share instead).
 */
class MarketerPerSaleCommissionTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        // No typed per-unit amount: the commission is resolved by the rules.
        $this->s->marketerCampaign->update(['marketer_commission_amount' => 0]);
    }

    private function categoryDefault(string $mode, float $rate, float $flat): void
    {
        MarketerCommissionCountrySetting::create([
            'category_id' => $this->s->category->id, 'country_id' => $this->s->country->id, 'currency' => 'AED',
            'influencer_fee_share_pct' => 20,
            'affiliate_commission_mode' => $mode, 'affiliate_commission_rate' => $rate, 'affiliate_commission_flat' => $flat,
        ]);
    }

    private function makeScenarioMarketerInfluencer(): void
    {
        $job = MarketerJob::firstOrCreate(['key' => 'influencer'], ['name_ar' => 'influencer', 'name_en' => 'influencer', 'is_active' => true, 'sort_order' => 1]);
        MarketerMarketerJob::create(['marketer_id' => $this->s->marketer->id, 'marketer_job_id' => $job->id]);
    }

    private function profileRule(string $mode, float $rate, float $flat): void
    {
        MarketerCommissionRule::create([
            'marketer_id' => $this->s->marketer->id, 'scope' => 'products',
            'category_type' => Category::class, 'category_id' => $this->s->category->id,
            'commission_mode' => $mode, 'commission_rate' => $rate, 'commission_flat_amount' => $flat,
        ]);
    }

    private function convert(int $qty): MarketerCampaignConversion
    {
        $this->actingAs($this->s->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($this->s->customer, $this->s->country->id, $this->s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'marketer_listing_id' => $this->s->marketerListing->id, 'quantity' => $qty,
            'unit_price' => (int) $this->s->marketerListing->price, 'added_at' => now(),
        ]);

        $response = $this->postJson("/api/customer/v1/{$this->s->country->site_code}/checkout/place-order", [
            'address_id' => $this->s->customerAddress->id,
            'country_payment_gateway_id' => $this->s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(201);

        $order = Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))->firstOrFail();

        return MarketerCampaignConversion::where('order_id', $order->id)->sole();
    }

    public function test_affiliate_earns_the_fixed_category_default_per_unit(): void
    {
        $this->categoryDefault('fixed', 0, 7);

        $this->assertEquals(21.0, (float) $this->convert(3)->commission_amount);
    }

    public function test_affiliate_earns_the_percentage_category_default_of_the_line(): void
    {
        $this->categoryDefault('percentage', 10, 0);

        $conversion = $this->convert(2);
        $line = (float) $conversion->orderItem->line_total;

        $this->assertGreaterThan(0, $line);
        $this->assertEquals(round($line * 0.10, 2), (float) $conversion->commission_amount);
    }

    public function test_affiliate_earns_percentage_plus_flat_in_both_mode(): void
    {
        $this->categoryDefault('both', 10, 3);

        $conversion = $this->convert(2);
        $line = (float) $conversion->orderItem->line_total;

        $this->assertEquals(round($line * 0.10 + 3 * 2, 2), (float) $conversion->commission_amount);
    }

    public function test_the_profile_rule_beats_the_category_default_for_an_affiliate(): void
    {
        $this->categoryDefault('fixed', 0, 7);
        $this->profileRule('fixed', 0, 11);

        $this->assertEquals(22.0, (float) $this->convert(2)->commission_amount);
    }

    public function test_the_stored_category_default_on_the_campaign_is_resolved_on_the_real_line(): void
    {
        // Approval stores the per-unit estimate of the affiliate default on the campaign: it is not a typed override.
        $this->categoryDefault('fixed', 0, 7);
        $this->s->marketerCampaign->update(['marketer_commission_amount' => 7]);
        $this->profileRule('fixed', 0, 11);

        $this->assertEquals(22.0, (float) $this->convert(2)->commission_amount);
    }

    public function test_a_typed_campaign_amount_still_wins(): void
    {
        $this->categoryDefault('fixed', 0, 7);
        $this->s->marketerCampaign->update(['marketer_commission_amount' => 50]);

        $this->assertEquals(100.0, (float) $this->convert(2)->commission_amount);
    }

    public function test_influencer_gets_no_per_sale_commission_from_the_category_defaults(): void
    {
        $this->makeScenarioMarketerInfluencer();
        $this->categoryDefault('both', 10, 7);

        $this->assertEquals(0.0, (float) $this->convert(2)->commission_amount);
    }

    public function test_influencer_ignores_the_stored_affiliate_default_on_the_campaign(): void
    {
        $this->makeScenarioMarketerInfluencer();
        $this->categoryDefault('fixed', 0, 7);
        $this->s->marketerCampaign->update(['marketer_commission_amount' => 7]);

        $this->assertEquals(0.0, (float) $this->convert(2)->commission_amount);
    }

    public function test_influencer_per_sale_commission_comes_only_from_the_profile_rule(): void
    {
        $this->makeScenarioMarketerInfluencer();
        $this->categoryDefault('both', 10, 7);
        $this->profileRule('fixed', 0, 9);

        $this->assertEquals(18.0, (float) $this->convert(2)->commission_amount);
    }
}
