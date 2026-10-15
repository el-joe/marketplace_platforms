<?php

namespace Tests\Feature\Campaigns;

use App\Enums\ClassifiedListingStatus;
use App\Jobs\ApproveMarketerConversionsJob;
use App\Models\ClassifiedListing;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignConversion;
use App\Services\Marketer\ClassifiedSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Support\CampaignSampleFixtures as F;
use Tests\Support\MarketerV3Fixtures as V;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/** Classified "Mark sold": sale price is required while a campaign is live, the credited marketer earns, un-marking reverses. */
class ClassifiedMarkSoldCampaignTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private ClassifiedListing $listing;

    private MarketerCampaign $campaign;

    private $affiliate;

    private ClassifiedSaleService $sales;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
        $this->withoutVite();
        $this->s = MarketplaceScenario::make()->build();
        V::classifiedVendor($this->s);
        $this->listing = V::classifiedListing($this->s);
        V::defaults($this->s->country, 'classified', feePct: 10, feeFixed: 2);
        $this->affiliate = F::affiliate($this->s);
        V::rule($this->affiliate, 'classified', rate: 10);
        $this->campaign = V::classifiedCampaign($this->s, $this->listing, [$this->affiliate], 1000);
        $this->sales = app(ClassifiedSaleService::class);
    }

    private function conversion(): ?MarketerCampaignConversion
    {
        return MarketerCampaignConversion::where('classified_listing_id', $this->listing->id)->first();
    }

    public function test_a_price_is_required_while_a_campaign_is_live(): void
    {
        foreach ([null, 0.0] as $price) {
            try {
                $this->sales->markSold($this->listing->fresh(), $price, null, $this->affiliate->id);
                $this->fail('Marking sold without a price must be refused.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('sold_price', $e->errors());
            }
        }
        $this->assertSame(ClassifiedListingStatus::Active, $this->listing->fresh()->status);
    }

    public function test_the_marketer_must_belong_to_the_campaign(): void
    {
        $stranger = F::affiliate($this->s);

        $this->expectException(ValidationException::class);
        $this->sales->markSold($this->listing->fresh(), 5000, null, $stranger->id);
    }

    public function test_marking_sold_saves_the_sale_and_credits_the_affiliate_on_the_sold_price(): void
    {
        $sold = $this->sales->markSold($this->listing->fresh(), 5000, 'Buyer from a friend', $this->affiliate->id);

        $this->assertSame(ClassifiedListingStatus::Sold, $sold->status);
        $this->assertEquals(5000, $sold->sold_price);
        $this->assertNotNull($sold->sold_at);
        $this->assertSame($this->campaign->id, $sold->sold_via_campaign_id);

        $conversion = $this->conversion();
        $this->assertSame(MarketerCampaignConversion::TYPE_CLASSIFIED_SALE, $conversion->conversion_type);
        $this->assertEquals(5000, $conversion->sale_amount);
        $this->assertEquals([500, 52, 448], [$conversion->gross_commission_amount, $conversion->commission_fee_amount, $conversion->net_commission_amount]);
        $this->assertSame('done', $this->campaign->fresh()->status, 'a sale ends the campaign');
    }

    public function test_without_a_credited_marketer_no_commission_is_recorded(): void
    {
        $this->sales->markSold($this->listing->fresh(), 5000);

        $this->assertNull($this->conversion());
        $this->assertSame('done', $this->campaign->fresh()->status);
    }

    public function test_a_listing_without_a_campaign_needs_no_price(): void
    {
        $plain = V::classifiedListing($this->s);

        $sold = $this->sales->markSold($plain);

        $this->assertSame(ClassifiedListingStatus::Sold, $sold->status);
        $this->assertNull($sold->sold_price);
    }

    public function test_unmarking_reverses_the_conversion_and_clears_the_sale(): void
    {
        $this->sales->markSold($this->listing->fresh(), 5000, null, $this->affiliate->id);

        $listing = $this->sales->unmarkSold($this->listing->fresh());

        $this->assertSame(ClassifiedListingStatus::Active, $listing->status);
        $this->assertNull($listing->sold_price);
        $this->assertNull($listing->sold_via_campaign_id);
        $this->assertSame('reversed', $this->conversion()->status);
        $this->assertSame('done', $this->campaign->fresh()->status, 'a finished campaign is not reopened');
    }

    public function test_a_commission_already_paid_out_blocks_unmarking(): void
    {
        $this->sales->markSold($this->listing->fresh(), 5000, null, $this->affiliate->id);
        $this->conversion()->update(['status' => 'paid']);

        try {
            $this->sales->unmarkSold($this->listing->fresh());
            $this->fail('A paid commission cannot be taken back.');
        } catch (ValidationException) {
            $this->assertSame(ClassifiedListingStatus::Sold, $this->listing->fresh()->status);
        }
    }

    public function test_the_commission_is_approved_after_the_classified_clearing_days(): void
    {
        $this->sales->markSold($this->listing->fresh(), 5000, null, $this->affiliate->id);

        app()->call([new ApproveMarketerConversionsJob, 'handle']);
        $this->assertSame('pending', $this->conversion()->status, 'the default 2 days have not passed');

        $this->conversion()->update(['triggered_at' => now()->subDays(3)]);
        app()->call([new ApproveMarketerConversionsJob, 'handle']);

        $this->assertSame('approved', $this->conversion()->status);
    }
}
