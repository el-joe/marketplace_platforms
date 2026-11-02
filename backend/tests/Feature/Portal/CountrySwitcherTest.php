<?php

namespace Tests\Feature\Portal;

use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountrySwitcherTest extends TestCase
{
    use RefreshDatabase;

    private function launched(string $code, string $iso): Country
    {
        return Country::factory()->create([
            'site_code' => $code,
            'iso_code_2' => $iso,
            'is_active' => true,
            'is_launched' => true,
        ]);
    }

    public function test_switcher_renders_on_every_portal_header_type(): void
    {
        $this->launched('uae', 'AE');
        $this->launched('kwt', 'KW');

        foreach ([
            route('portal.faq'),
            route('portal.join'),
            route('portal.marketers'),
            route('portal.sellers', 'uae'),
            route('portal.adsupport.index', 'uae'),
            route('portal.helpcenter.index', 'uae'),
        ] as $url) {
            $this->withSession(['locale' => 'en'])->get($url)->assertOk()
                ->assertSee('data-portal-section="country-switcher"', false)
                ->assertSee(route('portal.country', 'kwt'), false);
        }
    }

    public function test_switching_country_on_a_country_prefixed_page_rewrites_the_url_segment(): void
    {
        $this->launched('uae', 'AE');
        $this->launched('kwt', 'KW');

        $this->from(route('portal.helpcenter.index', 'uae').'?q=1')
            ->get(route('portal.country', 'kwt'))
            ->assertRedirect(route('portal.helpcenter.index', 'kwt').'?q=1');

        $this->assertSame('kwt', session('portal_country'));
    }

    public function test_switching_country_on_an_unprefixed_page_returns_to_it(): void
    {
        $this->launched('uae', 'AE');
        $this->launched('kwt', 'KW');

        $this->from(route('portal.faq'))
            ->get(route('portal.country', 'kwt'))
            ->assertRedirect(route('portal.faq'));
    }

    public function test_country_is_auto_detected_on_first_visit_and_remembered(): void
    {
        $this->launched('uae', 'AE');
        $this->launched('kwt', 'KW');

        $this->withHeader('CF-IPCountry', 'KW')
            ->get(route('portal.faq'))
            ->assertOk();

        $this->assertSame('kwt', session('portal_country'));
    }

    public function test_manual_pick_is_not_overridden_by_detection(): void
    {
        $this->launched('uae', 'AE');
        $this->launched('kwt', 'KW');

        $this->withSession(['portal_country' => 'uae'])
            ->withHeader('CF-IPCountry', 'KW')
            ->get(route('portal.faq'))
            ->assertOk();

        $this->assertSame('uae', session('portal_country'));
    }
}
