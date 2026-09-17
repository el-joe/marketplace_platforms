<?php

namespace Tests\Feature\Customer;

use App\Models\HelpCenterArticle;
use App\Models\HelpCenterCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-26: GET {country}/help-center/tree replaces the
 * frontend's static help-sheet mock tree
 * (src/features/help-sheet/api/mockData.ts) with real
 * help_center_categories / help_center_articles data.
 */
class HelpCenterTreeTest extends TestCase
{
    use RefreshDatabase;

    private function buildTree(string $countrySiteCode): array
    {
        $orders = HelpCenterCategory::create([
            'name' => 'Orders', 'name_en' => 'Orders', 'name_ar' => 'الطلبات',
            'slug' => 'orders-' . $countrySiteCode,
            'description' => 'Manage your orders', 'description_en' => 'Manage your orders', 'description_ar' => 'إدارة طلباتك',
            'icon' => 'https://cdn.test/orders.svg',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $returns = HelpCenterCategory::create([
            'parent_id' => $orders->id,
            'name' => 'Returns', 'name_en' => 'Returns', 'name_ar' => 'المرتجعات',
            'slug' => 'returns-' . $countrySiteCode,
            'description' => '', 'description_en' => '', 'description_ar' => '',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Inactive sibling category — must never appear.
        HelpCenterCategory::create([
            'name' => 'Hidden', 'name_en' => 'Hidden', 'name_ar' => 'مخفي',
            'slug' => 'hidden-' . $countrySiteCode,
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $publishedArticle = HelpCenterArticle::create([
            'help_center_category_id' => $returns->id,
            'title' => 'How do I return an item?',
            'title_en' => 'How do I return an item?',
            'title_ar' => 'كيف أرجع منتجًا؟',
            'slug' => 'how-to-return-' . $countrySiteCode,
            'excerpt' => 'Return steps', 'excerpt_en' => 'Return steps', 'excerpt_ar' => 'خطوات الإرجاع',
            'body' => '<p>Go to Orders and select Return.</p>',
            'body_en' => '<p>Go to Orders and select Return.</p>',
            'body_ar' => '<p>اذهب إلى الطلبات واختر إرجاع.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        HelpCenterArticle::create([
            'help_center_category_id' => $returns->id,
            'title' => 'Draft article',
            'title_en' => 'Draft article',
            'title_ar' => 'مسودة',
            'slug' => 'draft-article-' . $countrySiteCode,
            'body' => '<p>Draft</p>', 'body_en' => '<p>Draft</p>', 'body_ar' => '<p>مسودة</p>',
            'status' => 'draft',
        ]);

        return compact('orders', 'returns', 'publishedArticle');
    }

    private function scenarioWithSiteCode(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        // MarketplaceScenario doesn't set site_code (only iso_code_2), but
        // DetectCountry middleware resolves {country} route segments via
        // site_code, not iso_code_2 (see HomePageQueryCountTest).
        if (empty($scenario->country->site_code)) {
            $scenario->country->update(['site_code' => strtolower($scenario->country->iso_code_2)]);
        }

        return $scenario;
    }

    public function test_returns_the_nested_category_tree_with_published_articles(): void
    {
        $scenario = $this->scenarioWithSiteCode();
        ['orders' => $orders, 'returns' => $returns, 'publishedArticle' => $article] = $this->buildTree($scenario->country->site_code);

        $response = $this->getJson("/api/customer/v1/{$scenario->country->site_code}/help-center/tree");

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(1, $data, 'Only the active top-level category should be returned.');

        $ordersNode = $data[0];
        $this->assertSame($orders->id, $ordersNode['id']);
        $this->assertSame(['ar' => 'الطلبات', 'en' => 'Orders'], $ordersNode['title']);
        $this->assertCount(1, $ordersNode['children']);

        $returnsNode = $ordersNode['children'][0];
        $this->assertSame($returns->id, $returnsNode['id']);
        $this->assertCount(1, $returnsNode['articles'], 'The draft article must be excluded.');

        $articleNode = $returnsNode['articles'][0];
        $this->assertSame($article->id, $articleNode['id']);
        $this->assertSame('How do I return an item?', $articleNode['title']['en']);
        $this->assertSame('كيف أرجع منتجًا؟', $articleNode['title']['ar']);
        $this->assertSame('<p>Go to Orders and select Return.</p>', $articleNode['body']['en']);
    }

    public function test_country_scoped_category_is_hidden_from_a_different_country(): void
    {
        $scenario = $this->scenarioWithSiteCode();
        $this->buildTree($scenario->country->site_code);

        HelpCenterCategory::create([
            'country_id' => 'some-other-country',
            'name' => 'Other Country Only', 'name_en' => 'Other Country Only', 'name_ar' => 'دولة أخرى فقط',
            'slug' => 'other-country-only-' . $scenario->country->site_code,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/customer/v1/{$scenario->country->site_code}/help-center/tree")->assertOk();

        $slugs = array_column($response->json('data'), 'slug');
        $this->assertNotContains('other-country-only-' . $scenario->country->site_code, $slugs);
    }
}
