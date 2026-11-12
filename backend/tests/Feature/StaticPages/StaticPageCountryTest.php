<?php

namespace Tests\Feature\StaticPages;

use App\Models\Admin;
use App\Models\Country;
use App\Models\StaticPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StaticPageCountryTest extends TestCase
{
    use RefreshDatabase;

    private Country $oman;

    private Country $kuwait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oman = Country::factory()->create(['site_code' => 'omn', 'iso_code_2' => 'OM', 'is_active' => true]);
        $this->kuwait = Country::factory()->create(['site_code' => 'kw', 'iso_code_2' => 'KW', 'is_active' => true]);
    }

    public function test_country_page_overrides_global_page_with_same_slug(): void
    {
        StaticPage::factory()->storefront()->create(['slug' => 'terms-and-conditions', 'title_en' => 'Global Terms']);
        StaticPage::factory()->storefront()->forCountry($this->oman)->create(['slug' => 'terms-and-conditions', 'title_en' => 'Oman Terms']);

        $this->getJson('/api/customer/v1/omn/static-pages/terms-and-conditions')
            ->assertOk()->assertJsonPath('data.title.en', 'Oman Terms');
        $this->getJson('/api/customer/v1/kw/static-pages/terms-and-conditions')
            ->assertOk()->assertJsonPath('data.title.en', 'Global Terms');
    }

    public function test_inactive_country_page_falls_back_to_global(): void
    {
        StaticPage::factory()->storefront()->inFooter()->create(['slug' => 'refund-policy', 'title_en' => 'Global Refunds']);
        StaticPage::factory()->storefront()->inFooter()->inactive()->forCountry($this->oman)->create(['slug' => 'refund-policy', 'title_en' => 'Oman Refunds']);

        $this->getJson('/api/customer/v1/omn/static-pages/refund-policy')
            ->assertOk()->assertJsonPath('data.title.en', 'Global Refunds');
        $this->assertSame(['Global Refunds'], array_column(
            array_column($this->getJson(route('public.country.footer', 'omn'))->json('data.static_pages'), 'title'),
            'en',
        ));
    }

    public function test_country_only_page_is_hidden_from_other_countries(): void
    {
        StaticPage::factory()->storefront()->forCountry($this->oman)->create(['slug' => 'oman-delivery']);

        $this->getJson('/api/customer/v1/omn/static-pages/oman-delivery')->assertOk();
        $this->getJson('/api/customer/v1/kw/static-pages/oman-delivery')->assertNotFound();
    }

    public function test_country_footer_lists_own_pages_and_non_overridden_global_pages(): void
    {
        StaticPage::factory()->storefront()->inFooter(StaticPage::FOOTER_GROUP_POLICIES, 1)->create(['slug' => 'terms-and-conditions', 'title_en' => 'Global Terms']);
        StaticPage::factory()->storefront()->inFooter(StaticPage::FOOTER_GROUP_HELP, 2)->create(['slug' => 'contact-us', 'title_en' => 'Global Contact']);
        StaticPage::factory()->storefront()->inFooter(StaticPage::FOOTER_GROUP_POLICIES, 1)->forCountry($this->oman)->create(['slug' => 'terms-and-conditions', 'title_en' => 'Oman Terms']);
        StaticPage::factory()->storefront()->inFooter(StaticPage::FOOTER_GROUP_POLICIES, 3)->forCountry($this->kuwait)->create(['slug' => 'kuwait-only', 'title_en' => 'Kuwait Only']);

        $titles = fn (string $code) => array_column(array_column(
            $this->getJson(route('public.country.footer', $code))->assertOk()->json('data.static_pages'),
            'title',
        ), 'en');

        $this->assertSame(['Oman Terms', 'Global Contact'], $titles('omn'));
        $this->assertSame(['Global Terms', 'Global Contact', 'Kuwait Only'], $titles('kw'));
        $this->assertSame(['Global Terms', 'Global Contact'], array_column(array_column(
            $this->getJson(route('public.footer'))->json('data.static_pages'),
            'title',
        ), 'en'));
    }

    public function test_admin_slug_is_unique_per_country(): void
    {
        StaticPage::factory()->create(['slug' => 'terms-and-conditions']);
        $admin = Admin::factory()->create();
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        foreach (['static_pages.view', 'static_pages.create'] as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']));
        }
        $payload = ['title_en' => 'Terms', 'title_ar' => 'الشروط', 'slug' => 'terms-and-conditions', 'audience' => 'both', 'is_active' => 1];

        $this->actingAs($admin, 'admin')->post(route('admin.static-pages.store'), $payload)
            ->assertSessionHasErrors('slug');

        $this->actingAs($admin, 'admin')->post(route('admin.static-pages.store'), $payload + ['country_id' => $this->oman->id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('static_pages', ['slug' => 'terms-and-conditions', 'country_id' => $this->oman->id]);
    }
}
