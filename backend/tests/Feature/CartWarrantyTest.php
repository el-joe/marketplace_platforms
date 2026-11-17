<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Country;
use App\Models\Customer;
use App\Models\WarrantyPlan;
use App\Services\Customer\CheckoutCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Regression coverage for the "Cart item not found" warranty bug (see
 * docs/plans/cart-warranty-receiver-coupon-fixes.md, Issue 1):
 *
 * - Adding a warranty to an item already in the cart must work as an
 *   independent action, even when the resolved "current" cart for the
 *   request's {country} segment isn't the one the item lives in.
 * - Selecting a warranty plan directly on POST /cart/items must validate
 *   applicability the same way PATCH .../warranty does.
 * - Guest carts must behave the same way as authed carts for item-level
 *   mutations.
 */
class CartWarrantyTest extends TestCase
{
    use RefreshDatabase;

    private function buildScenario(): MarketplaceScenario
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        return $scenario;
    }

    /**
     * A second, unrelated country a request could carry in its {country}
     * URL segment without it having anything to do with the cart the item
     * actually lives in.
     */
    private function secondCountry(): Country
    {
        return Country::create([
            'id' => (string) Str::uuid(),
            'iso_code_2' => 'SA',
            'iso_code_3' => 'SAU',
            'name_ar' => 'السعودية',
            'name_en' => 'Saudi Arabia - '.Str::random(6),
            'currency_code' => 'SAR',
            'vat_rate' => 15.00,
            'is_active' => true,
            'is_launched' => true,
            'cod_available' => true,
            'timezone' => 'Asia/Riyadh',
            'site_code' => 'sa-'.Str::lower(Str::random(6)),
        ]);
    }

    /** A warranty plan tied to a category the scenario's product doesn't belong to. */
    private function inapplicableWarrantyPlan(MarketplaceScenario $scenario): WarrantyPlan
    {
        $otherCategory = Category::create([
            'id' => (string) Str::uuid(),
            'name_en' => 'Unrelated Category '.Str::random(6),
            'name_ar' => 'فئة غير ذات صلة',
            'slug' => 'unrelated-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        return WarrantyPlan::create([
            'category_id' => $otherCategory->id,
            'name_en' => 'Inapplicable Plan',
            'name_ar' => 'خطة غير قابلة للتطبيق',
            'duration_months' => 12,
            'price' => 1000,
            'price_type' => 'flat',
            'currency' => 'AED',
            'is_active' => true,
            'created_by_admin_id' => Admin::factory()->create()->id,
        ]);
    }

    // ── (a) add then patch warranty as a separate call — authed ────────────

    public function test_authed_customer_can_add_warranty_to_an_already_cart_item_as_separate_call(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');

        $addResponse = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/cart/items",
            ['vendor_listing_id' => $scenario->vendorListingFbp->id, 'quantity' => 1],
        );
        $addResponse->assertStatus(201);
        $cartItemId = $addResponse->json('data.item.cart_item_id');
        $this->assertNotNull($cartItemId);

        $patchResponse = $this->patchJson(
            "/api/customer/v1/{$scenario->country->site_code}/cart/items/{$cartItemId}/warranty",
            ['warranty_plan_id' => $scenario->warrantyPlanFlat->id],
        );

        $patchResponse->assertStatus(200);
        $patchResponse->assertJsonPath('data.item.warranty_plan.id', $scenario->warrantyPlanFlat->id);
    }

    // ── regression: PATCH warranty must succeed even under a different {country} segment ──

    public function test_warranty_patch_succeeds_even_when_country_segment_differs_from_add_time(): void
    {
        $scenario = $this->buildScenario();
        $otherCountry = $this->secondCountry();
        $this->actingAs($scenario->customer, 'customer');

        $addResponse = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/cart/items",
            ['vendor_listing_id' => $scenario->vendorListingFbp->id, 'quantity' => 1],
        );
        $addResponse->assertStatus(201);
        $cartItemId = $addResponse->json('data.item.cart_item_id');

        // Same customer, but the request now carries an unrelated country's
        // site_code — this used to 404 because resolveCart() re-derived an
        // empty cart scoped to $otherCountry instead of looking the item up
        // by its own id + ownership.
        $patchResponse = $this->patchJson(
            "/api/customer/v1/{$otherCountry->site_code}/cart/items/{$cartItemId}/warranty",
            ['warranty_plan_id' => $scenario->warrantyPlanFlat->id],
        );

        $patchResponse->assertStatus(200);
        $patchResponse->assertJsonPath('data.item.warranty_plan.id', $scenario->warrantyPlanFlat->id);
    }

    // ── (b) select warranty on add-to-cart directly ─────────────────────────

    public function test_add_to_cart_with_warranty_plan_id_validates_and_applies_it(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');

        $response = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/cart/items",
            [
                'vendor_listing_id' => $scenario->vendorListingFbp->id,
                'quantity' => 1,
                'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
            ],
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.item.warranty_plan.id', $scenario->warrantyPlanFlat->id);
    }

    // ── (c) inapplicable plan rejected from both endpoints ──────────────────

    public function test_add_to_cart_rejects_a_warranty_plan_not_applicable_to_the_product(): void
    {
        $scenario = $this->buildScenario();
        $badPlan = $this->inapplicableWarrantyPlan($scenario);
        $this->actingAs($scenario->customer, 'customer');

        $response = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/cart/items",
            [
                'vendor_listing_id' => $scenario->vendorListingFbp->id,
                'quantity' => 1,
                'warranty_plan_id' => $badPlan->id,
            ],
        );

        $response->assertStatus(422);
        $this->assertSame(0, CartItem::whereNotNull('warranty_plan_id')->count());
    }

    public function test_warranty_patch_rejects_a_plan_not_applicable_to_the_product(): void
    {
        $scenario = $this->buildScenario();
        $badPlan = $this->inapplicableWarrantyPlan($scenario);
        $this->actingAs($scenario->customer, 'customer');

        $addResponse = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/cart/items",
            ['vendor_listing_id' => $scenario->vendorListingFbp->id, 'quantity' => 1],
        );
        $cartItemId = $addResponse->json('data.item.cart_item_id');

        $response = $this->patchJson(
            "/api/customer/v1/{$scenario->country->site_code}/cart/items/{$cartItemId}/warranty",
            ['warranty_plan_id' => $badPlan->id],
        );

        $response->assertStatus(422);
    }

    // ── (d) guest cart resolves item-level actions the same way ────────────

    public function test_guest_cart_can_add_warranty_to_an_already_cart_item_as_separate_call(): void
    {
        $scenario = $this->buildScenario();
        $guestToken = (string) Str::uuid();

        $addResponse = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/cart/items",
            ['vendor_listing_id' => $scenario->vendorListingFbp->id, 'quantity' => 1],
            ['X-Cart-Token' => $guestToken],
        );
        $addResponse->assertStatus(201);
        $cartItemId = $addResponse->json('data.item.cart_item_id');

        $patchResponse = $this->patchJson(
            "/api/customer/v1/{$scenario->country->site_code}/cart/items/{$cartItemId}/warranty",
            ['warranty_plan_id' => $scenario->warrantyPlanFlat->id],
            ['X-Cart-Token' => $guestToken],
        );

        $patchResponse->assertStatus(200);
        $patchResponse->assertJsonPath('data.item.warranty_plan.id', $scenario->warrantyPlanFlat->id);
    }

    public function test_guest_cannot_mutate_another_guests_cart_item(): void
    {
        $scenario = $this->buildScenario();
        $guestTokenA = (string) Str::uuid();
        $guestTokenB = (string) Str::uuid();

        $addResponse = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/cart/items",
            ['vendor_listing_id' => $scenario->vendorListingFbp->id, 'quantity' => 1],
            ['X-Cart-Token' => $guestTokenA],
        );
        $cartItemId = $addResponse->json('data.item.cart_item_id');

        $patchResponse = $this->patchJson(
            "/api/customer/v1/{$scenario->country->site_code}/cart/items/{$cartItemId}/warranty",
            ['warranty_plan_id' => $scenario->warrantyPlanFlat->id],
            ['X-Cart-Token' => $guestTokenB],
        );

        $patchResponse->assertStatus(404);
    }

    public function test_authed_customer_cannot_mutate_another_customers_cart_item(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');

        $addResponse = $this->postJson(
            "/api/customer/v1/{$scenario->country->site_code}/cart/items",
            ['vendor_listing_id' => $scenario->vendorListingFbp->id, 'quantity' => 1],
        );
        $cartItemId = $addResponse->json('data.item.cart_item_id');

        $otherCustomer = Customer::create([
            'name' => 'Other Customer',
            'email' => 'other-customer-'.Str::lower(Str::random(8)).'@example.test',
            'phone' => '+9715'.fake()->numerify('########'),
            'password' => Hash::make('password'),
            'country_id' => $scenario->country->id,
            'status' => 'active',
        ]);
        $this->actingAs($otherCustomer, 'customer');

        $patchResponse = $this->patchJson(
            "/api/customer/v1/{$scenario->country->site_code}/cart/items/{$cartItemId}/warranty",
            ['warranty_plan_id' => $scenario->warrantyPlanFlat->id],
        );

        $patchResponse->assertStatus(404);
    }

    // ── cart summary must price the warranty the way checkout does ─────────

    public function test_cart_summary_includes_the_selected_warranty_like_checkout_does(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');
        $siteCode = $scenario->country->site_code;

        $this->postJson("/api/customer/v1/{$siteCode}/cart/items", [
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 2,
            'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
        ])->assertStatus(201);

        $summary = $this->getJson("/api/customer/v1/{$siteCode}/cart")
            ->assertOk()
            ->json('data.cart.summary');

        // Checkout charges one plan price per cart line (not per unit) plus
        // VAT on it, on top of the products — the cart total must say the same.
        $warrantyPrice = $scenario->warrantyPlanFlat->resolvePrice($scenario->vendorListingFbp->price);
        $calculator = app(CheckoutCalculationService::class);
        $productTax = $calculator->calculateTax($summary['subtotal'] - $summary['discount'], $scenario->country);
        $warrantyTax = $calculator->calculateTax($warrantyPrice, $scenario->country);

        $this->assertEquals($warrantyPrice, $summary['warranty_total']);
        $this->assertEquals($productTax + $warrantyTax, $summary['estimated_tax']);
        $this->assertEquals(
            $summary['subtotal'] - $summary['discount'] + $warrantyPrice + $productTax + $warrantyTax,
            $summary['estimated_total'],
        );
    }

    public function test_cart_summary_has_no_warranty_total_without_a_selected_plan(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');
        $siteCode = $scenario->country->site_code;

        $this->postJson("/api/customer/v1/{$siteCode}/cart/items", [
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
        ])->assertStatus(201);

        $summary = $this->getJson("/api/customer/v1/{$siteCode}/cart")->json('data.cart.summary');

        $this->assertEquals(0, $summary['warranty_total']);
        $this->assertEquals(
            $summary['subtotal'] - $summary['discount'] + $summary['estimated_tax'],
            $summary['estimated_total'],
        );
    }

    public function test_coupon_discounts_the_products_only_and_the_warranty_stays_in_the_total(): void
    {
        $scenario = $this->buildScenario();
        $this->actingAs($scenario->customer, 'customer');
        $siteCode = $scenario->country->site_code;
        $coupon = $scenario->coupons['percentage_platform']; // 10%, all-eligible, active

        $this->postJson("/api/customer/v1/{$siteCode}/cart/items", [
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
        ])->assertStatus(201);

        $this->postJson("/api/customer/v1/{$siteCode}/cart/coupon", ['code' => $coupon->code])
            ->assertOk();

        $summary = $this->getJson("/api/customer/v1/{$siteCode}/cart")->json('data.cart.summary');

        $warrantyPrice = $scenario->warrantyPlanFlat->resolvePrice($scenario->vendorListingFbp->price);
        $calculator = app(CheckoutCalculationService::class);

        $this->assertGreaterThan(0, $summary['discount']);
        // 10% of the product subtotal — the warranty is not discounted.
        $this->assertEquals(round($summary['subtotal'] * 0.10, 2), $summary['discount']);
        $this->assertEquals($warrantyPrice, $summary['warranty_total']);
        $this->assertEquals(
            $summary['subtotal'] - $summary['discount'] + $warrantyPrice
                + $calculator->calculateTax($summary['subtotal'] - $summary['discount'], $scenario->country)
                + $calculator->calculateTax($warrantyPrice, $scenario->country),
            $summary['estimated_total'],
        );
    }

    public function test_guest_cart_total_includes_a_warranty_added_with_the_item_or_afterwards(): void
    {
        $scenario = $this->buildScenario();
        $siteCode = $scenario->country->site_code;
        $headers = ['X-Cart-Token' => (string) Str::uuid()];
        $warrantyPrice = $scenario->warrantyPlanFlat->resolvePrice($scenario->vendorListingFbp->price);

        // Guest carts are not recalculated on GET, so the add/patch responses
        // themselves must leave the stored totals including the warranty.
        $cartItemId = $this->postJson("/api/customer/v1/{$siteCode}/cart/items", [
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'warranty_plan_id' => $scenario->warrantyPlanFlat->id,
        ], $headers)->assertStatus(201)->json('data.item.cart_item_id');

        $withWarranty = $this->getJson("/api/customer/v1/{$siteCode}/cart", $headers)->json('data.cart.summary');
        $this->assertEquals($warrantyPrice, $withWarranty['warranty_total']);
        $this->assertGreaterThan(
            $withWarranty['subtotal'] + $withWarranty['estimated_tax'],
            $withWarranty['estimated_total'],
        );

        $this->patchJson(
            "/api/customer/v1/{$siteCode}/cart/items/{$cartItemId}/warranty",
            ['warranty_plan_id' => null],
            $headers,
        )->assertOk();

        $withoutWarranty = $this->getJson("/api/customer/v1/{$siteCode}/cart", $headers)->json('data.cart.summary');
        $this->assertEquals(0, $withoutWarranty['warranty_total']);
        $this->assertEquals(
            $withoutWarranty['subtotal'] - $withoutWarranty['discount'] + $withoutWarranty['estimated_tax'],
            $withoutWarranty['estimated_total'],
        );
    }
}
