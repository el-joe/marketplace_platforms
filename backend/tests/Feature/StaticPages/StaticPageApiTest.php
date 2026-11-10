<?php

namespace Tests\Feature\StaticPages;

use App\Models\Country;
use App\Models\FooterLink;
use App\Models\StaticPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticPageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Country::factory()->create(['site_code' => 'kw', 'iso_code_2' => 'KW', 'is_active' => true]);
    }

    public function test_storefront_page_returns_bilingual_payload(): void
    {
        $page = StaticPage::factory()->storefront()->inFooter(StaticPage::FOOTER_GROUP_POLICIES)->create([
            'slug' => 'return-policy',
            'title_en' => 'Return Policy',
            'title_ar' => 'سياسة الإرجاع',
            'content_en' => '<p>Return within 14 days.</p>',
            'content_ar' => '<p>الإرجاع خلال 14 يومًا.</p>',
            'seo_title_en' => 'Returns | Nawy',
            'seo_title_ar' => null,
            'seo_description_en' => 'How returns work.',
            'seo_description_ar' => 'كيف يتم الإرجاع.',
        ]);

        $this->getJson('/api/customer/v1/kw/static-pages/return-policy')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Success',
                'data' => [
                    'id' => $page->id,
                    'slug' => 'return-policy',
                    'title' => ['ar' => 'سياسة الإرجاع', 'en' => 'Return Policy'],
                    'content' => ['ar' => '<p>الإرجاع خلال 14 يومًا.</p>', 'en' => '<p>Return within 14 days.</p>'],
                    'seo' => [
                        'title' => ['ar' => null, 'en' => 'Returns | Nawy'],
                        'description' => ['ar' => 'كيف يتم الإرجاع.', 'en' => 'How returns work.'],
                    ],
                    'footer_group' => 'policies',
                    'updated_at' => $page->updated_at->toIso8601String(),
                ],
            ]);
    }

    public function test_page_for_both_sites_is_served(): void
    {
        StaticPage::factory()->create(['slug' => 'about-us']);

        $this->getJson('/api/customer/v1/kw/static-pages/about-us')->assertOk()->assertJsonPath('data.slug', 'about-us');
    }

    public function test_inactive_portal_only_and_missing_pages_are_not_found(): void
    {
        StaticPage::factory()->storefront()->inactive()->create(['slug' => 'inactive-page']);
        StaticPage::factory()->portal()->create(['slug' => 'seller-only']);

        foreach (['inactive-page', 'seller-only', 'missing-page'] as $slug) {
            $this->getJson("/api/customer/v1/kw/static-pages/{$slug}")
                ->assertNotFound()
                ->assertJson(['success' => false]);
        }
    }

    public function test_footer_payload_includes_storefront_static_pages_in_order(): void
    {
        StaticPage::factory()->storefront()->inFooter(StaticPage::FOOTER_GROUP_POLICIES, 2)->create(['slug' => 'privacy-policy', 'title_en' => 'Privacy Policy', 'title_ar' => 'سياسة الخصوصية']);
        StaticPage::factory()->inFooter(StaticPage::FOOTER_GROUP_COMPANY, 1)->create(['slug' => 'about-us']);
        StaticPage::factory()->portal()->inFooter()->create(['slug' => 'seller-terms']);
        StaticPage::factory()->storefront()->create(['slug' => 'not-in-footer']);
        StaticPage::factory()->storefront()->inFooter()->inactive()->create(['slug' => 'inactive-page']);

        $response = $this->getJson(route('public.footer'))->assertOk();

        $this->assertSame(['about-us', 'privacy-policy'], array_column($response->json('data.static_pages'), 'slug'));
        $response->assertJsonPath('data.static_pages.1', [
            'id' => StaticPage::where('slug', 'privacy-policy')->value('id'),
            'slug' => 'privacy-policy',
            'title' => ['ar' => 'سياسة الخصوصية', 'en' => 'Privacy Policy'],
            'footer_group' => 'policies',
            'sort_order' => 2,
            'link' => '/pages/privacy-policy',
        ]);
    }

    public function test_footer_payload_reflects_page_changes_after_cache_warmup(): void
    {
        $this->getJson(route('public.footer'))->assertJsonPath('data.static_pages', []);

        StaticPage::factory()->storefront()->inFooter()->create(['slug' => 'shipping-policy']);

        $this->getJson(route('public.footer'))->assertJsonPath('data.static_pages.0.slug', 'shipping-policy');
    }

    public function test_footer_payload_drops_placeholder_bottom_nav_links(): void
    {
        FooterLink::create(['group' => 'bottom_nav', 'label_en' => 'Privacy Policy', 'url' => 'https://example.com/privacy-policy', 'is_active' => true, 'sort_order' => 1]);
        FooterLink::create(['group' => 'bottom_nav', 'label_en' => 'Blog', 'url' => 'https://blog.nawy.test', 'is_active' => true, 'sort_order' => 2]);

        $response = $this->getJson(route('public.footer'))->assertOk();

        $this->assertSame(['Blog'], array_column(array_column($response->json('data.bottom_nav_links'), 'label'), 'en'));
    }
}
