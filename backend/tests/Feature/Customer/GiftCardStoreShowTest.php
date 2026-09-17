<?php

namespace Tests\Feature\Customer;

use App\Models\GiftCardBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P-26 follow-up (enhancement.md Phase H): the gift-cards detail/purchase
 * page (`/gift-cards/{id}`) moved from a static category lookup
 * (`src/features/noon/gift-cards/data.ts`) to a real single-batch fetch.
 * This locks down GET {country}/gift-card-store/{batch}.
 */
class GiftCardStoreShowTest extends TestCase
{
    use RefreshDatabase;

    private function buildScenario(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        return $scenario;
    }

    private function makeBatch(array $overrides = []): GiftCardBatch
    {
        return GiftCardBatch::create(array_merge([
            'name' => 'Congratulations 100',
            'title_en' => 'Congratulations',
            'title_ar' => 'تهانينا',
            'description' => 'A gift card for celebrations.',
            'amount' => 100,
            'currency_code' => 'AED',
            'quantity' => 10,
            'is_purchasable' => true,
            'image_url' => '/images/congratulations_t1.avif',
            'min_quantity' => 1,
            'max_quantity' => 5,
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_customer_can_fetch_a_single_available_batch(): void
    {
        $scenario = $this->buildScenario();
        $batch = $this->makeBatch();

        $response = $this->getJson(
            "/api/customer/v1/{$scenario->country->site_code}/gift-card-store/{$batch->id}?currency_code=AED"
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $batch->id);
        $response->assertJsonPath('data.title_en', 'Congratulations');
        $response->assertJsonPath('data.title_ar', 'تهانينا');
        $response->assertJsonPath('data.amount', 100);
        $response->assertJsonPath('data.currency_code', 'AED');
        $response->assertJsonPath('data.image_url', '/images/congratulations_t1.avif');
    }

    public function test_fetching_a_batch_with_the_wrong_currency_returns_404(): void
    {
        $scenario = $this->buildScenario();
        $batch = $this->makeBatch(['currency_code' => 'AED']);

        $response = $this->getJson(
            "/api/customer/v1/{$scenario->country->site_code}/gift-card-store/{$batch->id}?currency_code=SAR"
        );

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    public function test_fetching_a_non_purchasable_batch_returns_404(): void
    {
        $scenario = $this->buildScenario();
        $batch = $this->makeBatch(['is_purchasable' => false]);

        $response = $this->getJson(
            "/api/customer/v1/{$scenario->country->site_code}/gift-card-store/{$batch->id}?currency_code=AED"
        );

        $response->assertStatus(404);
    }

    public function test_fetching_an_unknown_batch_id_returns_404(): void
    {
        $scenario = $this->buildScenario();

        $response = $this->getJson(
            "/api/customer/v1/{$scenario->country->site_code}/gift-card-store/".Str::uuid()."?currency_code=AED"
        );

        $response->assertStatus(404);
    }

    public function test_show_route_does_not_shadow_the_static_my_purchases_route(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');

        $response = $this->getJson(
            "/api/customer/v1/{$scenario->country->site_code}/gift-card-store/my-purchases"
        );

        // Must hit CustomerGiftCardStoreController::myPurchases (paginated list),
        // not be swallowed by the {batch} wildcard as an invalid UUID lookup.
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['items', 'meta' => ['current_page', 'last_page']]]);
    }
}
