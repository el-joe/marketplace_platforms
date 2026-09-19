<?php

namespace Tests\Feature\CustomPages;

use App\Models\Category;
use App\Models\CustomPage;
use App\Models\Slug;
use Database\Seeders\NawyNowCustomPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class NawyNowSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_idempotent_and_preserves_admin_edits(): void
    {
        $this->seed(NawyNowCustomPageSeeder::class);
        CustomPage::first()->update(['name_en' => 'Edited']);
        $this->seed(NawyNowCustomPageSeeder::class);

        $this->assertSame(1, CustomPage::count());
        $this->assertSame(1, Slug::where('slug_url', 'nawy-now')->count());
        $p = CustomPage::first();
        $this->assertSame('Edited', $p->name_en);
        $this->assertSame(['admin'], $p->listing_types);
        $this->assertTrue($p->all_categories);
        $this->assertTrue($p->has_filters);
    }

    public function test_fails_loudly_when_slug_owned_by_other_entity(): void
    {
        $c = Category::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'slug' => 'nawy-now', 'name_en' => 'X', 'name_ar' => 'X', 'is_active' => true]);
        Slug::create(['slug_url' => 'nawy-now', 'sluggable_type' => Category::class, 'sluggable_id' => $c->id]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already owned');
        $this->seed(NawyNowCustomPageSeeder::class);
    }

    public function test_api_returns_admin_only_with_filters(): void
    {
        $s = MarketplaceScenario::make()->build();
        if (empty($s->country->site_code)) {
            $s->country->update(['site_code' => strtolower($s->country->iso_code_2)]);
        }
        $this->seed(NawyNowCustomPageSeeder::class);
        $base = "/api/customer/v1/{$s->country->site_code}/products?category=nawy-now";

        $r = $this->getJson($base)->assertOk();
        $types = collect($r->json('data.items'))->pluck('listing_type')->unique()->all();
        $this->assertSame(['admin'], $types);
        $this->assertTrue($r->json('data.category.has_filters'));
        $this->assertNotNull($r->json('data.filters') ?? $r->json('data.facets'));
        $this->getJson($base . '&sort=price_asc&price_min=1&price_max=99999999&brand=' . $s->brand->id)->assertOk();
    }
}
