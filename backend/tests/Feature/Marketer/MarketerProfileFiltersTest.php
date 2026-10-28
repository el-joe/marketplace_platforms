<?php

namespace Tests\Feature\Marketer;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\ExclusiveContract;
use App\Models\Marketer;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerListing;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TravelCountry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\MarketerV3Fixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class MarketerProfileFiltersTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    private Marketer $m;

    private string $slug;

    private Category $otherCategory;

    private Brand $otherBrand;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        $this->m = Marketer::factory()->withProfile('male')->create(['country_id' => $this->s->country->id]);
        $this->slug = $this->m->marketerProfile->profile_slug;

        $this->otherCategory = Category::create([
            'id' => (string) Str::uuid(),
            'name_en' => 'Fashion', 'name_ar' => 'أزياء', 'slug' => 'fashion-'.Str::lower(Str::random(6)),
            'commission_rate' => 10, 'is_active' => true, 'is_visible' => true,
        ]);
        $this->otherBrand = Brand::create(['name_en' => 'Zed', 'name_ar' => 'زد', 'slug' => 'zed-'.Str::lower(Str::random(6)), 'is_verified' => true, 'is_active' => true]);
    }

    private function url(array $query = []): string
    {
        $qs = $query ? '?'.http_build_query($query) : '';

        return "/api/public/v1/{$this->s->country->site_code}/marketers/{$this->slug}{$qs}";
    }

    private function fashionVariant(string $name): ProductVariant
    {
        $product = Product::create([
            'category_id' => $this->otherCategory->id, 'brand_id' => $this->otherBrand->id, 'name_en' => $name, 'name_ar' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)), 'status' => 'active', 'has_variants' => false,
        ]);

        return ProductVariant::create(['product_id' => $product->id, 'sku' => 'SKU-'.Str::upper(Str::random(10)), 'variant_name' => $name, 'is_default' => true, 'is_active' => true, 'position' => 0]);
    }

    private function listing(array $attrs): MarketerListing
    {
        return MarketerListing::create(array_merge([
            'marketer_id' => $this->m->id, 'country_id' => $this->s->country->id, 'listing_category' => 'product',
            'currency' => 'AED', 'status' => 'active', 'condition' => 'new', 'referral_code' => 'T-'.Str::upper(Str::random(10)),
        ], $attrs));
    }

    /** Own: 2 products; for sellers: 1 vendor-campaign product + 1 classified campaign ad. */
    private function seedListings(): array
    {
        $own1 = $this->listing(['product_variant_id' => $this->fashionVariant('Red Dress')->id, 'price' => 20000, 'compare_at_price' => 40000, 'total_sold' => 5]);
        $own2 = $this->listing(['product_variant_id' => $this->s->variants[1]->id, 'price' => 5000, 'total_sold' => 1]);

        $invitation = MarketerCampaignInvitation::create([
            'campaign_id' => $this->s->marketerCampaign->id, 'marketer_id' => $this->m->id, 'status' => 'accepted',
            'responded_at' => now(), 'referral_code' => 'REF-'.Str::upper(Str::random(8)),
        ]);
        $promoted = $this->listing([
            'product_variant_id' => $this->s->variants[0]->id, 'invitation_id' => $invitation->id, 'source_type' => 'vendor_listing',
            'source_listing_id' => $this->s->vendorListingFbp->id, 'price' => 1050,
        ]);

        $ad = MarketerV3Fixtures::classifiedListing($this->s, ['title_en' => 'Used car', 'price' => 90000]);
        $campaign = $this->s->marketerCampaign->replicate();
        $campaign->forceFill(['vendor_listing_id' => null, 'classified_listing_id' => $ad->id, 'campaign_category' => 'classified'])->save();
        $classifiedInvitation = MarketerCampaignInvitation::create([
            'campaign_id' => $campaign->id, 'marketer_id' => $this->m->id, 'status' => 'accepted',
            'responded_at' => now(), 'referral_code' => 'REF-'.Str::upper(Str::random(8)),
        ]);
        $classified = $this->listing([
            'listing_category' => 'classified', 'classified_listing_id' => $ad->id, 'invitation_id' => $classifiedInvitation->id, 'price' => 90000,
        ]);

        // The marketer's own classified ad (no campaign) is not part of the unified grid.
        $this->listing(['listing_category' => 'classified', 'classified_listing_id' => MarketerV3Fixtures::classifiedListing($this->s)->id, 'price' => 1]);

        return compact('own1', 'own2', 'promoted', 'classified', 'ad');
    }

    private function items(array $query = []): array
    {
        return $this->getJson($this->url($query))->assertOk()->json('data.listings.items');
    }

    public function test_response_keeps_legacy_keys_and_adds_new_blocks(): void
    {
        $this->seedListings();
        $data = $this->getJson($this->url())->assertOk()->json('data');

        foreach (['marketer', 'profile', 'own_listings', 'vendor_campaign_listings', 'marketer_campaign_listings', 'campaign_listings', 'exclusive_contracts', 'classified_listings', 'classified_count'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
        $this->assertSame('male', $data['profile']['gender']);
        $this->assertSame([], $data['banners']);
        $this->assertSame(['items', 'meta'], array_keys($data['listings']));
        $this->assertSame(['current_page', 'last_page', 'per_page', 'total'], array_keys($data['listings']['meta']));
        $this->assertSame(['sources', 'kinds', 'categories', 'brands', 'destinations', 'price', 'discounted_count'], array_keys($data['facets']));
        $this->assertCount(2, $data['own_listings']['items']);
        $this->assertSame(4, $data['listings']['meta']['total']);
    }

    public function test_source_and_kind_filters(): void
    {
        $r = $this->seedListings();

        $own = $this->items(['source' => 'own']);
        $this->assertEqualsCanonicalizing([$r['own1']->id, $r['own2']->id], array_column($own, 'id'));
        $this->assertSame(['own'], array_unique(array_column($own, 'source')));

        $sellers = $this->items(['source' => 'for_sellers']);
        $this->assertEqualsCanonicalizing([$r['promoted']->id, $r['classified']->id], array_column($sellers, 'id'));

        $classified = $this->items(['source' => 'for_sellers', 'kind' => 'classified']);
        $this->assertCount(1, $classified);
        $card = $classified[0];
        $this->assertSame('classified', $card['kind']);
        $this->assertSame($r['ad']->slug, $card['slug']);
        $this->assertSame(90000, $card['price']);
        foreach (['is_exclusive', 'discount_percent', 'location', 'thumbnail', 'title_en', 'title_ar', 'currency'] as $key) {
            $this->assertArrayHasKey($key, $card);
        }

        $products = $this->items(['source' => 'for_sellers', 'kind' => 'products']);
        $this->assertSame([$r['promoted']->id], array_column($products, 'id'));
        $this->assertSame('product', $products[0]['kind']);
        $this->assertTrue($products[0]['campaign']['is_vendor']);
        $this->assertNull($this->items(['source' => 'own', 'kind' => 'products'])[0]['campaign']);

        $this->assertSame([], $this->items(['source' => 'own', 'kind' => 'classified']));

        // Sections: a vendor-campaign product is narrowed by source and labelled by `section`.
        $this->assertSame([$r['promoted']->id], array_column($this->items(['source' => 'vendor_campaign']), 'id'));
        $this->assertSame([], $this->items(['source' => 'marketer_campaign']));
        $this->assertSame('vendor_campaign', $products[0]['section']);
        $this->assertSame('own', $this->items(['source' => 'own', 'kind' => 'products'])[0]['section']);
    }

    public function test_search_category_brand_price_and_discount_filters(): void
    {
        $r = $this->seedListings();

        $this->assertSame([$r['own1']->id], array_column($this->items(['q' => 'Red Dress']), 'id'));
        $this->assertSame([$r['own1']->id], array_column($this->items(['category' => [$this->otherCategory->id]]), 'id'));
        $this->assertSame([$r['own1']->id], array_column($this->items(['brand' => [$this->otherBrand->id]]), 'id'));
        $this->assertSame([$r['own1']->id], array_column($this->items(['price_min' => 10000, 'kind' => 'products']), 'id'));
        $this->assertEqualsCanonicalizing([$r['own2']->id, $r['promoted']->id], array_column($this->items(['price_max' => 5000, 'kind' => 'products']), 'id'));

        $discounted = $this->items(['discount' => 1]);
        $this->assertSame([$r['own1']->id], array_column($discounted, 'id'));
        $this->assertSame(50, $discounted[0]['discount_percent']);
        $this->assertSame('product', $discounted[0]['kind']);
    }

    public function test_sort_and_pagination(): void
    {
        $r = $this->seedListings();

        $asc = $this->items(['kind' => 'products', 'sort' => 'price_asc']);
        $this->assertSame([$r['promoted']->id, $r['own2']->id, $r['own1']->id], array_column($asc, 'id'));
        $desc = $this->items(['kind' => 'products', 'sort' => 'price_desc']);
        $this->assertSame($r['own1']->id, $desc[0]['id']);

        $page = $this->getJson($this->url(['per_page' => 3, 'page' => 2, 'sort' => 'price_asc']))->json('data.listings');
        $this->assertCount(1, $page['items']);
        $this->assertSame(['current_page' => 2, 'last_page' => 2, 'per_page' => 3, 'total' => 4], $page['meta']);
    }

    public function test_facets_counts_and_ranges(): void
    {
        $this->seedListings();
        $f = $this->getJson($this->url())->json('data.facets');

        $this->assertSame(['all' => 4, 'own' => 2, 'vendor_campaign' => 1, 'marketer_campaign' => 0], collect($f['sources'])->pluck('count', 'key')->all());
        $this->assertSame(['all' => 4, 'products' => 3, 'classified' => 1, 'travel' => 0], collect($f['kinds'])->pluck('count', 'key')->all());
        $this->assertSame(['Electronics' => 2, 'Fashion' => 1], collect($f['categories'])->pluck('count', 'name')->all());
        $this->assertSame(['Acme' => 2, 'Zed' => 1], collect($f['brands'])->pluck('count', 'name')->all());
        $this->assertSame(['min' => 1050, 'max' => 90000], $f['price']);
        $this->assertSame(1, $f['discounted_count']);

        $ar = $this->getJson($this->url(['locale' => 'ar']))->json('data.facets.categories');
        $this->assertContains('أزياء', array_column($ar, 'name'));

        // Tab (kind) counts ignore the other filters so the tabs stay stable.
        $own = $this->getJson($this->url(['source' => 'own']))->json('data.facets');
        $this->assertSame(['all' => 4, 'products' => 3, 'classified' => 1, 'travel' => 0], collect($own['kinds'])->pluck('count', 'key')->all());
    }

    private function travelListing(string $title, array $packageOverrides = []): MarketerListing
    {
        $package = MarketerV3Fixtures::package(MarketerV3Fixtures::agency($this->s), array_merge(['title_en' => $title, 'price' => 3000], $packageOverrides));
        $campaign = $this->s->marketerCampaign->replicate();
        $campaign->forceFill(['vendor_listing_id' => null, 'travel_package_id' => $package->id, 'campaign_category' => 'travel'])->save();
        $invitation = MarketerCampaignInvitation::create([
            'campaign_id' => $campaign->id, 'marketer_id' => $this->m->id, 'status' => 'accepted',
            'responded_at' => now(), 'referral_code' => 'REF-'.Str::upper(Str::random(8)),
        ]);

        return $this->listing(['listing_category' => 'travel', 'travel_package_id' => $package->id, 'invitation_id' => $invitation->id, 'price' => $package->price]);
    }

    public function test_travel_tab_lists_active_upcoming_campaign_packages_with_their_own_filters(): void
    {
        $r = $this->seedListings();
        $turkey = TravelCountry::create(['iso_code_2' => 'TR', 'iso_code_3' => 'TUR', 'name_en' => 'Turkey', 'name_ar' => 'تركيا']);
        $this->travelListing('Istanbul escape', ['destination_country' => 'Turkey', 'destination_city' => 'Istanbul', 'destination_travel_country_id' => $turkey->id]);
        $this->travelListing('Old trip', ['departure_date' => now()->subDay(), 'return_date' => now()->addDay()]);

        $travel = $this->items(['kind' => 'travel']);
        $this->assertCount(1, $travel);
        $this->assertSame('travel', $travel[0]['kind']);
        $this->assertSame('travel', $travel[0]['listing_type']);
        $this->assertSame('Istanbul escape', $travel[0]['title_en']);
        $this->assertSame('Istanbul', $travel[0]['destination_city']);
        $this->assertNotEmpty($travel[0]['slug']);

        $this->assertCount(1, $this->items(['kind' => 'travel', 'destination' => $turkey->id]));
        $this->assertCount(0, $this->items(['kind' => 'travel', 'destination' => (string) Str::uuid()]));
        $this->assertCount(1, $this->items(['kind' => 'travel', 'q' => 'istanbul']));
        $this->assertCount(0, $this->items(['kind' => 'travel', 'price_min' => 5000]));

        // Other tabs never include travel; the unfiltered grid does.
        $this->assertNotContains('travel', array_column($this->items(['kind' => 'products']), 'kind'));
        $this->assertNotContains('travel', array_column($this->items(['kind' => 'classified']), 'kind'));
        $this->assertContains('travel', array_column($this->items(), 'kind'));

        $f = $this->getJson($this->url(['kind' => 'travel']))->json('data.facets');
        $this->assertSame(['all' => 5, 'products' => 3, 'classified' => 1, 'travel' => 1], collect($f['kinds'])->pluck('count', 'key')->all());
        $this->assertSame(['Turkey' => 1], collect($f['destinations'])->pluck('count', 'name')->all());
        $this->assertSame([], $f['categories']);
        $this->assertSame([], $f['brands']);
    }

    public function test_classified_tab_category_filter_uses_classified_categories(): void
    {
        $r = $this->seedListings();
        $categoryId = $r['ad']->classified_category_id;

        $f = $this->getJson($this->url(['kind' => 'classified']))->json('data.facets');
        $this->assertSame([$categoryId => 1], collect($f['categories'])->pluck('count', 'id')->all());
        $this->assertSame([], $f['brands']);

        $this->assertCount(1, $this->items(['kind' => 'classified', 'category' => $categoryId]));
        $this->assertCount(0, $this->items(['kind' => 'classified', 'category' => (string) Str::uuid()]));
    }

    public function test_exclusive_classified_cards_and_other_countries_are_scoped(): void
    {
        $r = $this->seedListings();
        ExclusiveContract::forceCreate([
            'marketer_id' => $this->m->id, 'classified_listing_id' => $r['ad']->id, 'classified_category_id' => $r['ad']->classified_category_id,
            'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
        ]);
        $r['own2']->update(['country_id' => Country::factory()->create()->id]);

        $card = $this->items(['kind' => 'classified'])[0];
        $this->assertTrue($card['is_exclusive']);
        $this->assertSame(3, $this->getJson($this->url())->json('data.listings.meta.total'));
    }

    public function test_filtered_responses_are_not_served_from_the_header_cache(): void
    {
        $this->seedListings();
        $this->getJson($this->url())->assertOk();
        $this->assertCount(1, $this->items(['q' => 'Red Dress']));
        $this->assertCount(2, $this->items(['source' => 'own']));
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.listings.meta.total', 4);
    }
}
