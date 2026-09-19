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

class MarketerSpecialtyDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
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

    public function test_self_edit_saves_specialty_and_public_api_and_directory_expose_it(): void
    {
        $a = $this->mk('affiliate');
        $this->actingAs($a, 'marketer')->put(route('marketer.profile.update'), [
            'specialty_ar' => 'عقارات', 'specialty_en' => '<b>Real estate</b>',
        ])->assertSessionHasNoErrors();
        $p = $a->marketer->marketerProfile->fresh();
        $this->assertSame('عقارات', $p->specialty_ar);

        $this->getJson($this->url('directory/brokers'))->assertOk()
            ->assertJsonFragment(['specialty_ar' => 'عقارات']);
        $this->getJson($this->url('directory/influencers'))->assertOk()
            ->assertJsonMissing(['specialty_ar' => 'عقارات']);
        $this->getJson($this->url('marketers/' . $p->profile_slug))->assertOk()
            ->assertJsonFragment(['specialty_ar' => 'عقارات']);
    }

    public function test_specialty_length_validated(): void
    {
        $a = $this->mk('influencer');
        $this->actingAs($a, 'marketer')->put(route('marketer.profile.update'), ['specialty_en' => str_repeat('x', 151)])
            ->assertSessionHasErrors('specialty_en');
    }

    public function test_profile_view_escapes_specialty(): void
    {
        $a = $this->mk('influencer', ['specialty_en' => '<script>alert(1)</script>']);
        $this->actingAs($a, 'marketer')->get(route('marketer.profile'))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
