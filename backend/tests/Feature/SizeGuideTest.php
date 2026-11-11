<?php

namespace Tests\Feature;

use App\Models\SizeGuide;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Services\Customer\BuyBoxRebuildService;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class SizeGuideTest extends TestCase
{
    use RefreshDatabase;

    private MarketplaceScenario $s;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s = MarketplaceScenario::make()->build();
        $this->s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        $this->s->product->update(['has_custom_attributes' => true]);
    }

    private function owner(Vendor $vendor): VendorAdmin
    {
        $admin = VendorAdmin::create([
            'vendor_id' => $vendor->id, 'name' => 'V', 'email' => 'v-'.Str::random(8).'@example.test',
            'password' => bcrypt('password'), 'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
        foreach (['listings.view', 'listings.edit'] as $name) {
            $admin->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'vendor']));
        }

        return $admin;
    }

    private function guideFor(Vendor $vendor): SizeGuide
    {
        return SizeGuide::factory()->withAbayaRows()->create(['vendor_id' => $vendor->id, 'name' => 'Abayas']);
    }

    private function api(string $path): string
    {
        return "/api/customer/v1/{$this->s->country->site_code}/{$path}";
    }

    public function test_vendor_creates_guide_from_editor_payload_and_cannot_touch_another_vendors_guide(): void
    {
        $this->actingAs($this->owner($this->s->vendor), 'vendor');

        $this->post(route('partner.size-guides.store'), [
            'name' => 'Abayas',
            'unit' => 'cm',
            'columns' => json_encode([['key' => 'length', 'label_en' => 'Length', 'label_ar' => 'الطول', 'is_measurement' => true]]),
            'rows' => json_encode([
                ['size_label' => '52', 'values' => ['length' => ' 132 ', 'ghost' => 'dropped']],
                ['size_label' => '54', 'values' => ['length' => '137']],
            ]),
        ])->assertRedirect();

        $guide = SizeGuide::where('vendor_id', $this->s->vendor->id)->with('rows')->sole();
        $this->assertSame(['52', '54'], $guide->rows->pluck('size_label')->all());
        $this->assertSame(['length' => '132'], $guide->rows->first()->values);

        $this->post(route('partner.size-guides.store'), [
            'name' => 'Dupes', 'unit' => 'cm',
            'columns' => json_encode([['key' => 'length', 'label_en' => 'Length']]),
            'rows' => json_encode([['size_label' => '52', 'values' => ['length' => '1']], ['size_label' => '52', 'values' => ['length' => '2']]]),
        ])->assertSessionHasErrors('rows.1.size_label');

        $foreign = $this->guideFor(Vendor::factory()->create());
        $this->get(route('partner.size-guides.edit', $foreign))->assertNotFound();
        $this->delete(route('partner.size-guides.destroy', $foreign))->assertNotFound();
        $this->putJson(route('partner.listings.size-guide.update', $this->s->vendorListingFbp->id), ['size_guide_id' => $foreign->id])
            ->assertUnprocessable();
    }

    public function test_pdp_exposes_attribute_types_and_the_listings_size_guide_only_while_custom_attributes_are_on(): void
    {
        $this->s->product->customAttributes()->create(['label' => 'Fit', 'type' => 'select', 'options' => ['Loose', 'Fitted'], 'is_required' => true]);
        $guide = $this->guideFor($this->s->vendor);
        $listing = $this->s->vendorListingFbp;

        $this->actingAs($this->owner($this->s->vendor), 'vendor');
        $this->putJson(route('partner.listings.size-guide.update', $listing->id), ['size_guide_id' => $guide->id])->assertOk();

        $url = $this->api("products/v-{$listing->id}");
        $product = $this->getJson($url)->assertOk()->json('data.product');
        $this->assertSame('select', $product['custom_attributes'][0]['type']);
        $this->assertSame(['Loose', 'Fitted'], $product['custom_attributes'][0]['options']);
        $this->assertSame($guide->id, $product['size_guide']['id']);
        $this->assertSame('54', $product['size_guide']['rows'][2]['size']);

        $this->getJson($this->api("size-guides/{$guide->id}"))->assertOk()->assertJsonPath('data.vendor.id', $this->s->vendor->id);

        $this->s->product->update(['has_custom_attributes' => false]);
        $this->assertNull($this->getJson($url)->json('data.product.size_guide'));
        $this->getJson($this->api("size-guides/{$guide->id}"))->assertNotFound();
    }

    public function test_switching_custom_attributes_off_stops_enforcing_leftover_required_fields(): void
    {
        $this->s->product->customAttributes()->create(['label' => 'Length', 'type' => 'number', 'is_required' => true]);
        $this->s->product->update(['has_custom_attributes' => false]);

        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreateCart($this->s->customer, $this->s->country->id, $this->s->country->currency_code);
        $item = $cartService->addItem($cart, $this->s->vendorListingFbp->id, 1, null, $this->s->country->id);

        $this->assertSame(0, $item->customAttributeValues()->count());
    }

    public function test_category_grid_filters_and_facets_by_guide_size_and_made_to_measure(): void
    {
        $this->s->vendorListingFbp->update(['size_guide_id' => $this->guideFor($this->s->vendor)->id]);
        app(BuyBoxRebuildService::class)->rebuildCountry($this->s->country);
        $category = $this->s->category->id;

        $listingIds = fn (array $query) => collect($this->getJson($this->api('products?'.http_build_query(['category' => $category] + $query)))
            ->assertOk()->json('data.items'))->pluck('listing_id')->all();

        $this->assertSame([$this->s->vendorListingFbp->id], $listingIds(['attributes' => ['guide_size' => ['54']]]));
        $this->assertSame([], $listingIds(['attributes' => ['guide_size' => ['99']]]));

        $facets = collect($this->getJson($this->api('products?category='.$category))->json('data.facets.attributes'))->keyBy('code');
        $this->assertSame(['50', '52', '54', '56', '58', '60', '62'], collect($facets['guide_size']['values'])->pluck('value.en')->all());
        $this->assertSame(1, $facets['made_to_measure']['values'][0]['count']);

        $this->s->product->update(['has_custom_attributes' => false]);
        app(BuyBoxRebuildService::class)->rebuildCountry($this->s->country);
        $codes = collect($this->getJson($this->api('products?category='.$category))->json('data.facets.attributes'))->pluck('code');
        $this->assertNotContains('made_to_measure', $codes);
        $this->assertNotContains('guide_size', $codes);
    }
}
