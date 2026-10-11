<?php

namespace Tests\Feature\Api\Marketer;

use App\Enums\MarketerListingStatus;
use App\Models\CartItem;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class ListingToggleStatusTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->marketer->forceFill(['onboarding_completed_at' => now()])->save();
    }

    private function accountFor(Marketer $marketer): MarketerAdmin
    {
        return MarketerAdmin::create([
            'marketer_id' => $marketer->id, 'name' => 'M', 'email' => 'm-'.Str::random(8).'@example.test',
            'password' => bcrypt('password'), 'is_owner' => true, 'is_active' => true,
        ]);
    }

    private function url(): string
    {
        return "/api/marketer/listings/{$this->s->marketerListing->id}/toggle";
    }

    public function test_pauses_an_active_listing_as_a_manual_pause(): void
    {
        $this->actingAs($this->accountFor($this->s->marketer), 'marketer_api')
            ->postJson($this->url())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'paused')
            ->assertJsonPath('data.paused_reason', 'manual');

        $listing = $this->s->marketerListing->fresh();
        $this->assertSame(MarketerListingStatus::Paused, $listing->status);
        $this->assertSame('manual', $listing->paused_reason);
    }

    public function test_resumes_a_paused_listing_and_clears_the_pause_reason(): void
    {
        $this->s->marketerListing->update(['status' => MarketerListingStatus::Paused, 'paused_reason' => 'manual']);

        $this->actingAs($this->accountFor($this->s->marketer), 'marketer_api')
            ->postJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.paused_reason', null);

        $listing = $this->s->marketerListing->fresh();
        $this->assertSame(MarketerListingStatus::Active, $listing->status);
        $this->assertNull($listing->paused_reason);
    }

    public function test_toggling_twice_returns_to_the_original_state(): void
    {
        $account = $this->accountFor($this->s->marketer);

        $this->actingAs($account, 'marketer_api')->postJson($this->url())->assertJsonPath('data.status', 'paused');
        $this->actingAs($account, 'marketer_api')->postJson($this->url())->assertJsonPath('data.status', 'active');
    }

    public function test_forbids_toggling_another_marketers_listing(): void
    {
        $other = Marketer::create([
            'name' => 'Other', 'email' => 'o-'.Str::random(8).'@example.test', 'phone' => '+9715'.fake()->numerify('########'),
            'marketer_type' => 'affiliate', 'global_status' => 'active', 'approved_at' => now(), 'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($this->accountFor($other), 'marketer_api')->postJson($this->url())->assertForbidden();

        $this->assertSame(MarketerListingStatus::Active, $this->s->marketerListing->fresh()->status);
    }

    public function test_paused_listing_is_not_purchasable_because_it_leaves_the_cart(): void
    {
        $cart = app(CartService::class)->getOrCreateCart($this->s->customer, $this->s->country->id, $this->s->country->currency_code);
        $item = CartItem::create([
            'cart_id' => $cart->id, 'marketer_listing_id' => $this->s->marketerListing->id, 'quantity' => 1,
            'unit_price' => (int) $this->s->marketerListing->price, 'added_at' => now(),
        ]);

        $this->actingAs($this->accountFor($this->s->marketer), 'marketer_api')->postJson($this->url())->assertOk();
        app(CartService::class)->getOrCreateCart($this->s->customer, $this->s->country->id, $this->s->country->currency_code);

        $this->assertModelMissing($item);
    }
}
