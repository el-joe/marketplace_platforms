<?php

namespace Tests\Feature\Marketer;

use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Models\MarketerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class MarketerProfileAdPriceTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        \App\Models\Currency::query()->firstOrCreate(['code' => 'AED'], ['name' => 'Dirham', 'symbol' => 'AED', 'is_active' => true]);
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-' . Str::lower(Str::random(6))]);
    }

    private function mk(string $type, array $profile = [], array $m = []): MarketerAdmin
    {
        $marketer = Marketer::create(array_merge([
            'name' => 'M ' . Str::random(4), 'email' => Str::random(8) . '@example.test',
            'phone' => '+9715' . fake()->numerify('########'), 'marketer_type' => $type,
            'global_status' => 'active', 'country_id' => $this->s->country->id, 'approved_at' => now(),
        ], $m));
        MarketerProfile::create(array_merge(['marketer_id' => $marketer->id, 'profile_slug' => Str::lower(Str::random(8))], $profile));

        return MarketerAdmin::create([
            'marketer_id' => $marketer->id, 'name' => 'A', 'email' => Str::random(8) . '@example.test',
            'password' => Hash::make('password'), 'is_owner' => true, 'is_active' => true,
        ]);
    }

    private function url(string $path): string
    {
        return "/api/public/v1/{$this->s->country->site_code}/{$path}";
    }

    public function test_self_edit_blocked_when_flag_false(): void
    {
        $a = $this->mk('influencer', ['can_self_edit_ad_price' => false, 'ad_price' => 100]);
        $this->actingAs($a, 'marketer')->put(route('marketer.profile.ad-price.update'), ['ad_price' => 5])->assertForbidden();
        $this->assertSame(100, (int) $a->marketer->marketerProfile->fresh()->ad_price);
    }

    public function test_self_edit_validation_and_success(): void
    {
        $a = $this->mk('influencer', ['can_self_edit_ad_price' => true]);
        $r = route('marketer.profile.ad-price.update');
        $this->actingAs($a, 'marketer')->put($r, ['ad_price' => -1])->assertSessionHasErrors('ad_price');
        $this->actingAs($a, 'marketer')->put($r, ['ad_price' => 'abc'])->assertSessionHasErrors('ad_price');
        $this->actingAs($a, 'marketer')->put($r, ['ad_price' => 12.5])->assertSessionHasErrors('ad_price');
        $this->actingAs($a, 'marketer')->put($r, ['ad_price' => 5, 'ad_price_currency' => 'AEDX'])->assertSessionHasErrors('ad_price_currency');
        $this->actingAs($a, 'marketer')->put($r, ['ad_price' => 5, 'ad_price_currency' => 'ZZZ'])->assertSessionHasErrors('ad_price_currency');
        $this->actingAs($a, 'marketer')->put($r, ['ad_price' => 500, 'ad_price_currency' => 'AED'])->assertSessionHasNoErrors();
        $p = $a->marketer->marketerProfile->fresh();
        $this->assertSame(500, (int) $p->ad_price);
        $this->assertSame('AED', $p->ad_price_currency);
    }

    public function test_self_edit_requires_auth(): void
    {
        $this->put(route('marketer.profile.ad-price.update'), ['ad_price' => 5])->assertRedirect();
    }

    public function test_public_list_shows_ad_price_and_hides_inactive(): void
    {
        $this->mk('influencer', ['ad_price' => 700, 'ad_price_currency' => 'AED'], ['name' => 'ActiveOne']);
        $this->mk('influencer', ['ad_price' => 1], ['name' => 'Suspended', 'global_status' => 'suspended']);
        $res = $this->getJson($this->url('marketers'))->assertOk();
        $names = collect($res->json('data.items'))->pluck('name');
        $this->assertTrue($names->contains('ActiveOne'));
        $this->assertFalse($names->contains('Suspended'));
        $this->assertSame(700, (int) collect($res->json('data.items'))->firstWhere('name', 'ActiveOne')['ad_price']);
    }

    public function test_profile_slug_404_and_inactive_hidden_and_no_leak(): void
    {
        $this->getJson($this->url('marketers/nope-nope'))->assertNotFound();

        $a = $this->mk('influencer', ['profile_slug' => 'gone-one'], ['global_status' => 'suspended']);
        $this->getJson($this->url('marketers/gone-one'))->assertNotFound();

        $this->mk('influencer', ['profile_slug' => 'ok-one', 'shirt_size' => 'XL']);
        $json = $this->getJson($this->url('marketers/ok-one')); $json->assertOk();
        $body = $json->getContent();
        $this->assertStringNotContainsString('"email"', $body);
        $this->assertStringNotContainsString('commission', strtolower($body));
        $json->assertJsonStructure(['data' => ['own_listings' => ['items'], 'campaign_listings' => ['items']]]);
    }
}
