<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class CartShowResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_cart_still_loads_when_a_vendor_listing_in_it_was_deleted(): void
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);

        $cart = app(CartService::class)->getOrCreateCart(
            $scenario->customer,
            $scenario->country->id,
            $scenario->country->currency_code
        );

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->price,
            'added_at' => now(),
        ]);

        $scenario->vendorListingFbp->delete();

        $this->actingAs($scenario->customer, 'customer');
        $response = $this->getJson("/api/customer/v1/{$scenario->country->site_code}/cart");

        $response->assertOk();
        $groups = $response->json('data.shipping_groups') ?? $response->json('shipping_groups');
        $line = collect($groups ?? [])->flatMap(fn ($group) => $group['items'])->firstWhere('id', $item->id);
        $this->assertTrue($line === null || $line['vendor'] === null);
    }
}
