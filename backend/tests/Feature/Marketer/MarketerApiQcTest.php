<?php

namespace Tests\Feature\Marketer;

use App\Models\ClassifiedInquiry;
use App\Models\ClassifiedListing;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Models\MarketerAdPackage;
use App\Models\MarketerConversation;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class MarketerApiQcTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
    }

    private function admin(Marketer $m, bool $onboarded = true): MarketerAdmin
    {
        if ($onboarded) {
            $m->forceFill(['onboarding_completed_at' => now()])->save();
        }
        return MarketerAdmin::create([
            'marketer_id' => $m->id, 'name' => 'M', 'email' => 'm-'.Str::random(8).'@example.test',
            'password' => bcrypt('password'), 'is_owner' => true, 'is_active' => true,
        ]);
    }

    private function otherMarketer(): Marketer
    {
        $m = $this->s->marketer->replicate();
        $m->phone = '+9715'.fake()->numerify('########');
        $m->email = 'om-'.Str::random(6).'@example.test';
        $m->save();

        return $m;
    }

    public function test_onboarding_gates_api_until_complete(): void
    {
        $m = $this->s->marketer;
        $a = $this->admin($m, false);
        if (! $m->fresh()->needsOnboarding()) {
            $this->markTestSkipped('Scenario marketer already onboarded; gating not reproducible here.');
        }
        $this->actingAs($a, 'marketer_api')->getJson('/api/marketer/dashboard')
            ->assertForbidden()->assertJsonPath('code', 'onboarding_required');
        $this->actingAs($a, 'marketer_api')->getJson('/api/marketer/me')->assertOk();
    }

    public function test_ad_package_wallet_subscribe_insufficient_then_success(): void
    {
        $m = $this->s->marketer;
        $a = $this->admin($m);
        $currency = $m->country?->currency_code ?? 'AED';
        $pkg = MarketerAdPackage::create([
            'name_ar' => 'x', 'name_en' => 'Pkg', 'price' => 10000, 'currency' => $currency, 'vat_pct' => 5,
            'target_type' => 'all', 'duration_days' => 30, 'is_active' => true, 'sort_order' => 1,
        ]);
        $url = "/api/marketer/ad-packages/{$pkg->id}/subscribe";

        $this->actingAs($a, 'marketer_api')->postJson($url, ['payment_method' => 'wallet'])
            ->assertStatus(422)->assertJsonPath('success', false);

        $w = app(WalletService::class)->getOrCreateWallet('marketer', $m->id, $currency);
        app(WalletService::class)->credit($w, 100000, 'test', null, 'seed');

        $this->actingAs($a, 'marketer_api')->postJson($url, ['payment_method' => 'wallet'])
            ->assertStatus(201)->assertJsonPath('success', true);
        $this->actingAs($a, 'marketer_api')->getJson('/api/marketer/ad-packages/my-subscription')
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.package_id', $pkg->id);
    }

    public function test_inquiry_and_conversation_ownership_isolation(): void
    {
        $mine = $this->admin($this->s->marketer);
        $otherM = $this->otherMarketer();
        $theirs = $this->admin($otherM);

        $cat = \App\Models\ClassifiedCategory::query()->value('id')
            ?? \App\Models\ClassifiedCategory::forceCreate(['name_en' => 'C', 'name_ar' => 'c', 'slug' => 'c-'.Str::random(5), 'is_active' => true])->id;
        $listing = ClassifiedListing::forceCreate([
            'listing_number' => 'T-'.Str::random(6), 'slug' => 't-'.Str::random(8),
            'seller_type' => Marketer::class, 'seller_id' => $this->s->marketer->id,
            'classified_category_id' => $cat, 'country_id' => $this->s->marketer->country_id, 'title_ar' => 't', 'title_en' => 't',
            'price' => 1000, 'currency' => 'AED', 'status' => 'active', 'listing_purpose' => 'sale',
        ]);
        $inq = ClassifiedInquiry::forceCreate([
            'classified_listing_id' => $listing->id, 'customer_id' => $this->s->customer->id,
            'message' => 'hi', 'contact_phone' => '+971500000000', 'status' => 'new',
        ]);
        $conv = MarketerConversation::forceCreate([
            'marketer_id' => $this->s->marketer->id, 'customer_id' => $this->s->customer->id,
            'classified_listing_id' => $listing->id, 'classified_inquiry_id' => $inq->id,
        ]);

        $this->actingAs($theirs, 'marketer_api')->getJson("/api/marketer/classified-inquiries/{$inq->id}")->assertNotFound();
        $this->actingAs($theirs, 'marketer_api')->patchJson("/api/marketer/classified-inquiries/{$inq->id}/close")->assertNotFound();
        $this->actingAs($theirs, 'marketer_api')->getJson("/api/marketer/conversations/{$conv->id}")->assertNotFound();
        $this->actingAs($theirs, 'marketer_api')->postJson("/api/marketer/conversations/{$conv->id}/messages", ['body' => 'x'])->assertNotFound();
        $this->actingAs($theirs, 'marketer_api')->getJson('/api/marketer/classified-listings/'.$listing->id)->assertNotFound();
        $this->actingAs($theirs, 'marketer_api')->getJson('/api/marketer/classified-inquiries')
            ->assertOk()->assertJsonCount(0, 'data.data');

        $this->actingAs($mine, 'marketer_api')->getJson("/api/marketer/classified-inquiries/{$inq->id}")
            ->assertOk()->assertJsonPath('success', true);
        $this->actingAs($mine, 'marketer_api')->getJson("/api/marketer/conversations/{$conv->id}")->assertOk();
    }
}
