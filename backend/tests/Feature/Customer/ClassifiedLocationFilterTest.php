<?php

namespace Tests\Feature\Customer;

use App\Models\City;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedListing;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Classified list and map pins can be narrowed by city or by a point + radius,
 * and /classified/cities lists the cities that currently have active ads.
 */
class ClassifiedLocationFilterTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private City $riyadh;

    private City $jeddah;

    private ClassifiedCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scenario = MarketplaceScenario::make()->build();
        $this->scenario->country->update(['site_code' => 'sa-'.Str::lower(Str::random(6))]);

        $this->riyadh = $this->city('Riyadh', 'الرياض');
        $this->jeddah = $this->city('Jeddah', 'جدة');
        $this->category = ClassifiedCategory::create([
            'name_en' => 'Cars', 'name_ar' => 'سيارات', 'slug' => 'cars-'.Str::lower(Str::random(6)),
            'is_active' => true, 'sort_order' => 1,
        ]);
    }

    private function city(string $en, string $ar): City
    {
        return City::create(['country_id' => $this->scenario->country->id, 'name_en' => $en, 'name_ar' => $ar, 'is_active' => true]);
    }

    private function listing(City $city, float $lat, float $lng, string $title): ClassifiedListing
    {
        return ClassifiedListing::create([
            'listing_number' => 'CL-'.Str::upper(Str::random(8)),
            'seller_type' => Customer::class, 'seller_id' => $this->scenario->customer->id,
            'classified_category_id' => $this->category->id, 'country_id' => $this->scenario->country->id,
            'city_id' => $city->id, 'listing_purpose' => 'sale', 'title_en' => $title, 'title_ar' => $title,
            'price' => 1000, 'currency' => 'SAR', 'price_negotiable' => false, 'status' => 'active',
            'latitude' => $lat, 'longitude' => $lng,
        ]);
    }

    private function url(string $path, array $query = []): string
    {
        $qs = $query ? '?'.http_build_query($query) : '';

        return "/api/customer/v1/{$this->scenario->country->site_code}/{$path}{$qs}";
    }

    private function seedListings(): void
    {
        $this->listing($this->riyadh, 24.7136, 46.6753, 'Riyadh centre');
        $this->listing($this->riyadh, 24.7500, 46.7000, 'Riyadh north');
        $this->listing($this->jeddah, 21.4858, 39.1925, 'Jeddah corniche');
    }

    private function titles(array $items): array
    {
        $titles = array_map(fn ($i) => $i['title_en'] ?? $i['title'], $items);
        sort($titles);

        return $titles;
    }

    public function test_list_filters_by_city_and_by_radius(): void
    {
        $this->seedListings();

        $this->assertSame(
            ['Jeddah corniche', 'Riyadh centre', 'Riyadh north'],
            $this->titles($this->getJson($this->url('classified'))->assertOk()->json('data.listings.items')),
        );
        $this->assertSame(
            ['Jeddah corniche'],
            $this->titles($this->getJson($this->url('classified', ['city_id' => $this->jeddah->id]))->json('data.listings.items')),
        );
        $this->assertSame(
            ['Riyadh centre', 'Riyadh north'],
            $this->titles($this->getJson($this->url('classified', ['lat' => 24.72, 'lng' => 46.68, 'radius_km' => 25]))->json('data.listings.items')),
        );
        // A tight radius around the centre excludes the listing ~5 km away.
        $this->assertSame(
            ['Riyadh centre'],
            $this->titles($this->getJson($this->url('classified', ['lat' => 24.7136, 'lng' => 46.6753, 'radius_km' => 1]))->json('data.listings.items')),
        );
    }

    public function test_invalid_coordinates_are_ignored_instead_of_filtering_everything_out(): void
    {
        $this->seedListings();

        $items = $this->getJson($this->url('classified', ['lat' => 'abc', 'lng' => 999]))->assertOk()->json('data.listings.items');

        $this->assertCount(3, $items);
    }

    public function test_map_pins_follow_the_same_city_and_radius_filters(): void
    {
        $this->seedListings();

        $byCity = $this->getJson($this->url('classified/map-pins', ['city_id' => $this->riyadh->id]))->assertOk()->json('data');
        $this->assertSame(['Riyadh centre', 'Riyadh north'], $this->titles($byCity));

        $byRadius = $this->getJson($this->url('classified/map-pins', ['lat' => 21.5, 'lng' => 39.2, 'radius_km' => 50]))->json('data');
        $this->assertSame(['Jeddah corniche'], $this->titles($byRadius));
    }

    public function test_cities_endpoint_lists_cities_with_active_ads_busiest_first(): void
    {
        $this->seedListings();
        $this->city('Empty town', 'بلدة فارغة');
        $this->listing($this->jeddah, 21.5, 39.2, 'Sold')->update(['status' => 'sold']);

        $cities = $this->getJson($this->url('classified/cities', ['locale' => 'uae-en']))->assertOk()->json('data');

        $this->assertSame([['name' => 'Riyadh', 'count' => 2], ['name' => 'Jeddah', 'count' => 1]], array_map(
            fn ($c) => ['name' => $c['name'], 'count' => $c['count']],
            $cities,
        ));
    }
}
