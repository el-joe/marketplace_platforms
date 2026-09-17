<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\MarketerListing;
use App\Models\Order;
use App\Services\Customer\CartService;
use App\Services\Marketer\MarketerListingAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AssertsOrderMoney;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * enhancement.md P-15: marketer listings must always resolve to a
 * sellable source (stock, fulfilment, price).
 */
class MarketerListingSourceTest extends TestCase
{
    use RefreshDatabase;
    use AssertsOrderMoney;

    public function test_independent_listing_creation_requires_and_binds_an_active_source(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        $this->actingAs($this->fakeMarketerGuardUser($scenario), 'marketer');

        // No active listing exists for variants[1] except vendorListingFbn.
        $response = $this->post(route('marketer.listings.store'), [
            'product_variant_id' => $scenario->variants[1]->id,
            'country_id'         => $scenario->country->id,
            'price'              => (int) $scenario->vendorListingFbn->getRawOriginal('price'),
            'condition'          => 'new',
        ]);

        $response->assertSessionHasNoErrors();

        $listing = MarketerListing::where('product_variant_id', $scenario->variants[1]->id)
            ->where('marketer_id', $scenario->marketer->id)
            ->first();

        $this->assertNotNull($listing);
        $this->assertSame('vendor_listing', $listing->source_type);
        $this->assertSame($scenario->vendorListingFbn->id, $listing->source_listing_id);
    }

    public function test_independent_listing_creation_rejected_without_an_active_source(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->vendorListingFbp->update(['status' => 'paused']);

        $this->actingAs($this->fakeMarketerGuardUser($scenario), 'marketer');

        // variants[0] only has vendorListingFbp, which is now paused, and no
        // admin listing at all for variants[0] (adminListing is variants[0]
        // too — pause it as well so there is truly no source left).
        $scenario->adminListing->update(['status' => 'paused']);

        $response = $this->post(route('marketer.listings.store'), [
            'product_variant_id' => $scenario->variants[0]->id,
            'country_id'         => $scenario->country->id,
            'price'              => 1000,
            'condition'          => 'new',
        ]);

        $response->assertSessionHasErrors('product_variant_id');
        // The scenario's own campaign-linked listing for variants[0]
        // already exists (invitation_id set); only assert no NEW
        // independent listing (invitation_id null) was created.
        $this->assertSame(0, MarketerListing::where('product_variant_id', $scenario->variants[0]->id)
            ->where('marketer_id', $scenario->marketer->id)
            ->whereNull('invitation_id')
            ->count());
    }

    public function test_price_out_of_bounds_is_rejected_at_creation(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        $this->actingAs($this->fakeMarketerGuardUser($scenario), 'marketer');

        // vendorListingFbn price is 1200; ±20% bound is 960..1440.
        $response = $this->post(route('marketer.listings.store'), [
            'product_variant_id' => $scenario->variants[1]->id,
            'country_id'         => $scenario->country->id,
            'price'              => 5000, // way above the bound
            'condition'          => 'new',
        ]);

        $response->assertSessionHasErrors('price');
    }

    public function test_pausing_source_listing_hides_marketer_listing_within_the_same_request(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        $this->assertSame('active', $scenario->marketerListing->status);

        // Pause the campaign-linked marketer listing's source vendor listing.
        $scenario->vendorListingFbp->update(['status' => 'paused']);

        $scenario->marketerListing->refresh();
        $this->assertSame('paused', $scenario->marketerListing->status);
        $this->assertSame('source_unavailable', $scenario->marketerListing->paused_reason);

        // Storefront query used by ListingQueryService/PDP: no longer visible.
        $this->assertSame(0, MarketerListing::where('id', $scenario->marketerListing->id)
            ->where('status', 'active')
            ->count());

        // Re-activating the source unpauses it again.
        $scenario->vendorListingFbp->update(['status' => 'active']);
        $scenario->marketerListing->refresh();
        $this->assertSame('active', $scenario->marketerListing->status);
        $this->assertNull($scenario->marketerListing->paused_reason);
    }

    public function test_manually_paused_listing_is_not_auto_unpaused(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        $scenario->marketerListing->update(['status' => 'paused', 'paused_reason' => 'manual']);

        // Touch the source (no real change, but triggers observer via a status write).
        $scenario->vendorListingFbp->update(['status' => 'paused']);
        $scenario->vendorListingFbp->update(['status' => 'active']);

        $scenario->marketerListing->refresh();
        $this->assertSame('paused', $scenario->marketerListing->status);
        $this->assertSame('manual', $scenario->marketerListing->paused_reason);
    }

