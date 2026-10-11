<?php

namespace Tests\Feature\Cart;

use App\Enums\MarketerListingStatus;
use App\Models\CartItem;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

class MarketerListingCartRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function cartItemFor(MarketplaceScenario $s): CartItem
    {
        $cart = app(CartService::class)->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);

        return CartItem::create([
            'cart_id' => $cart->id, 'marketer_listing_id' => $s->marketerListing->id, 'quantity' => 1,
            'unit_price' => (int) $s->marketerListing->price, 'added_at' => now(),
        ]);
    }

    public function test_active_marketer_listing_item_survives_cart_recalculation(): void
    {
        $s = MarketplaceScenario::make()->build();
        $item = $this->cartItemFor($s);

        app(CartService::class)->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);

        $this->assertModelExists($item);
    }

    public function test_paused_marketer_listing_item_is_removed_on_cart_recalculation(): void
    {
        $s = MarketplaceScenario::make()->build();
        $item = $this->cartItemFor($s);
        $s->marketerListing->update(['status' => MarketerListingStatus::Paused]);

        app(CartService::class)->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);

        $this->assertModelMissing($item);
    }
}
