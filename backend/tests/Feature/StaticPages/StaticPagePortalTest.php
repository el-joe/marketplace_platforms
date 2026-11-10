<?php

namespace Tests\Feature\StaticPages;

use App\Models\StaticPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticPagePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_portal_page_renders_localized_content_and_seo(): void
    {
        StaticPage::factory()->portal()->create([
            'slug' => 'seller-terms',
            'title_en' => 'Seller Terms',
            'title_ar' => 'شروط البائع',
            'content_en' => '<p>English body</p>',
            'content_ar' => '<p>المحتوى العربي</p>',
            'seo_title_ar' => 'شروط البائع على ناوي',
            'seo_description_ar' => 'وصف الصفحة',
        ]);

        $this->withSession(['locale' => 'ar'])
            ->get(route('portal.pages.show', 'seller-terms'))
            ->assertOk()
            ->assertSee('<title>شروط البائع على ناوي |', false)
            ->assertSee('<meta name="description" content="وصف الصفحة">', false)
            ->assertSee('<p>المحتوى العربي</p>', false)
            ->assertDontSee('English body');

        $this->withSession(['locale' => 'en'])
            ->get(route('portal.pages.show', 'seller-terms'))
            ->assertOk()
            ->assertSee('<p>English body</p>', false);
    }

    public function test_page_for_both_sites_renders_on_portal(): void
    {
        StaticPage::factory()->create(['slug' => 'about-us', 'audience' => StaticPage::AUDIENCE_BOTH]);

        $this->get(route('portal.pages.show', 'about-us'))->assertOk();
    }

    public function test_inactive_storefront_only_and_deleted_pages_are_not_found(): void
    {
        StaticPage::factory()->portal()->inactive()->create(['slug' => 'inactive-page']);
        StaticPage::factory()->storefront()->create(['slug' => 'shopper-only']);
        StaticPage::factory()->portal()->create(['slug' => 'deleted-page'])->delete();

        $this->get(route('portal.pages.show', 'inactive-page'))->assertNotFound();
        $this->get(route('portal.pages.show', 'shopper-only'))->assertNotFound();
        $this->get(route('portal.pages.show', 'deleted-page'))->assertNotFound();
        $this->get(route('portal.pages.show', 'missing-page'))->assertNotFound();
    }

    public function test_portal_footer_lists_grouped_portal_footer_pages(): void
    {
        StaticPage::factory()->portal()->inFooter(StaticPage::FOOTER_GROUP_COMPANY)->create(['slug' => 'about-us', 'title_ar' => 'من نحن']);
        StaticPage::factory()->inFooter(StaticPage::FOOTER_GROUP_POLICIES)->create(['slug' => 'privacy-policy', 'title_ar' => 'سياسة الخصوصية']);
        StaticPage::factory()->storefront()->inFooter()->create(['slug' => 'return-policy', 'title_ar' => 'سياسة الإرجاع']);
        StaticPage::factory()->portal()->create(['slug' => 'not-in-footer', 'title_ar' => 'ليست في التذييل']);

        $response = $this->withSession(['locale' => 'ar'])->get(route('portal.pages.show', 'not-in-footer'))->assertOk();

        $response->assertSee(route('portal.pages.show', 'about-us'), false)
            ->assertSee(route('portal.pages.show', 'privacy-policy'), false)
            ->assertDontSee(route('portal.pages.show', 'return-policy'), false)
            ->assertSeeInOrder(['data-portal-footer-links', 'الشركة', 'من نحن', 'السياسات', 'سياسة الخصوصية'], false);
    }
}
