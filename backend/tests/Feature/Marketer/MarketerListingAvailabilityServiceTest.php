<?php

namespace Tests\Feature\Marketer;

use App\Enums\MarketerListingStatus;
use App\Services\Marketer\MarketerListingAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class MarketerListingAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
    }

    private function sync(): void
    {
        app(MarketerListingAvailabilityService::class)->syncListing($this->s->marketerListing->fresh());
    }

    public function test_never_touches_an_archived_listing(): void
    {
        $this->s->marketerListing->update(['status' => MarketerListingStatus::Archived]);

        $this->sync();

        $this->assertSame(MarketerListingStatus::Archived, $this->s->marketerListing->fresh()->status);
    }

    public function test_leaves_a_manually_paused_listing_paused_even_when_its_source_is_available(): void
    {
        $this->s->marketerListing->update(['status' => MarketerListingStatus::Paused, 'paused_reason' => 'manual']);

        $this->sync();

        $listing = $this->s->marketerListing->fresh();
        $this->assertSame(MarketerListingStatus::Paused, $listing->status);
        $this->assertSame('manual', $listing->paused_reason);
    }

    public function test_resumes_a_listing_it_auto_paused_once_the_source_is_available_again(): void
    {
        $this->s->marketerListing->update(['status' => MarketerListingStatus::Paused, 'paused_reason' => 'source_unavailable']);

        $this->sync();

        $listing = $this->s->marketerListing->fresh();
        $this->assertSame(MarketerListingStatus::Active, $listing->status);
        $this->assertNull($listing->paused_reason);
    }

    public function test_auto_pauses_an_active_listing_whose_source_is_unavailable(): void
    {
        $this->s->vendorListingFbp->update(['status' => 'paused']);

        $this->sync();

        $listing = $this->s->marketerListing->fresh();
        $this->assertSame(MarketerListingStatus::Paused, $listing->status);
        $this->assertSame('source_unavailable', $listing->paused_reason);
    }
}
