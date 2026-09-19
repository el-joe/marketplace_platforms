<?php

namespace Tests\Feature\Travel;

use App\Models\TravelAgency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class TravelSearchFiltersTest extends TestCase
{
    use RefreshDatabase;

    private string $base;
    private string $c1;
    private string $c2;
    private string $city1;
    private string $city2;
    private string $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->base = "/api/customer/v1/{$s->country->site_code}/travel";
        $now = now();
        foreach ([1, 2] as $i) {
            $this->{"c$i"} = (string) Str::uuid();
            DB::table('travel_countries')->insert(['id' => $this->{"c$i"}, 'iso_code_2' => "X$i", 'iso_code_3' => "XX$i", 'name_en' => "C$i", 'name_ar' => "C$i", 'created_at' => $now, 'updated_at' => $now]);
            $this->{"city$i"} = (string) Str::uuid();
            DB::table('travel_cities')->insert(['id' => $this->{"city$i"}, 'travel_country_id' => $this->{"c$i"}, 'name_en' => "T$i", 'name_ar' => "T$i", 'created_at' => $now, 'updated_at' => $now]);
        }
        $this->agency = TravelAgency::query()->first()?->id ?? $this->makeAgency($s->country->id);
    }

    private function makeAgency(string $countryId): string
    {
        $id = (string) Str::uuid();
        DB::table('travel_agencies')->insert(['id' => $id, 'name' => 'A', 'email' => Str::random(6).'@x.com', 'password' => 'x', 'country_id' => $countryId, 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }

    private function pkg(string $status, int $days, ?string $country, ?string $city, string $slug): void
    {
        DB::table('travel_packages')->insert([
            'id' => (string) Str::uuid(), 'travel_agency_id' => $this->agency, 'slug' => $slug,
            'title_en' => $slug, 'title_ar' => $slug, 'price' => 1000, 'currency' => 'AED',
            'duration_days' => 3, 'duration_nights' => 2, 'departure_date' => today()->addDays($days),
            'return_date' => today()->addDays($days + 3), 'status' => $status,
            'destination_travel_country_id' => $country, 'destination_travel_city_id' => $city,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function slugs($r): array
    {
        return array_column($r->json('data.listings.items'), 'slug');
    }

    public function test_filters_sort_and_visibility(): void
    {
        $this->pkg('active', 30, $this->c1, $this->city1, 'late');
        $this->pkg('active', 5, $this->c1, $this->city1, 'soon');
        $this->pkg('active', 10, $this->c2, $this->city2, 'other');
        $this->pkg('active', -3, $this->c1, $this->city1, 'expired');
        $this->pkg('draft', 7, $this->c1, $this->city1, 'draft');

        $this->assertSame(['soon', 'other', 'late'], $this->slugs($this->getJson($this->base)));
        $this->assertSame(['soon', 'late'], $this->slugs($this->getJson($this->base.'?country_id='.$this->c1)));
        $this->assertSame(['other'], $this->slugs($this->getJson($this->base.'?city_id='.$this->city2)));
        $this->assertSame(['other'], $this->slugs($this->getJson($this->base.'?departure_from='.today()->addDays(8)->toDateString().'&departure_to='.today()->addDays(20)->toDateString())));
        $this->assertSame(['soon', 'other'], $this->slugs($this->getJson($this->base.'?departure_to='.today()->addDays(10)->toDateString())));
        $this->assertSame(['other'], $this->slugs($this->getJson($this->base.'?country_id='.$this->c2.'&city_id='.$this->city2.'&departure_from='.today()->toDateString())));
        $this->assertSame(1, $this->getJson($this->base.'?per_page=1')->json('data.listings.meta.per_page'));
        $this->assertSame(3, $this->getJson($this->base.'?per_page=1')->json('data.listings.meta.total'));
    }

    public function test_invalid_input_is_422(): void
    {
        $this->getJson($this->base.'?country_id=nope')->assertStatus(422);
        $this->getJson($this->base.'?city_id='.Str::uuid())->assertStatus(422);
        $this->getJson($this->base.'?departure_from=garbage')->assertStatus(422);
        $this->getJson($this->base.'?departure_from=2030-02-01&departure_to=2030-01-01')->assertStatus(422);
        $this->getJson($this->base.'?country_id='.$this->c1.'&city_id='.$this->city2)->assertStatus(422);
    }
}
