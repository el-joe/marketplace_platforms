<?php

namespace Tests\Feature\Portal;

use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\MarketerJob;
use App\Models\PortalContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_join_page_shows_only_the_three_sections(): void
    {
        $response = $this->get(route('portal.join'));

        $response->assertOk();
        $response->assertSee('بائع منتجات');
        $response->assertSee('شركات تخصصية ووسطاء');
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
        $response->assertSee('/images/join/companies.svg', false);
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

    public function test_classified_vendor_section_opens_the_companies_landing(): void
    {
        $response = $this->post(route('portal.join.select'), ['section' => 'classified_vendor']);

        $response->assertRedirect(route('portal.companies'));
        $response->assertSessionHas('join.selection.section', 'classified_vendor');
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

    public function test_companies_landing_lists_active_specializations_only(): void
    {
        $active = $this->activeClassifiedCategory();
        ClassifiedCategory::create([
            'name_ar' => 'غير متاح',
            'name_en' => 'Inactive',
            'slug' => 'inactive',
            'is_active' => false,
        ]);

        $response = $this->get(route('portal.companies'));

        $response->assertOk();
        // The Open Souq choice reveals the classified categories to pick from.
        $response->assertSee($active->name_ar);
        $response->assertDontSee('غير متاح');
        $response->assertSee('name="classified_category_id"', false);
        // The two ways into the classified side.
        $response->assertSee('وكالة سفر');
        $response->assertSee('السوق المفتوح');
    }

    public function test_companies_page_choice_screen_uses_its_own_continue_action(): void
    {
        $response = $this->get(route('portal.companies'));

        $response->assertOk();
        $response->assertSee(route('portal.companies.continue'), false);
        $response->assertSee('name="choice"', false);
        // No section navigation menu on the uniform choice screen.
        $response->assertDontSee(route('portal.companies').'#specializations', false);
    }

    public function test_companies_continue_stores_the_choice(): void
    {
        $category = $this->activeClassifiedCategory();

        $travel = $this->post(route('portal.companies.continue'), ['choice' => 'travel_agency']);
        // A travel-agency choice opens that section's introduction landing page.
        $travel->assertRedirect(route('portal.companies.travel-agency'));
        $travel->assertSessionHas('companies.selection.choice', 'travel_agency');

        $souq = $this->post(route('portal.companies.continue'), [
            'choice' => 'open_souq',
            'classified_category_id' => $category->id,
        ]);
        $souq->assertRedirect(route('portal.companies'));
        $souq->assertSessionHas('join_status');
        $souq->assertSessionHas('companies.selection.classified_category_id', $category->id);
    }

    public function test_open_souq_requires_a_specialization(): void
    {
        $response = $this->from(route('portal.companies'))->post(route('portal.companies.continue'), [
            'choice' => 'open_souq',
        ]);

        $response->assertSessionHasErrors('classified_category_id');
    }

    public function test_companies_page_inherits_the_home_page_country_without_a_picker(): void
    {
        $country = Country::factory()->create([
            'name_ar' => 'مصر',
            'name_en' => 'Egypt',
            'site_code' => 'eg',
            'iso_code_2' => 'EG',
            'is_active' => true,
            'is_launched' => true,
        ]);

        $response = $this->withSession(['locale' => 'ar', 'portal_country' => 'eg'])
            ->get(route('portal.companies'));

        $response->assertOk();
        $response->assertDontSee('companies-country', false);
        $response->assertSee('name="country_id" value="'.$country->id.'"', false);
    }

    public function test_classified_vendor_requires_specialization_and_entity_type(): void
    {
        $country = $this->activeCountry();

        $missing = $this->from(route('portal.companies'))->post(route('portal.join.continue'), [
            'country_id' => $country->id,
            'section' => 'classified_vendor',
        ]);
        $missing->assertSessionHasErrors(['classified_category_id', 'entity_type']);

        $category = $this->activeClassifiedCategory();
        $ok = $this->post(route('portal.join.continue'), [
            'country_id' => $country->id,
            'section' => 'classified_vendor',
            'classified_category_id' => $category->id,
            'entity_type' => 'broker',
        ]);
        $ok->assertRedirect(route('portal.companies'));
        $ok->assertSessionHas('join_status');
        $ok->assertSessionHas('join.selection.classified_category_id', $category->id);
        $ok->assertSessionHas('join.selection.entity_type', 'broker');
    }

    public function test_inactive_country_is_rejected(): void
    {
        $country = Country::factory()->create(['is_active' => false]);

        $response = $this->from(route('portal.companies'))->post(route('portal.join.continue'), [
            'country_id' => $country->id,
            'section' => 'classified_vendor',
            'entity_type' => 'company',
        ]);

        $response->assertSessionHasErrors('country_id');
    }

    // ── Marketers landing ─────────────────────────────────────────────────

    public function test_marketers_landing_lists_each_active_type_on_the_choice_screen(): void
    {
        $affiliate = $this->activeMarketerJob('affiliate');
        $influencer = $this->activeMarketerJob('influencer', 2);

        $response = $this->get(route('portal.marketers'));

        $response->assertOk();
        $response->assertSee($affiliate->name_ar);
        $response->assertSee($influencer->name_ar);
        $response->assertSee('name="marketer_type"', false);
        $response->assertSee(route('portal.marketers.continue'), false);
    }

    public function test_marketer_continue_hands_off_to_marketer_register(): void
    {
        $this->activeMarketerJob('affiliate');

        $response = $this->post(route('portal.marketers.continue'), ['marketer_type' => 'affiliate']);

        $response->assertRedirect(route('marketer.register', ['type' => 'affiliate']));
        $response->assertSessionHas('marketers.selection.marketer_type', 'affiliate');
    }

    public function test_marketer_type_must_be_an_active_job_key(): void
    {
        $country = $this->activeCountry();
        $this->activeMarketerJob('affiliate');

        $unknown = $this->from(route('portal.companies'))->post(route('portal.join.continue'), [
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

    public function test_companies_page_links_back_to_the_join_options(): void
    {
        $response = $this->get(route('portal.companies'));

        $response->assertOk();
        $response->assertSee('href="'.route('portal.join').'"', false);
    }

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
