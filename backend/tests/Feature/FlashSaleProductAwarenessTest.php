<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\FlashSale;
use App\Models\FlashSaleSubmission;
use App\Services\FlashSaleService;
use App\Services\Shared\PageBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * docs/plans/flash-sale-badge-and-countdown.md Task H: per-product
 * flash-sale awareness, mirroring PageBuilderServiceMegaDealTest's coverage
 * of the mega-deal equivalent. Source of truth: a listing has an active
 * flash-sale price when its FlashSaleSubmission.status = 'live', and the
 * countdown end time is that submission's parent FlashSale.sale_ends_at.
 */
class FlashSaleProductAwarenessTest extends TestCase
{
    use RefreshDatabase;

    private function makeFlashSale(array $overrides = []): FlashSale
    {
        $admin = Admin::factory()->create();

        return FlashSale::create(array_merge([
            'country_id' => null,
            'name_en' => 'Test Flash Sale',
            'name_ar' => 'عرض برق تجريبي',
            'status' => 'live',
            'submission_opens_at' => now()->subDays(5),
            'submission_closes_at' => now()->subDays(4),
            'review_deadline_at' => now()->subDays(3),
            'sale_starts_at' => now()->subHour(),
            'sale_ends_at' => now()->addHours(4),
            'min_discount_pct' => 10,
            'created_by_admin_id' => $admin->id,
        ], $overrides));
    }

    private function makeSubmission(FlashSale $sale, $scenario, array $overrides = []): FlashSaleSubmission
    {
        return FlashSaleSubmission::create(array_merge([
            'flash_sale_id' => $sale->id,
            'vendor_id' => $scenario->vendor->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'status' => 'live',
            'flash_price' => 5000,
            'original_price' => 10000,
            'calculated_discount_pct' => 50,
            'max_quantity_total' => 100,
            'flash_price_currency' => $scenario->country->currency_code,
        ], $overrides));
    }

    public function test_product_with_live_submission_reports_is_flash_sale_true_with_correct_ends_at(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $sale = $this->makeFlashSale();
        $this->makeSubmission($sale, $scenario);

        $service = app(FlashSaleService::class);

        $endsAt = $service->activeFlashSaleEndsAtForProduct($scenario->product->id, $scenario->country);

        $this->assertNotNull($endsAt);
        $this->assertTrue($endsAt->equalTo($sale->sale_ends_at));

        $batch = $service->activeFlashSaleEndsAtByProduct([$scenario->product->id], $scenario->country);
        $this->assertTrue($batch->has($scenario->product->id));
        $this->assertTrue($batch->get($scenario->product->id)->equalTo($sale->sale_ends_at));
    }

    public function test_product_with_only_draft_submission_reports_false(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $sale = $this->makeFlashSale();
        $this->makeSubmission($sale, $scenario, ['status' => 'draft']);

        $service = app(FlashSaleService::class);

        $this->assertNull($service->activeFlashSaleEndsAtForProduct($scenario->product->id, $scenario->country));
    }

    public function test_product_with_only_ended_submission_reports_false(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $sale = $this->makeFlashSale();
        $this->makeSubmission($sale, $scenario, ['status' => 'ended']);

        $service = app(FlashSaleService::class);

        $this->assertNull($service->activeFlashSaleEndsAtForProduct($scenario->product->id, $scenario->country));
    }

    public function test_product_with_live_submission_but_ended_parent_sale_reports_false(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $sale = $this->makeFlashSale(['status' => 'ended']);
        $this->makeSubmission($sale, $scenario);

        $service = app(FlashSaleService::class);

        $this->assertNull($service->activeFlashSaleEndsAtForProduct($scenario->product->id, $scenario->country));
    }

    public function test_product_with_neither_mega_deal_nor_flash_sale_reports_false_for_both(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        $flashSaleService = app(FlashSaleService::class);
        $pageBuilderService = app(PageBuilderService::class);

        $this->assertNull($flashSaleService->activeFlashSaleEndsAtForProduct($scenario->product->id, $scenario->country));
        $this->assertFalse($pageBuilderService->isProductInActiveMegaDeal($scenario->product->id, $scenario->country));
    }
}
