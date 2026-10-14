<?php

namespace Tests\Feature\Campaigns;

use App\Enums\PromotionCategory;
use App\Exceptions\PeerPromotionException;
use App\Models\MarketerCampaign;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\MarketerListing;
use App\Services\Marketer\PromotionQuotaService;
use App\Services\MarketerCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketplaceScenario;
use Tests\Support\PeerPromotionFixtures as P;
use Tests\TestCase;

/** P13 (R11): service level rules of influencer-to-influencer promotion. */
class PeerPromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->s = MarketplaceScenario::make()->build();
    }

    private function assertRejected(string $reason, callable $fn): void
    {
        try {
            $fn();
            $this->fail("Expected PeerPromotionException({$reason})");
        } catch (PeerPromotionException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    public function test_request_creates_an_active_campaign_with_an_accepted_invitation_and_a_proxy_listing(): void
    {
        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        P::promoterRule($this->s, $promoter, 3000);
        $listing = P::ownListing($this->s, $owner);

        $campaign = P::request($promoter, $listing);

        $this->assertSame('active', $campaign->status);
        $this->assertSame($listing->id, $campaign->marketer_listing_id);
        $this->assertSame('marketer', $campaign->owner_type);
        $this->assertSame($owner->id, $campaign->owner_id);
        $this->assertSame($promoter->id, $campaign->requested_by_marketer_id);
        $this->assertNull($campaign->vendor_id);
        $this->assertSame(3000, (int) $campaign->marketer_commission_amount);

        $invitation = $campaign->invitations()->sole();
        $this->assertSame($promoter->id, $invitation->marketer_id);
        $this->assertSame('accepted', $invitation->status);
        $this->assertSame('not_applicable', $invitation->platform_fee_status);

        $proxy = MarketerListing::where('invitation_id', $invitation->id)->sole();
        $this->assertSame('marketer_listing', $proxy->source_type);
        $this->assertSame($listing->id, $proxy->source_listing_id);
        $this->assertSame($promoter->id, $proxy->marketer_id);
        $this->assertSame(20000, (int) $proxy->getRawOriginal('price'));
        $this->assertSame('active', $proxy->status->value);

        // No physical samples for a peer promotion.
        $this->assertSame(0, $campaign->samples()->count());
    }

    public function test_promoter_cannot_promote_his_own_listing(): void
    {
        $owner = F::influencer($this->s);
        $listing = P::ownListing($this->s, $owner);

        $before = MarketerCampaign::count();
        $this->assertRejected(PeerPromotionException::SELF_PROMOTION, fn () => P::request($owner, $listing));
        $this->assertSame($before, MarketerCampaign::count());
    }

    public function test_listing_must_be_active(): void
    {
        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);

        foreach (['paused', 'draft', 'out_of_stock', 'archived'] as $status) {
            $listing = P::ownListing($this->s, $owner, 20, $status);
            $this->assertRejected(PeerPromotionException::LISTING_NOT_ACTIVE, fn () => P::request($promoter, $listing));
        }
    }

    public function test_only_own_fbn_listings_of_influencers_can_be_promoted(): void
    {
        $promoter = F::influencer($this->s);

        // A proxy listing (source_listing_id set) is not an influencer-owned product.
        $this->assertRejected(PeerPromotionException::NOT_PEER_LISTING, fn () => P::request($promoter, $this->s->marketerListing));

        // Owner is an affiliate, not an influencer.
        $affiliateListing = P::ownListing($this->s, F::affiliate($this->s));
        $this->assertRejected(PeerPromotionException::NOT_INFLUENCER, fn () => P::request($promoter, $affiliateListing));

        // Promoter is an affiliate.
        $listing = P::ownListing($this->s, F::influencer($this->s));
        $this->assertRejected(PeerPromotionException::NOT_INFLUENCER, fn () => P::request(F::affiliate($this->s), $listing));
    }

    public function test_stock_check_uses_the_listings_own_warehouse_inventory(): void
    {
        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        $empty = P::ownListing($this->s, $owner, 0);

        $this->assertRejected(PeerPromotionException::INSUFFICIENT_STOCK, fn () => P::request($promoter, $empty));

        // Reserved units are not available.
        $reserved = P::ownListing($this->s, $owner, 5);
        $reserved->warehouseInventories()->update(['quantity_reserved' => 5]);
        $this->assertRejected(PeerPromotionException::INSUFFICIENT_STOCK, fn () => P::request($promoter, $reserved));

        // The minimum is admin configurable.
        F::setting('marketer_peer_campaign_min_stock', 10);
        $low = P::ownListing($this->s, $owner, 9);
        $this->assertRejected(PeerPromotionException::INSUFFICIENT_STOCK, fn () => P::request($promoter, $low));
        $ok = P::ownListing($this->s, $owner, 10);
        $this->assertSame('active', P::request($promoter, $ok)->status);
    }

    public function test_one_promotion_per_promoter_and_owner_per_calendar_month(): void
    {
        $owner = F::influencer($this->s);
        $otherOwner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        $other = F::influencer($this->s);

        $first = P::ownListing($this->s, $owner);
        $second = P::ownListing($this->s, $owner);

        $campaign = P::request($promoter, $first);

        // Same owner, a DIFFERENT product of his, same month: blocked.
        $this->assertRejected(PeerPromotionException::MONTHLY_LIMIT, fn () => P::request($promoter, $second));
        $this->assertRejected(PeerPromotionException::MONTHLY_LIMIT, fn () => P::request($promoter, $first));

        // Another promoter on the same owner, and the same promoter on another owner, are fine.
        $this->assertSame('active', P::request($other, $first)->status);
        $this->assertSame('active', P::request($promoter, P::ownListing($this->s, $otherOwner))->status);

        // A cancelled promotion frees the slot.
        app(MarketerCampaignService::class)->cancelCampaign($campaign->fresh());
        $this->assertSame('active', P::request($promoter, $second)->status);

        // Next calendar month the rule resets.
        $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addDay());
        $this->assertSame('active', P::request($promoter, $first)->status);
    }

    public function test_peer_promotion_counts_for_category_two_quota(): void
    {
        $owner = F::influencer($this->s);
        $promoter = F::influencer($this->s);
        $promoter->marketerProfile()->update(['promotion_category' => PromotionCategory::PeerInfluencerProducts->value]);

        $quota = app(PromotionQuotaService::class);
        $this->assertSame(0, $quota->doneFor($promoter->fresh(), PromotionCategory::PeerInfluencerProducts, now()));

        P::request($promoter, P::ownListing($this->s, $owner));

        $this->assertSame(1, $quota->doneFor($promoter->fresh(), PromotionCategory::PeerInfluencerProducts, now()));
        $this->assertSame(0, $quota->doneFor($promoter->fresh(), PromotionCategory::VendorRequests, now()));
    }

    public function test_peer_commission_is_the_promoters_profile_rule_and_zero_without_one_even_with_a_category_default(): void
    {
        // D11/D12: the category-tab affiliate default and fee share never give an influencer a per-sale commission.
        MarketerCommissionCountrySetting::create([
            'category_id' => $this->s->category->id, 'country_id' => $this->s->country->id,
            'influencer_fee_share_pct' => 20, 'affiliate_commission_mode' => 'fixed', 'affiliate_commission_flat' => 1000, 'currency' => 'AED',
        ]);
        $owner = F::influencer($this->s);
        $withoutRule = F::influencer($this->s);
        $withRule = F::influencer($this->s);
        P::promoterRule($this->s, $withRule, 4500);

        $campaign = P::request($withoutRule, P::ownListing($this->s, $owner));
        $this->assertSame(0, (int) $campaign->marketer_commission_amount);
        $this->assertTrue($campaign->hasNoPeerCommission());

        $campaign2 = P::request($withRule, P::ownListing($this->s, $owner));
        $this->assertSame(4500, (int) $campaign2->marketer_commission_amount);
        $this->assertFalse($campaign2->hasNoPeerCommission());
    }

    public function test_pausing_the_owners_listing_pauses_the_proxy_listing(): void
    {
        $owner = F::influencer($this->s);
        $listing = P::ownListing($this->s, $owner);
        $campaign = P::request(F::influencer($this->s), $listing);
        $proxy = MarketerListing::where('invitation_id', $campaign->invitations()->sole()->id)->sole();
        $this->assertSame('active', $proxy->status->value);

        $listing->update(['status' => 'paused']);

        $this->assertSame('paused', $proxy->fresh()->status->value);

        $listing->update(['status' => 'active']);
        $this->assertSame('active', $proxy->fresh()->status->value);
    }
}
