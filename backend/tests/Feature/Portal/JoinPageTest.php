<?php

namespace Tests\Feature\Portal;

use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\MarketerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JoinPageTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_join_page_renders_live_countries_and_sections(): void
    {
        $country = $this->activeCountry();

        $response = $this->get(route('portal.join'));

        $response->assertOk();
        $response->assertSee($country->name_ar);
        $response->assertSee('بائع منتجات');
        $response->assertSee('شركات تخصصية ووسطاء');
        $response->assertSee('مشاهير ومؤثرون ومسوقون بالعمولة');
    }

    public function test_product_vendor_is_routed_to_existing_register_wizard(): void
    {
        $country = $this->activeCountry();

        $response = $this->post(route('portal.join.continue'), [
            'country_id' => $country->id,
            'section' => 'product_vendor',
        ]);

        $response->assertRedirect(route('portal.register'));
        $response->assertSessionHas('join.selection.section', 'product_vendor');
        $response->assertSessionHas('reg_data.step1.country_id', $country->id);
    }

    public function test_marketer_is_routed_to_marketer_register_with_type_and_country(): void
    {
        $country = $this->activeCountry();
        $this->activeMarketerJob('affiliate');

        $response = $this->post(route('portal.join.continue'), [
            'country_id' => $country->id,
            'section' => 'marketer',
            'marketer_type' => 'affiliate',
        ]);

        $response->assertRedirect(route('marketer.register', [
            'type' => 'affiliate',
            'country_id' => $country->id,
        ]));
    }

    public function test_invalid_section_is_rejected_server_side(): void
    {
        $country = $this->activeCountry();

        $response = $this->from(route('portal.join'))->post(route('portal.join.continue'), [
            'country_id' => $country->id,
            'section' => 'not_a_section',
        ]);

        $response->assertRedirect(route('portal.join'));
        $response->assertSessionHasErrors('section');
    }

    public function test_inactive_country_is_rejected(): void
    {
        $country = Country::factory()->create(['is_active' => false]);

        $response = $this->from(route('portal.join'))->post(route('portal.join.continue'), [
            'country_id' => $country->id,
            'section' => 'product_vendor',
        ]);

        $response->assertSessionHasErrors('country_id');
    }

    public function test_classified_vendor_requires_an_active_specialization(): void
    {
        $country = $this->activeCountry();

        $missing = $this->from(route('portal.join'))->post(route('portal.join.continue'), [
            'country_id' => $country->id,
            'section' => 'classified_vendor',
        ]);
        $missing->assertSessionHasErrors('classified_category_id');

        $inactive = ClassifiedCategory::create([
            'name_ar' => 'غير متاح',
            'name_en' => 'Inactive',
            'slug' => 'inactive',
            'is_active' => false,
        ]);
        $rejected = $this->from(route('portal.join'))->post(route('portal.join.continue'), [
            'country_id' => $country->id,
            'section' => 'classified_vendor',
            'classified_category_id' => $inactive->id,
        ]);
        $rejected->assertSessionHasErrors('classified_category_id');
    }

    public function test_classified_vendor_is_held_until_its_flow_is_ready(): void
    {
        $country = $this->activeCountry();
        $category = $this->activeClassifiedCategory();

        $response = $this->post(route('portal.join.continue'), [
            'country_id' => $country->id,
            'section' => 'classified_vendor',
            'classified_category_id' => $category->id,
        ]);

        $response->assertRedirect(route('portal.join'));
        $response->assertSessionHas('join_status');
        $response->assertSessionHas('join.selection.classified_category_id', $category->id);
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

        // Valid enum value, but no active marketer_jobs row backs it.
        $inactiveJob = $this->from(route('portal.join'))->post(route('portal.join.continue'), [
            'country_id' => $country->id,
            'section' => 'marketer',
            'marketer_type' => 'broker',
        ]);
        $inactiveJob->assertSessionHasErrors('marketer_type');
    }

    public function test_options_endpoint_returns_marketer_types_and_rejects_inactive_country(): void
    {
        $country = $this->activeCountry();
        $this->activeMarketerJob('influencer');

        $response = $this->getJson(route('portal.join.options', ['country_id' => $country->id]));
        $response->assertOk()->assertJsonPath('success', true);
        $this->assertArrayHasKey('influencer', $response->json('marketer_types'));

        $inactive = Country::factory()->create(['is_active' => false]);
        $this->getJson(route('portal.join.options', ['country_id' => $inactive->id]))
            ->assertNotFound();
    }

    public function test_existing_register_route_still_works(): void
    {
        $this->get(route('portal.register'))->assertOk();
    }
}
