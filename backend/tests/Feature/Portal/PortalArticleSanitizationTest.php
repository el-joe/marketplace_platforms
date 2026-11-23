<?php

namespace Tests\Feature\Portal;

use App\Models\Admin;
use App\Models\AdSupportArticle;
use App\Models\AdSupportCollection;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Country;
use App\Models\HelpCenterArticle;
use App\Models\HelpCenterCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Portal blog / help-center / ad-support article bodies are admin-authored HTML rendered with {!! !!}:
 * they are sanitized on save (admin controllers) and again on render (rows saved before the fix).
 */
class PortalArticleSanitizationTest extends TestCase
{
    use RefreshDatabase;

    private const UPLOADED_IMAGE = 'http://admin.noon.loc/storage/helpcenter/content/diagram-abc123.png';

    private const MALICIOUS_BODY = '<h2 id="h_fees" onclick="alert(1)">Fees</h2>'
        .'<script>alert("xss")</script>'
        .'<div class="hc-callout hc-callout-info absolute inset-0"><p>Note</p></div>'
        .'<p><img src="'.self::UPLOADED_IMAGE.'" onerror="alert(2)" alt="diagram"></p>'
        .'<h3 id="bad id&quot;><script>">Bad id</h3>'
        .'<p><a href="javascript:alert(3)">click</a></p>';

    private function contentAdmin(): Admin
    {
        foreach (['vendors.assigned_only', 'pages.view', 'pages.manage'] as $name) {
            Permission::findOrCreate($name, 'admin');
        }
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['pages.view', 'pages.manage']);

