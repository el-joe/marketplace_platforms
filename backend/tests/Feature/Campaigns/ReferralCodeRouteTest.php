<?php

namespace Tests\Feature\Campaigns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\Support\ReferralCodeFixtures as R;
use Tests\TestCase;

/** P14, customer-facing GET /api/r/{code}: what a scan does for every code state. */
class ReferralCodeRouteTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        config(['app.frontend_url' => 'https://shop.test']);
        $this->s = MarketplaceScenario::make()->build();
    }

    private function slug(): string
    {
        return $this->s->vendorListingFbp->productVariant->product->slug;
    }

    public function test_accepted_code_remembers_the_click_and_lands_on_the_marketer_listing(): void
    {
        $inv = $this->s->marketerCampaignInvitation;

        $res = R::scan($this, $inv->referral_code, 'rt-1')->assertOk()->assertJsonPath('attributed', true);

        $this->assertStringContainsString('/products/'.$this->slug().'?ref='.$inv->referral_code, $res->json('destination'));
        $this->assertStringContainsString('marketer_listing_id='.$this->s->marketerListing->id, $res->json('destination'));
        $this->assertSame($inv->referral_code, Cache::get('referral_click:rt-1')['referral_code']);
    }

    public function test_plain_navigation_redirects_instead_of_returning_json(): void
    {
        $this->get('/api/r/'.$this->s->marketerCampaignInvitation->referral_code, ['X-Session-Id' => 'rt-2'])
            ->assertRedirectContains('https://shop.test/products/'.$this->slug());
    }

    public function test_replaced_code_redirects_to_the_product_page_without_ref_and_records_nothing(): void
    {
        $a = F::influencer($this->s);
        $campaign = R::pendingCampaign($this->s, $a);
        $invA = R::invitationOf($campaign, $a);
        R::reject($invA->fresh());

        $res = R::scan($this, $invA->referral_code, 'rt-3')->assertOk()->assertJsonPath('attributed', false);

        $this->assertSame('https://shop.test/products/'.$this->slug(), $res->json('destination'));
        $this->assertStringNotContainsString('ref=', $res->json('destination'));
        $this->assertNull(Cache::get('referral_click:rt-3'));
    }

    public function test_cancelled_campaign_code_goes_to_the_product_page_with_no_ref(): void
    {
        $this->s->marketerCampaign->update(['status' => 'cancelled']);

        $res = R::scan($this, $this->s->marketerCampaignInvitation->referral_code, 'rt-4')->assertJsonPath('attributed', false);

        $this->assertSame('https://shop.test/products/'.$this->slug(), $res->json('destination'));
        $this->assertNull(Cache::get('referral_click:rt-4'));
    }

    public function test_pending_code_remembers_the_click_and_unknown_code_keeps_the_legacy_redirect(): void
    {
        $a = F::influencer($this->s);
        $invA = R::invitationOf(R::pendingCampaign($this->s, $a), $a);

        R::scan($this, $invA->referral_code, 'rt-5')->assertJsonPath('attributed', true);
        $this->assertNotNull(Cache::get('referral_click:rt-5'));

        R::scan($this, 'NOSUCHCODE', 'rt-6')->assertOk()
            ->assertJsonPath('destination', 'https://shop.test?ref=NOSUCHCODE')
            ->assertJsonPath('attributed', false);
        $this->assertNull(Cache::get('referral_click:rt-6'));
    }
}
