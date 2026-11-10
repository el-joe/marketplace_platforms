<?php

namespace Tests\Feature\Vendor;

use App\Enums\FulfillmentModel;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorListing;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CountryContractFixtures;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Partner > Create listing: new listings are always FBN, the FBM customer payment method is
 * platform-managed (null) and "I cover remaining delivery costs" is off — whatever the request
 * sends. Editing a listing must not wipe the stored payment gateway / delivery-coverage values.
 */
class PartnerListingFbnDefaultsTest extends TestCase
{
    use CountryContractFixtures;
    use RefreshDatabase;

    private MarketplaceScenario $scenario;

    private Vendor $vendor;

    private VendorAdmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->scenario = MarketplaceScenario::make()->build();

        $this->vendor = $this->vendorIn($this->scenario->country, 'FbnSeller');
        $this->owner = $this->ownerFor($this->vendor);
        foreach (['listings.view', 'listings.create', 'listings.edit'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'vendor']);
            $this->owner->givePermissionTo($permission);
        }
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'product_variant_id' => $this->scenario->variants[0]->id,
            'country_id' => $this->scenario->country->id,
            'price' => '150.50',
            'condition' => 'new',
            'warehouse_id' => $this->scenario->platformWarehouse->id,
            'initial_quantity' => 3,
            'declared_weight_grams' => 500,
            'handling_class' => 'standard',
        ], $overrides);
    }

    private function tamperedPayload(array $overrides = []): array
    {
        return $this->validPayload(array_merge([
            'fulfillment_model' => 'fbm',
            'fbm_payment_gateway_id' => $this->scenario->paymentGateways['bank_transfer']->id,
            'vendor_covers_delivery' => '1',
        ], $overrides));
    }

    private function createdListing(): VendorListing
    {
        return VendorListing::where('vendor_id', $this->vendor->id)
            ->where('product_variant_id', $this->scenario->variants[0]->id)
            ->sole();
    }

    public function test_store_forces_fbn_null_gateway_and_no_vendor_covered_delivery_on_a_plain_request(): void
    {
        $this->actingAs($this->owner, 'vendor')
            ->postJson(route('partner.listings.store'), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('success', true);

        $listing = $this->createdListing();
        $this->assertSame(FulfillmentModel::Fbn, $listing->fulfillment_model);
        $this->assertNull($listing->fbm_payment_gateway_id);
        $this->assertFalse($listing->vendor_covers_delivery);
    }

    public function test_store_ignores_tampered_fulfillment_model_payment_gateway_and_delivery_coverage(): void
    {
        // A platform (FBN) warehouse with fulfillment_model=fbm would be rejected as incompatible if the
        // submitted value were trusted, so a 200 here also proves the server forced 'fbn' before that check.
        $this->actingAs($this->owner, 'vendor')
            ->postJson(route('partner.listings.store'), $this->tamperedPayload())
            ->assertOk()
            ->assertJsonPath('success', true);

        $listing = $this->createdListing();
        $this->assertSame('fbn', $listing->getRawOriginal('fulfillment_model'));
        $this->assertNull($listing->fbm_payment_gateway_id);
        $this->assertFalse($listing->vendor_covers_delivery);
        $this->assertSame(0, (int) $listing->getRawOriginal('vendor_covers_delivery'));
    }

    public function test_store_ignores_cross_dock_and_garbage_values_for_the_managed_fields(): void
    {
        $this->actingAs($this->owner, 'vendor')
            ->postJson(route('partner.listings.store'), $this->tamperedPayload([
                'fulfillment_model' => 'cross_dock',
                'fbm_payment_gateway_id' => 'not-a-uuid',
                'vendor_covers_delivery' => 'yes-please',
            ]))
            ->assertOk();

        $listing = $this->createdListing();
        $this->assertSame(FulfillmentModel::Fbn, $listing->fulfillment_model);
        $this->assertNull($listing->fbm_payment_gateway_id);
        $this->assertFalse($listing->vendor_covers_delivery);
    }

    public function test_store_validates_the_warehouse_against_fbn_even_when_fbm_is_submitted(): void
    {
        $ownWarehouse = Warehouse::create([
            'country_id' => $this->scenario->country->id,
            'name' => 'Seller Shed',
            'code' => 'SS-'.Str::upper(Str::random(6)),
            'type' => 'seller_owned',
            'owner_vendor_id' => $this->vendor->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->owner, 'vendor')
            ->postJson(route('partner.listings.store'), $this->tamperedPayload(['warehouse_id' => $ownWarehouse->id]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, VendorListing::where('vendor_id', $this->vendor->id)->count());
    }

    public function test_create_page_has_fixed_fbn_and_no_payment_method_or_delivery_coverage_fields(): void
    {
        $ownWarehouse = Warehouse::create([
            'country_id' => $this->scenario->country->id,
            'name' => 'Seller Shed',
            'code' => 'SS-'.Str::upper(Str::random(6)),
            'type' => 'seller_owned',
            'owner_vendor_id' => $this->vendor->id,
            'is_active' => true,
        ]);

        $html = $this->actingAs($this->owner, 'vendor')
            ->get(route('partner.listings.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<input type="hidden" name="fulfillment_model" value="fbn">/', $html);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*name="fulfillment_model"/', $html);
        $this->assertStringNotContainsString('name="fbm_payment_gateway_id"', $html);
        $this->assertStringNotContainsString('name="vendor_covers_delivery"', $html);
        $this->assertStringNotContainsString('vendor-covers-delivery-field', $html);

        // Only FBN-compatible (platform) warehouses are offered, since store() rejects any other.
        $this->assertStringContainsString('value="'.$this->scenario->platformWarehouse->id.'"', $html);
        $this->assertStringNotContainsString('value="'.$ownWarehouse->id.'"', $html);
    }

    private function existingListing(array $attributes = []): VendorListing
    {
        return VendorListing::create(array_merge([
            'id' => (string) Str::uuid(),
            'vendor_id' => $this->vendor->id,
            'product_variant_id' => $this->scenario->variants[1]->id,
            'country_id' => $this->scenario->country->id,
            'price' => 200.00,
            'currency' => 'AED',
            'condition' => 'new',
            'fulfillment_model' => 'fbm',
            'fbm_payment_gateway_id' => $this->scenario->paymentGateways['bank_transfer']->id,
            'vendor_covers_delivery' => true,
            'status' => 'paused',
            'declared_weight_grams' => 400,
            'handling_class' => 'standard',
        ], $attributes));
    }

    public function test_update_keeps_the_stored_payment_gateway_and_delivery_coverage(): void
    {
        $gatewayId = $this->scenario->paymentGateways['bank_transfer']->id;
        $listing = $this->existingListing();

        $this->actingAs($this->owner, 'vendor')
            ->put(route('partner.listings.update', $listing), [
                'price' => '210.00',
                'condition' => 'like_new',
                'fulfillment_model' => 'fbm',
                'declared_weight_grams' => 450,
                'handling_class' => 'standard',
                // Removed from the form; even if sent they must not change anything.
                'fbm_payment_gateway_id' => (string) Str::uuid(),
                'vendor_covers_delivery' => '0',
            ])
            ->assertRedirect(route('partner.listings.show', $listing))
            ->assertSessionHasNoErrors();

        $listing->refresh();
        $this->assertSame('like_new', $listing->condition);
        $this->assertSame(450, (int) $listing->declared_weight_grams);
        $this->assertSame($gatewayId, $listing->fbm_payment_gateway_id);
        $this->assertTrue($listing->vendor_covers_delivery);
    }

    public function test_update_without_the_removed_fields_does_not_reset_them(): void
    {
        $gatewayId = $this->scenario->paymentGateways['bank_transfer']->id;
        $listing = $this->existingListing();

        $this->actingAs($this->owner, 'vendor')
            ->put(route('partner.listings.update', $listing), [
                'price' => '199.00',
                'condition' => 'new',
                'fulfillment_model' => 'fbm',
                'declared_weight_grams' => 400,
                'handling_class' => 'fragile',
            ])
            ->assertSessionHasNoErrors();

        $listing->refresh();
        $this->assertSame('fragile', $listing->handling_class);
        $this->assertSame($gatewayId, $listing->fbm_payment_gateway_id);
        $this->assertTrue($listing->vendor_covers_delivery);
    }

    public function test_edit_page_preselects_the_current_fulfillment_model_and_hides_the_removed_fields(): void
    {
        foreach (['fbm', 'cross_dock'] as $model) {
            VendorListing::where('vendor_id', $this->vendor->id)->forceDelete();
            $listing = $this->existingListing(['fulfillment_model' => $model]);

            $html = $this->actingAs($this->owner, 'vendor')
                ->get(route('partner.listings.edit', $listing))
                ->assertOk()
                ->getContent();

            $this->assertMatchesRegularExpression('/<option value="'.$model.'"\s+selected/', $html, "{$model} should be preselected");
            $this->assertDoesNotMatchRegularExpression('/<option value="fbn"\s+selected/', $html);
            $this->assertStringNotContainsString('name="fbm_payment_gateway_id"', $html);
            $this->assertStringNotContainsString('name="vendor_covers_delivery"', $html);
        }
    }
}