    public function test_stock_depletion_via_inventory_service_pauses_marketer_listing_synchronously(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        app(\App\Services\Inventory\InventoryService::class)->reserve(
            listing: $scenario->vendorListingFbp,
            qty: 50, // deplete all available stock
            referenceType: \App\Enums\InventoryMovementReferenceType::Adjustment->value,
            referenceId: (string) Str::uuid(),
        );

        $scenario->vendorListingFbp->refresh();
        $this->assertSame('out_of_stock', $scenario->vendorListingFbp->status?->value ?? $scenario->vendorListingFbp->status);

        // The listing is unavailable either way: our sync (P-15) would mark
        // it 'paused'/source_unavailable, but here P-14's
        // PauseCampaignsOnLowStock listener (same ListingStockChanged
        // event, registered first) already archives it via the campaign
        // lifecycle before our listener runs — 'archived' is a stricter
        // superset of "not purchasable", so we don't downgrade it back to
        // 'paused'. Either outcome satisfies "not visible/purchasable".
        $scenario->marketerListing->refresh();
        $this->assertContains($scenario->marketerListing->status, ['paused', 'archived']);
        $this->assertNotSame('active', $scenario->marketerListing->status);
    }

    public function test_visible_marketer_listing_can_be_bought_end_to_end_and_reserves_source_stock(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-' . Str::lower(Str::random(6))]);

        $cart = app(CartService::class)->getOrCreateCart(
            $scenario->customer,
            $scenario->country->id,
            $scenario->country->currency_code
        );

        CartItem::create([
            'cart_id'             => $cart->id,
            'marketer_listing_id' => $scenario->marketerListing->id,
            'quantity'            => 1,
            'unit_price'          => (int) $scenario->marketerListing->price,
            'added_at'            => now(),
        ]);

        $this->actingAs($scenario->customer, 'customer');
        $payload = [
            'address_id'                 => $scenario->customerAddress->id,
            'country_payment_gateway_id' => $scenario->countryPaymentGateways['cod']->id,
        ];

        $place = $this->postJson("/api/customer/v1/{$scenario->country->site_code}/checkout/place-order", array_merge($payload, [
            'idempotency_key' => (string) Str::uuid(),
        ]));

        $place->assertStatus(201);

        $orderNumber = $place->json('data.order.order_number') ?? $place->json('data.order_number');
        $order = Order::where('order_number', $orderNumber)->first();
        $this->assertNotNull($order);
        $this->assertMoneyBalanced($order);

        $order->load('subOrders.items');
        $items = $order->subOrders->flatMap(fn ($so) => $so->items);
        $marketerItem = $items->firstWhere('marketer_listing_id', $scenario->marketerListing->id);

        $this->assertNotNull($marketerItem);
        $this->assertSame($scenario->vendorListingFbp->id, $marketerItem->vendor_listing_id);

        // Stock reserved on the SOURCE listing's inventory, not on any
        // phantom marketer-listing inventory.
        $this->assertStock($scenario->vendorListingFbp, 50, 1);
    }

    public function test_price_bound_default_is_twenty_percent(): void
    {
        $service = app(MarketerListingAvailabilityService::class);

        $this->assertSame(20.0, $service->priceBoundPct());
        $this->assertSame([800, 1200], $service->priceBounds(1000));
        $this->assertTrue($service->isPriceInBounds(800, 1000));
        $this->assertTrue($service->isPriceInBounds(1200, 1000));
        $this->assertFalse($service->isPriceInBounds(799, 1000));
        $this->assertFalse($service->isPriceInBounds(1201, 1000));
    }

    /**
     * The `marketer` web guard authenticates a MarketerAdmin (not the
     * Marketer record itself) — Marketer\ListingController resolves the
     * acting marketer via Auth::guard('marketer')->user()->marketer.
     */
    private function fakeMarketerGuardUser(MarketplaceScenario $scenario)
    {
        return \App\Models\MarketerAdmin::create([
            'marketer_id' => $scenario->marketer->id,
            'name'        => 'Test Marketer Admin',
            'email'       => 'marketer-admin-' . Str::lower(Str::random(8)) . '@example.test',
            'password'    => \Illuminate\Support\Facades\Hash::make('password'),
            'is_owner'    => true,
            'is_active'   => true,
        ]);
    }
}
