<?php

namespace Tests\Feature\Portal;

use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\MarketerJob;
use App\Models\PortalContent;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class JoinPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The portal is Arabic-first; render copy and labels in Arabic.
        $this->withSession(['locale' => 'ar']);
    }

    private function activeCountry(): Country
    {
        return Country::factory()->create(['is_active' => true]);
    }

    private function activeClassifiedCategory(): ClassifiedCategory
    {
        return ClassifiedCategory::create([
            'name_ar' => 'عقارات',
            'name_en' => 'Real Estate',
            'slug' => 'real-estate',
            'is_active' => true,
        ]);
    }

    private function activeMarketerJob(string $key, int $sort = 1): MarketerJob
    {
        return MarketerJob::create([
            'key' => $key,
            'name_ar' => $key,
            'name_en' => ucfirst($key),
            'is_active' => true,
            'sort_order' => $sort,
        ]);
    }

    // ── First screen: section choice only ─────────────────────────────────

    public function test_join_page_shows_all_four_sections(): void
    {
        $response = $this->get(route('portal.join'));

        $response->assertOk();
        $response->assertSee('بائع منتجات');
        $response->assertSee('السوق المفتوح');
        $response->assertSee('وكالة سفر');
        $response->assertSee('مشاهير ومؤثرون ومسوقون بالعمولة');

        // No country or specialization pickers on the first screen.
        $response->assertDontSee('name="country_id"', false);
        $response->assertDontSee('name="classified_category_id"', false);
    }

    public function test_join_page_renders_admin_managed_card_images_and_continue_action(): void
    {
        PortalContent::where('page_key', 'join')
            ->where('block_key', 'card_product_vendor')
            ->where('field_key', 'image')
            ->update(['value_url' => '/images/join/custom-seller.svg']);
        PortalContent::flush('join');

        $response = $this->get(route('portal.join'));

        $response->assertOk();
        // Admin-edited image flows straight to the card.
        $response->assertSee('/images/join/custom-seller.svg', false);
        $response->assertSee('/images/join/open-souq.jpg', false);
        $response->assertSee('/images/join/marketer.svg', false);
        // Selection is client-side; the Continue button submits the choice once.
        $response->assertSee(route('portal.join.select'), false);
        $response->assertSee('name="section"', false);
        $response->assertSee('متابعة');
    }

    public function test_home_redirects_to_the_section_selection_page(): void
    {
        $this->get(route('portal.home'))->assertRedirect(route('portal.join'));
    }

    public function test_product_vendor_section_opens_the_legacy_seller_landing(): void
    {
        $response = $this->post(route('portal.join.select'), ['section' => 'product_vendor']);

        $response->assertRedirect(route('portal.vendors'));
        $response->assertSessionHas('join.selection.section', 'product_vendor');
    }

    public function test_open_souq_section_opens_its_landing(): void
    {
        $response = $this->post(route('portal.join.select'), ['section' => 'open_souq']);

        $response->assertRedirect(route('portal.companies.open-souq'));
        $response->assertSessionHas('join.selection.section', 'open_souq');
    }

    public function test_travel_agency_section_opens_its_landing(): void
    {
        $response = $this->post(route('portal.join.select'), ['section' => 'travel_agency']);

        $response->assertRedirect(route('portal.companies.travel-agency'));
    }

    public function test_admin_can_hide_a_join_option(): void
    {
        Setting::where('key', 'join_option_travel_agency_enabled')->firstOrFail()->update(['value' => false]);
        Cache::flush();

        $this->get(route('portal.join'))
            ->assertOk()
            ->assertDontSee('وكالة سفر')
            ->assertSee('السوق المفتوح');

        $this->from(route('portal.join'))
            ->post(route('portal.join.select'), ['section' => 'travel_agency'])
            ->assertRedirect(route('portal.join'))
            ->assertSessionHasErrors('section');
    }

    public function test_companies_url_redirects_to_join(): void
    {
        $this->get(route('portal.companies'))->assertRedirect(route('portal.join'))->assertStatus(301);
    }

    public function test_marketer_section_opens_the_marketers_landing(): void
    {
        $response = $this->post(route('portal.join.select'), ['section' => 'marketer']);

        $response->assertRedirect(route('portal.marketers'));
        $response->assertSessionHas('join.selection.section', 'marketer');
    }

    public function test_invalid_section_is_rejected_server_side(): void
    {
        $response = $this->from(route('portal.join'))->post(route('portal.join.select'), [
            'section' => 'not_a_section',
        ]);

        $response->assertRedirect(route('portal.join'));
        $response->assertSessionHasErrors('section');
    }

    // ── Product vendor landing ────────────────────────────────────────────

    public function test_vendors_landing_reuses_legacy_content_and_links_to_register(): void
    {
        $response = $this->get(route('portal.vendors'));

        $response->assertOk();
        $response->assertSee('بيع على ناوي');
        $response->assertSee(route('portal.register'), false);
    }

    // ── Companies & brokers landing ───────────────────────────────────────

    public function test_open_souq_landing_page_renders_and_links_to_vendor_registration(): void
    {
        $category = $this->activeClassifiedCategory();

        $response = $this->get(route('portal.companies.open-souq'));

        $response->assertOk();
        // Open Souq has no registration of its own yet: it hands off to /vendors/register.
        $response->assertSee(route('portal.register'), false);
        // The landing lists the classified categories that can be published.
        $response->assertSee($category->name_ar);
        // Its navbar belongs to the Open Souq section, not the travel-agency one.
        $response->assertSee('لماذا تبيع على السوق المفتوح');
        $response->assertDontSee('لماذا تبيع السفر');
    }

    public function test_join_continue_resolves_open_souq_to_its_landing(): void
    {
        $country = $this->activeCountry();

        $this->post(route('portal.join.continue'), ['country_id' => $country->id, 'section' => 'open_souq'])
            ->assertRedirect(route('portal.companies.open-souq'));
    }

    public function test_inactive_country_is_rejected(): void
    {
        $country = Country::factory()->create(['is_active' => false]);

        $response = $this->from(route('portal.join'))->post(route('portal.join.continue'), [
            'country_id' => $country->id,
            'section' => 'open_souq',
        ]);

        $response->assertSessionHasErrors('country_id');
    }

    // ── Marketers landing ─────────────────────────────────────────────────

    public function test_marketers_landing_presents_each_active_type_as_a_landing_page(): void
    {
        $affiliate = $this->activeMarketerJob('affiliate');
        $influencer = $this->activeMarketerJob('influencer', 2);

        $response = $this->get(route('portal.marketers'));

        $response->assertOk();
        $response->assertSee($affiliate->name_ar);
        $response->assertSee($influencer->name_ar);
        $response->assertSee(route('portal.marketers.type', 'affiliate'), false);
        $response->assertSee(route('portal.marketers.type', 'influencer'), false);
    }

    public function test_marketer_type_landing_hands_off_to_marketer_register(): void
    {
        $this->activeMarketerJob('affiliate');

        $response = $this->get(route('portal.marketers.type', 'affiliate'));

        $response->assertOk();
        $response->assertSee(route('marketer.register', ['type' => 'affiliate']), false);
    }

    public function test_unknown_marketer_type_landing_is_not_found(): void
    {
        $this->activeMarketerJob('affiliate');

        $this->get(route('portal.marketers.type', 'does_not_exist'))->assertNotFound();
    }

    public function test_marketer_type_must_be_an_active_job_key(): void
    {
        $country = $this->activeCountry();
        $this->activeMarketerJob('affiliate');

        $unknown = $this->from(route('portal.join'))->post(route('portal.join.continue'), [
            'country_id' => $country->id,
            'section' => 'marketer',
            'marketer_type' => 'does_not_exist',
        ]);
        $unknown->assertSessionHasErrors('marketer_type');
    }

    // ── Country auto-detection & switcher ────────────────────────────────

    public function test_join_page_offers_launched_countries_and_language_options(): void
    {
        Country::factory()->create([
            'name_ar' => 'الإمارات',
            'name_en' => 'United Arab Emirates',
            'site_code' => 'ae',
            'iso_code_2' => 'AE',
            'is_active' => true,
            'is_launched' => true,
        ]);
        Country::factory()->create([
            'name_ar' => 'غير مُطلقة',
            'name_en' => 'Not Launched',
            'site_code' => 'xx',
            'is_active' => true,
            'is_launched' => false,
        ]);

        $response = $this->get(route('portal.join'));

        $response->assertOk();
        $response->assertSee('الإمارات');
        $response->assertSee(route('portal.country', 'ae'), false);
        $response->assertSee(route('portal.language', 'en'), false);
        $response->assertSee(route('portal.language', 'ar'), false);
        $response->assertDontSee('تم تحديدها تلقائيًا');
        $response->assertDontSee('غير مُطلقة');
    }

    public function test_join_page_does_not_claim_auto_detection_after_a_manual_pick(): void
    {
        Country::factory()->create([
            'name_ar' => 'مصر',
            'name_en' => 'Egypt',
            'site_code' => 'eg',
            'iso_code_2' => 'EG',
            'is_active' => true,
            'is_launched' => true,
        ]);

        $response = $this->withSession(['locale' => 'ar', 'portal_country' => 'eg'])
            ->get(route('portal.join'));

        $response->assertOk();
        $response->assertSee('مصر');
        $response->assertDontSee('تم تحديدها تلقائيًا');
    }

    // ── Section-specific choice screens ──────────────────────────────────

    public function test_marketers_page_links_back_to_the_join_options(): void
    {
        $response = $this->get(route('portal.marketers'));

        $response->assertOk();
        $response->assertSee('href="'.route('portal.join').'"', false);
    }

    // ── Backward compatibility ────────────────────────────────────────────

    public function test_existing_register_route_still_works(): void
    {
        $this->get(route('portal.register'))->assertOk();
    }

    public function test_existing_marketer_register_route_still_works(): void
    {
        $this->get(route('marketer.register'))->assertOk();
    }

    public function test_marketer_register_shows_only_the_handed_over_type(): void
    {
        $preselected = $this->get(route('marketer.register', ['type' => 'broker']));

        $preselected->assertOk();
        $preselected->assertSee('name="marketer_type" value="broker"', false);
        $preselected->assertDontSee('name="marketer_type" value="influencer"', false);
        $preselected->assertDontSee('name="marketer_type" value="affiliate"', false);

        $all = $this->get(route('marketer.register'));

        $all->assertOk();
        $all->assertSee('name="marketer_type" value="influencer"', false);
        $all->assertSee('name="marketer_type" value="affiliate"', false);
        $all->assertSee('name="marketer_type" value="broker"', false);
    }
}