        return $admin;
    }

    private function country(): Country
    {
        return Country::factory()->create(['site_code' => 'kwt', 'iso_code_2' => 'KW', 'is_active' => true, 'is_launched' => true]);
    }

    private function helpCenterCategory(): HelpCenterCategory
    {
        return HelpCenterCategory::create(['name' => 'Fees', 'name_en' => 'Fees', 'name_ar' => 'الرسوم', 'slug' => 'fees', 'is_active' => true, 'sort_order' => 1]);
    }

    private function adSupportCollection(): AdSupportCollection
    {
        return AdSupportCollection::create(['name' => 'Ads', 'name_en' => 'Ads', 'name_ar' => 'الإعلانات', 'slug' => 'ads', 'is_active' => true, 'sort_order' => 1]);
    }

    private function blogCategory(): BlogCategory
    {
        return BlogCategory::create(['name_en' => 'News', 'name_ar' => 'أخبار', 'slug' => 'news', 'is_active' => true]);
    }

    private function assertBodyIsSafe(string $html): void
    {
        $this->assertStringNotContainsStringIgnoringCase('<script>alert', $html);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $html);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $html);
        $this->assertStringNotContainsStringIgnoringCase('javascript:alert', $html);
        $this->assertStringNotContainsString('absolute inset-0', $html);
    }

    private function assertBodyKeepsSafeContent(string $html): void
    {
        $this->assertStringContainsString('<h2 id="h_fees">Fees</h2>', $html);
        $this->assertStringContainsString('<h3>Bad id</h3>', $html);
        $this->assertStringContainsString('<div class="hc-callout hc-callout-info">', $html);
        $this->assertStringContainsString('<img src="'.self::UPLOADED_IMAGE.'" alt="diagram">', $html);
    }

    public function test_help_center_article_body_is_sanitized_on_save(): void
    {
        $this->actingAs($this->contentAdmin(), 'admin');

        $this->post(route('admin.helpcenter.articles.store'), [
            'help_center_category_id' => $this->helpCenterCategory()->id,
            'title_en' => 'Fees', 'title_ar' => 'الرسوم',
            'body' => self::MALICIOUS_BODY, 'body_en' => self::MALICIOUS_BODY, 'body_ar' => self::MALICIOUS_BODY,
            'action' => 'publish',
        ])->assertRedirect();

        $article = HelpCenterArticle::sole();
        foreach (['body', 'body_en', 'body_ar'] as $column) {
            $this->assertBodyIsSafe($article->getRawOriginal($column));
            $this->assertBodyKeepsSafeContent($article->getRawOriginal($column));
        }
    }

    public function test_ad_support_article_body_is_sanitized_on_update(): void
    {
        $this->actingAs($this->contentAdmin(), 'admin');
        $collection = $this->adSupportCollection();
        $article = AdSupportArticle::create([
            'ad_support_collection_id' => $collection->id, 'title' => 'Bids', 'title_en' => 'Bids', 'title_ar' => 'المزايدات',
            'slug' => 'bids', 'body' => '<p>old</p>', 'body_en' => '<p>old</p>', 'body_ar' => '<p>old</p>', 'status' => 'draft',
        ]);

        $this->put(route('admin.adsupport.articles.update', $article->id), [
            'ad_support_collection_id' => $collection->id,
            'title_en' => 'Bids', 'title_ar' => 'المزايدات', 'slug' => 'bids',
            'body' => self::MALICIOUS_BODY, 'body_en' => self::MALICIOUS_BODY, 'body_ar' => self::MALICIOUS_BODY,
        ])->assertRedirect();

        $article->refresh();
        $this->assertBodyIsSafe($article->body_en);
        $this->assertBodyIsSafe($article->body_ar);
        $this->assertBodyKeepsSafeContent($article->body_en);
    }

    public function test_blog_post_body_is_sanitized_on_save(): void
    {
        $this->actingAs($this->contentAdmin(), 'admin');

        $this->post(route('admin.blog.posts.store'), [
            'blog_category_id' => $this->blogCategory()->id,
            'title_en' => 'Launch', 'title_ar' => 'إطلاق',
            'body_en' => self::MALICIOUS_BODY, 'body_ar' => self::MALICIOUS_BODY,
        ])->assertRedirect();

        $post = BlogPost::sole();
        $this->assertBodyIsSafe($post->body_en);
        $this->assertBodyIsSafe($post->body_ar);
        $this->assertBodyKeepsSafeContent($post->body_ar);
    }

    public function test_help_center_article_saved_before_the_fix_renders_sanitized_with_working_toc(): void
    {
        $country = $this->country();
        $article = HelpCenterArticle::create([
            'help_center_category_id' => $this->helpCenterCategory()->id,
            'title' => 'Fees', 'title_en' => 'Fees', 'title_ar' => 'الرسوم', 'slug' => 'legacy-fees',
            'body' => self::MALICIOUS_BODY, 'body_en' => self::MALICIOUS_BODY, 'body_ar' => self::MALICIOUS_BODY,
            'status' => 'published', 'published_at' => now()->subDay(),
        ]);

        $html = $this->withSession(['locale' => 'en'])
            ->get(route('portal.helpcenter.article.show', ['country' => $country->site_code, 'article' => $article->slug]))
            ->assertOk()
            ->getContent();

        $this->assertBodyIsSafe($html);
        $this->assertBodyKeepsSafeContent($html);
        $this->assertStringContainsString('href="#h_fees"', $html);
        $this->assertSame([['id' => 'h_fees', 'label' => 'Fees', 'level' => 2]], $article->table_of_contents);
    }

    public function test_ad_support_article_saved_before_the_fix_renders_sanitized_with_working_toc(): void
    {
        $country = $this->country();
        $article = AdSupportArticle::create([
            'ad_support_collection_id' => $this->adSupportCollection()->id,
            'title' => 'Bids', 'title_en' => 'Bids', 'title_ar' => 'المزايدات', 'slug' => 'legacy-bids',
            'body' => self::MALICIOUS_BODY, 'body_en' => self::MALICIOUS_BODY, 'body_ar' => self::MALICIOUS_BODY,
            'status' => 'published', 'published_at' => now()->subDay(),
        ]);

        $html = $this->withSession(['locale' => 'ar'])
            ->get(route('portal.adsupport.articles.show', ['country' => $country->site_code, 'article' => $article->slug]))
            ->assertOk()
            ->getContent();

        $this->assertBodyIsSafe($html);
        $this->assertBodyKeepsSafeContent($html);
        $this->assertStringContainsString('href="#h_fees"', $html);
    }

    public function test_blog_post_saved_before_the_fix_renders_sanitized(): void
    {
        $post = BlogPost::create([
            'blog_category_id' => $this->blogCategory()->id,
            'author_admin_id' => Admin::factory()->create()->id,
            'title_en' => 'Launch', 'title_ar' => 'إطلاق', 'slug' => 'legacy-launch',
            'body_en' => self::MALICIOUS_BODY, 'body_ar' => self::MALICIOUS_BODY,
            'status' => 'published', 'published_at' => now()->subDay(),
        ]);

        $html = $this->withSession(['locale' => 'en'])
            ->get(route('portal.blog.show', $post->slug))
            ->assertOk()
            ->getContent();

        $this->assertBodyIsSafe($html);
        $this->assertBodyKeepsSafeContent($html);
    }
}
