<?php

namespace Tests\Feature\Cart;

use App\Http\Controllers\Customer\ListingDetailController;
use App\Models\AdminListing;
use App\Models\MarketerListing;
use App\Models\VendorListing;
use App\Services\Customer\ListingQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Add-to-cart for every listing type (vendor / admin / marketer), sending the
 * exact payloads the storefront builds in frontend/src/hooks/use-cart.ts.
 */
class AddCartItemListingTypesTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->actingAs($this->s->customer, 'customer');
    }

    /**
     * Mirrors use-cart.ts `addItem`: the id always goes in vendor_listing_id, and
     * is repeated in admin_listing_id when the type is admin.
     *
     * @param  array<string, string>  $headers
     */
    private function addAsFrontend(string $listingId, string $listingType, array $headers = []): TestResponse
    {
        $body = [
            'vendor_listing_id' => $listingId,
            'quantity' => 1,
            'listing_type' => $listingType,
            'warranty_plan_id' => null,
        ];
        if ($listingType === 'admin') {
            $body['admin_listing_id'] = $listingId;
        }

        return $this->withHeaders($headers)
            ->postJson("/api/customer/v1/{$this->s->country->site_code}/cart/items", $body);
    }

    private function assertAdded(TestResponse $response, string $type, string $listingId): void
    {
        $this->assertSame(201, $response->status(), 'API said: '.json_encode($response->json('message'), JSON_UNESCAPED_UNICODE));
        $this->assertSame($type, $response->json('data.item.listing_type'));
        $this->assertSame($listingId, $response->json('data.item.listing_id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function detailListing(VendorListing|AdminListing|MarketerListing $listing, array $headers = []): array
    {
        $request = Request::create('/', 'GET', server: collect($headers)->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all());
        $request->attributes->set('country', $this->s->country);
        app()->instance('app.context', $headers['X-App-Context'] ?? 'main');

        return app(ListingDetailController::class)
            ->showFromListing($request, $this->s->country, $listing)
            ->getData(true)['data'];
    }

    public function test_vendor_listing_is_added(): void
    {
        $id = $this->s->vendorListingFbp->id;
        $this->assertAdded($this->addAsFrontend($id, 'vendor'), 'vendor', $id);
    }

    public function test_marketer_listing_is_added(): void
    {
        $id = $this->s->marketerListing->id;
        $this->assertAdded($this->addAsFrontend($id, 'marketer'), 'marketer', $id);
    }

    public function test_admin_listing_is_added_in_nawy_now(): void
    {
        $id = $this->s->adminListing->id;
        $this->assertAdded($this->addAsFrontend($id, 'admin', ['X-App-Context' => 'nawy_now']), 'admin', $id);
    }

    /**
     * Current business rule (CartService::addAdminItem): platform listings are
     * only purchasable in the nawy_now app context.
     */
    public function test_admin_listing_is_rejected_on_main_site_with_business_message(): void
    {
        $this->addAsFrontend($this->s->adminListing->id, 'admin')
            ->assertUnprocessable()
            ->assertJsonMissingValidationErrors('admin_listing_id')
            ->assertJsonPath('message', __('common.exceptions.cart.admin_listing_not_allowed'));
    }

    /** The original bug: card sent listing_type=admin without admin_listing_id. */
    public function test_admin_payload_without_admin_listing_id_fails_validation(): void
    {
        $this->postJson("/api/customer/v1/{$this->s->country->site_code}/cart/items", [
            'vendor_listing_id' => $this->s->adminListing->id, 'quantity' => 1, 'listing_type' => 'admin',
        ])->assertUnprocessable()->assertJsonValidationErrors('admin_listing_id');
    }

    public function test_express_fbn_vendor_card_is_typed_vendor_and_is_added(): void
    {
        $listing = $this->s->vendorListingFbn;
        $listing->update(['global_system_type' => 'express_fbn']);
        $listing = $listing->fresh(['productVariant.product']);

        $card = app(ListingQueryService::class)->toCardShape($listing, $listing->productVariant->product, $this->s->country);

        $this->assertSame('vendor', $card['listing_type']);
        $this->assertTrue($card['is_express_fbn']);
        $this->assertAdded($this->addAsFrontend($card['listing_id'], $card['listing_type']), 'vendor', $listing->id);
    }

    public function test_product_page_exposes_listing_type_for_each_listing_kind(): void
    {
        $express = $this->s->vendorListingFbn;
        $express->update(['global_system_type' => 'express_fbn']);

        $vendor = $this->detailListing($express->fresh());
        $this->assertSame('vendor', $vendor['listing']['listing_type']);
        $this->assertAdded($this->addAsFrontend($vendor['listing']['listing_id'], $vendor['listing']['listing_type']), 'vendor', $express->id);

        $marketer = $this->detailListing($this->s->marketerListing);
        $this->assertSame('marketer', $marketer['listing']['listing_type']);
        $this->assertAdded($this->addAsFrontend($marketer['listing']['listing_id'], 'marketer'), 'marketer', $this->s->marketerListing->id);

        $admin = $this->detailListing($this->s->adminListing, ['X-App-Context' => 'nawy_now']);
        $this->assertSame('admin', $admin['listing']['listing_type']);
        $this->assertAdded($this->addAsFrontend($admin['listing']['listing_id'], 'admin', ['X-App-Context' => 'nawy_now']), 'admin', $this->s->adminListing->id);
    }

    /** Mirrors use-cart.ts `addItemsBulk` mapping (Bought Together). */
    public function test_bulk_add_with_mixed_listing_types(): void
    {
        $this->withHeaders(['X-App-Context' => 'nawy_now'])
            ->postJson("/api/customer/v1/{$this->s->country->site_code}/cart/items/bulk", ['items' => [
                ['vendor_listing_id' => $this->s->vendorListingFbp->id, 'quantity' => 1],
                ['listing_type' => 'admin', 'admin_listing_id' => $this->s->adminListing->id, 'quantity' => 1],
                ['listing_type' => 'marketer', 'marketer_listing_id' => $this->s->marketerListing->id, 'quantity' => 1],
            ]])
            ->assertSuccessful();

        $cart = $this->s->customer->carts()->latest()->firstOrFail();
        $items = $cart->items()->get();
        $this->assertSame($this->s->vendorListingFbp->id, $items->firstWhere('vendor_listing_id', '!=', null)?->vendor_listing_id);
        $this->assertNotNull($items->firstWhere('admin_listing_id', $this->s->adminListing->id));
        $this->assertNotNull($items->firstWhere('marketer_listing_id', $this->s->marketerListing->id));
    }
}
