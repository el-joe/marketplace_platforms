<?php

namespace Tests\Feature\Portal;

use App\Models\Country;
use App\Models\PortalContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Stevebauman\Location\Facades\Location;
use Stevebauman\Location\Position;
use Tests\TestCase;

/**
 * Regression coverage for "the next portal page shows another country":
 * the selected / detected / default country must be the same everywhere
 * (header, content, links) and survive Continue, direct links, the country
 * switcher and the language switcher.
 */
class CountryPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function country(string $code, string $iso, bool $launched = true): Country
    {
        return Country::factory()->create([
            'site_code' => $code,
            'iso_code_2' => $iso,
            'name_en' => strtoupper($code).' Land',
            'is_active' => true,
            'is_launched' => $launched,
        ]);
    }

    /**
     * Oman is not launched (as in production), Kuwait and UAE are.
     */
    private function seedCountries(): void
    {
        $this->country('omn', 'OM', launched: false);
        $this->country('kwt', 'KW');
        $this->country('uae', 'AE');

        foreach (['omn' => 'Oman', 'kwt' => 'Kuwait', 'uae' => 'Emirates'] as $code => $label) {
            PortalContent::updateOrCreate(
                ['country_code' => $code, 'page_key' => 'home', 'block_key' => 'meta', 'field_key' => 'title'],
                ['type' => 'text', 'value_en' => "Sell in {$label} details", 'value_ar' => "{$label} ar", 'is_active' => true],
            );
        }
    }

    private function position(string $countryCode): Position
    {
        $position = new Position;
        $position->countryCode = $countryCode;

        return $position;
    }

    /**
     * Absolute URL on the portal host for a path (the test client defaults to another host).
     */
    private function portalUrl(string $path): string
    {
        return rtrim(route('portal.join'), '/join').$path;
    }

    private function vendorsPage()
    {
        return $this->withSession(['locale' => 'en'])->get(route('portal.vendors'));
    }

    // ── Detection ─────────────────────────────────────────────────────────

    public function test_accept_language_with_region_selects_that_country_without_a_session(): void
    {
        $this->seedCountries();

        $this->withHeader('Accept-Language', 'ar-OM,ar;q=0.9,en;q=0.8')
            ->get(route('portal.join'))
            ->assertOk();

        $this->assertSame('omn', session('portal_country'));
    }

    public function test_accept_language_region_is_found_beyond_the_first_tag(): void
    {
        $this->seedCountries();

        $this->withHeader('Accept-Language', 'ar,en-US;q=0.9,ar-KW;q=0.8')
            ->get(route('portal.faq'))
            ->assertOk();

        $this->assertSame('kwt', session('portal_country'));
    }

    public function test_cloudflare_country_header_selects_the_country_even_before_launch(): void
    {
        $this->seedCountries();

        $this->withHeader('CF-IPCountry', 'OM')->get(route('portal.faq'))->assertOk();

        $this->assertSame('omn', session('portal_country'));
    }

    public function test_default_country_is_stored_and_header_and_content_agree(): void
    {
        $this->seedCountries();

        $response = $this->vendorsPage()->assertOk();

        $this->assertSame('omn', session('portal_country'));
        $response->assertSee('Sell in Oman details');
        $this->get(route('portal.join'))->assertSee('flags/om.svg', false);
    }

    public function test_default_country_is_the_same_notion_as_the_switcher(): void
    {
        $this->country('kwt', 'KW');
        $this->country('uae', 'AE');
        $this->country('xxx', 'XX', launched: false);

        $this->assertSame('kwt', Country::defaultSiteCode());
        $this->assertTrue(Country::isPortalSelectable('uae'));
        $this->assertFalse(Country::isPortalSelectable('xxx'));
    }

    public function test_failed_geo_lookup_is_cached_only_briefly(): void
    {
        $this->seedCountries();
        Location::shouldReceive('get')->with('8.8.8.8')->twice()->andReturn(false, $this->position('KW'));

        $request = request();
        $this->assertNull(Country::resolveFromIp('8.8.8.8', $request));
        $this->assertSame('', Cache::get('geo:country:8.8.8.8'));

        $this->travel(6)->minutes();

        $this->assertSame('kwt', Country::resolveFromIp('8.8.8.8', $request));
        $this->assertSame('KW', Cache::get('geo:country:8.8.8.8'));
    }

    public function test_forwarded_for_header_from_an_untrusted_client_is_ignored(): void
    {
        $this->seedCountries();
        Location::shouldReceive('get')->with('8.8.4.4')->once()->andReturn($this->position('KW'));

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.4.4'])
            ->withHeader('X-Forwarded-For', '1.1.1.1')
            ->get(route('portal.faq'))
            ->assertOk();

        $this->assertSame('kwt', session('portal_country'));
    }

    // ── The reported scenario: select country, Continue, next page ───────

    /**
     * @return array<string, array{string, string}>
     */
    public static function nonDefaultCountries(): array
    {
        return [
            'kuwait' => ['kwt', 'Sell in Kuwait details'],
            'emirates' => ['uae', 'Sell in Emirates details'],
            'oman (not launched)' => ['omn', 'Sell in Oman details'],
        ];
    }

    #[DataProvider('nonDefaultCountries')]
    public function test_selected_country_is_rendered_on_the_page_after_continue(string $code, string $expected): void
    {
        $this->seedCountries();

        $this->withSession(['locale' => 'en'])->get(route('portal.country', $code))->assertRedirect();

        $this->get(route('portal.join'))->assertOk();
        $this->assertSame($code, session('portal_country'));

        $next = $this->post(route('portal.join.select'), ['section' => 'product_vendor', 'country' => $code]);
        $next->assertRedirect(route('portal.vendors'));

        $this->get(route('portal.vendors'))
            ->assertOk()
            ->assertSee($expected)
            ->assertDontSee('Sell in '.($code === 'kwt' ? 'Emirates' : 'Kuwait').' details');
    }

    public function test_continue_with_the_header_country_overrides_a_stale_session(): void
    {
        $this->seedCountries();

        $this->withSession(['locale' => 'en', 'portal_country' => 'uae'])
            ->post(route('portal.join.select'), ['section' => 'product_vendor', 'country' => 'kwt'])
            ->assertRedirect(route('portal.vendors'));

        $this->assertSame('kwt', session('portal_country'));
        $this->get(route('portal.vendors'))->assertSee('Sell in Kuwait details');
    }

    public function test_join_form_posts_the_displayed_country(): void
    {
        $this->seedCountries();

        $this->withSession(['portal_country' => 'kwt'])
            ->get(route('portal.join'))
            ->assertSee('name="country" value="kwt"', false);
    }

    public function test_registration_wizard_preselects_the_portal_country(): void
    {
        $this->seedCountries();
        $kuwait = Country::where('site_code', 'kwt')->first();

        $this->withSession(['portal_country' => 'kwt'])
            ->get(route('portal.register'))
            ->assertOk()
            ->assertSee('&quot;country_id&quot;:&quot;'.$kuwait->id.'&quot;', false);
    }

    // ── Direct links, switching, language ────────────────────────────────

    public function test_direct_link_with_country_segment_sets_the_country_for_later_pages(): void
    {
        $this->seedCountries();

        $this->get(route('portal.sellers', 'kwt'))->assertOk();
        $this->assertSame('kwt', session('portal_country'));

        $this->vendorsPage()->assertSee('Sell in Kuwait details');
    }

    public function test_url_segment_wins_over_the_session(): void
    {
        $this->seedCountries();

        $this->withSession(['portal_country' => 'uae'])
            ->get(route('portal.helpcenter.index', 'kwt'))
            ->assertOk();

        $this->assertSame('kwt', session('portal_country'));
    }

    public function test_switching_country_on_a_later_page_changes_the_following_pages(): void
    {
        $this->seedCountries();

        $this->get(route('portal.sellers', 'uae'))->assertOk();
        $this->vendorsPage()->assertSee('Sell in Emirates details');

        $this->from(route('portal.sellers', 'uae'))
            ->get(route('portal.country', 'kwt'))
            ->assertRedirect(route('portal.sellers', 'kwt'));

        $this->vendorsPage()->assertSee('Sell in Kuwait details');
    }

    public function test_language_switch_keeps_the_country(): void
    {
        $this->seedCountries();

        $this->withSession(['portal_country' => 'kwt', 'locale' => 'en'])
            ->from(route('portal.helpcenter.index', 'kwt'))
            ->get(route('portal.language', 'ar'))
            ->assertRedirect(route('portal.helpcenter.index', 'kwt').'#');

        $this->assertSame('kwt', session('portal_country'));
        $this->assertSame('ar', session('locale'));

        $this->get(route('portal.vendors'))->assertSee('Kuwait ar');
    }

    public function test_unprefixed_advertise_links_redirect_to_the_selected_country(): void
    {
        $this->seedCountries();

        $this->withSession(['portal_country' => 'kwt'])
            ->get($this->portalUrl('/advertise/sellers'))
            ->assertRedirect(route('portal.sellers', 'kwt'));
    }

    // ── Validation ────────────────────────────────────────────────────────

    public function test_unknown_country_in_switcher_is_rejected(): void
    {
        $this->seedCountries();

        $this->withSession(['portal_country' => 'kwt'])
            ->get(route('portal.country', 'zzz'))
            ->assertNotFound();

        $this->assertSame('kwt', session('portal_country'));
    }

    public function test_active_but_unselectable_country_is_rejected_by_the_switcher(): void
    {
        $this->seedCountries();
        $this->country('qat', 'QA', launched: false);

        $this->get(route('portal.country', 'qat'))->assertNotFound();
        $this->assertNotSame('qat', session('portal_country'));
    }

    public function test_join_select_rejects_an_invalid_country_and_does_not_redirect_onward(): void
    {
        $this->seedCountries();
        $this->country('qat', 'QA', launched: false);

        foreach (['zzz', 'qat'] as $bad) {
            $this->withSession(['portal_country' => 'kwt'])
                ->from(route('portal.join'))
                ->post(route('portal.join.select'), ['section' => 'product_vendor', 'country' => $bad])
                ->assertRedirect(route('portal.join'))
                ->assertSessionHasErrors('country');

            $this->assertSame('kwt', session('portal_country'));
        }
    }

    public function test_unknown_country_segment_redirects_to_the_visitors_country(): void
    {
        $this->seedCountries();

        $this->withSession(['portal_country' => 'kwt'])
            ->get($this->portalUrl('/zzz/advertise/sellers?ref=1'))
            ->assertRedirect(route('portal.sellers', 'kwt').'?ref=1');
    }

    public function test_unknown_country_segment_on_a_post_is_not_found(): void
    {
        $this->seedCountries();

        $this->post($this->portalUrl('/zzz/advertise/request'), [])->assertNotFound();
    }
}
