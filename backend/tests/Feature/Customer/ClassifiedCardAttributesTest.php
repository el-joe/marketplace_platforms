<?php

namespace Tests\Feature\Customer;

use App\Models\City;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\Customer;
use App\Services\Customer\ListingQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P-26 follow-up: the classified card/list/similar-listings API response
 * (App\Services\Customer\ListingQueryService::toClassifiedCardShape) was
 * missing the listing's `attributes`, so the frontend card had no real data
 * to render its spec chips (year/make/model/...) and fell back to a
 * hardcoded "Dummy data" placeholder array. This asserts the real attributes
 * are now returned end-to-end from both the browse/classified listing and
 * the classified detail's "similar" endpoint.
 */
class ClassifiedCardAttributesTest extends TestCase
{
    use RefreshDatabase;

    private function makeScenario(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-' . Str::lower(Str::random(6))]);

        return $scenario;
    }

    private function makeListing(MarketplaceScenario $scenario, array $overrides = []): ClassifiedListing
    {
        $city = City::create([
            'country_id' => $scenario->country->id,
            'name_en' => 'Test City',
            'name_ar' => 'مدينة تجريبية',
            'is_active' => true,
        ]);

        $category = ClassifiedCategory::create([
            'name_en' => 'Cars For Sale',
            'name_ar' => 'سيارات للبيع',
            'slug' => 'cars-for-sale-' . Str::lower(Str::random(6)),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return ClassifiedListing::create(array_merge([
            'listing_number' => 'CL-' . Str::upper(Str::random(8)),
            'seller_type' => Customer::class,
            'seller_id' => $scenario->customer->id,
            'classified_category_id' => $category->id,
            'country_id' => $scenario->country->id,
            'city_id' => $city->id,
            'listing_purpose' => 'sale',
            'title_en' => '2026 Kia Sportage EX',
            'title_ar' => 'سبورتاج 2026',
            'description_en' => 'Great car',
            'description_ar' => 'سيارة رائعة',
            'price' => 2050000,
            'currency' => 'EGP',
            'price_negotiable' => false,
            'attributes' => [
                'year' => '2026',
                'make' => 'Kia',
                'model' => 'Sportage',
                'condition' => 'New',
                'fuel_type' => 'Gasoline',
            ],
            'status' => 'active',
        ], $overrides));
    }

    public function test_to_classified_card_shape_includes_attributes(): void
    {
        $scenario = $this->makeScenario();
        $listing = $this->makeListing($scenario);

        $shape = app(ListingQueryService::class)->toClassifiedCardShape($listing->fresh(['images', 'city']));

        $this->assertArrayHasKey('attributes', $shape);
        $this->assertEquals([
            'year' => '2026',
            'make' => 'Kia',
            'model' => 'Sportage',
            'condition' => 'New',
            'fuel_type' => 'Gasoline',
        ], $shape['attributes']);
    }

    public function test_browse_classified_all_returns_listing_attributes(): void
    {
        $scenario = $this->makeScenario();
        $listing = $this->makeListing($scenario);

        $response = $this->getJson("/api/customer/v1/{$scenario->country->site_code}/classified");

        $response->assertOk();
        $items = $response->json('data.listings.items') ?? $response->json('data.items') ?? $response->json('data');
        $this->assertNotEmpty($items);
        $match = collect($items)->firstWhere('listing_number', $listing->listing_number);
        $this->assertNotNull($match);
        $this->assertSame('Kia', $match['attributes']['make']);
    }

    public function test_similar_classified_returns_listing_attributes(): void
    {
        $scenario = $this->makeScenario();
        $listing = $this->makeListing($scenario);
        $otherListing = $this->makeListing($scenario, [
            'classified_category_id' => $listing->classified_category_id,
        ]);

        $response = $this->getJson(
            "/api/customer/v1/{$scenario->country->site_code}/listings/classified/{$listing->slug}/similar"
        );

        $response->assertOk();
        $items = $response->json('data.items');
        $this->assertNotEmpty($items);
        $match = collect($items)->firstWhere('listing_number', $otherListing->listing_number);
        $this->assertNotNull($match);
        $this->assertArrayHasKey('attributes', $match);
        $this->assertSame('Kia', $match['attributes']['make']);
    }
}
