<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\ShippingMethod;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Cart / checkout shipment groups carry real delivery data so the storefront can show
 * "Get it by {date}" and an "order within" countdown instead of placeholder text.
 */
class CartShippingGroupDeliveryInfoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingMethodPayload(?string $cutoff): array
    {
        $scenario = MarketplaceScenario::make()->build();
        $scenario->country->update(['timezone' => 'Asia/Dubai']);

        $method = ShippingMethod::create([
            'name' => 'Standard',
            'code' => 'std-'.Str::lower(Str::random(6)),
            'min_delivery_days' => 1,
            'max_delivery_days' => 3,
            'handling_time_hours' => 0,
            'order_cutoff_time' => $cutoff,
        ]);

        $service = app(CartService::class);
        $cart = $service->getOrCreateCart($scenario->customer, $scenario->country->id, $scenario->country->currency_code);

        CartItem::create([
            'cart_id' => $cart->id,
            'vendor_listing_id' => $scenario->vendorListingFbp->id,
            'quantity' => 1,
            'unit_price' => (int) $scenario->vendorListingFbp->price,
            'added_at' => now(),
            'selected_shipping_method_id' => $method->id,
        ]);

        return $service->buildShippingGroups($cart->fresh(), $scenario->country->id)[0]['shipping_method'];
    }

    public function test_group_exposes_latest_delivery_date_and_future_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 10:00:00', 'Asia/Dubai'));

        $payload = $this->shippingMethodPayload('14:00:00');

        $this->assertSame('2026-11-26', $payload['estimated_delivery_date']);
        $this->assertSame(
            Carbon::parse('2026-11-23 14:00:00', 'Asia/Dubai')->toIso8601String(),
            $payload['order_cutoff_at'],
        );
    }

    public function test_cutoff_is_null_once_passed_and_the_estimate_moves_a_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 15:00:00', 'Asia/Dubai'));

        $payload = $this->shippingMethodPayload('14:00:00');

        $this->assertNull($payload['order_cutoff_at']);
        $this->assertSame('2026-11-27', $payload['estimated_delivery_date']);
    }

    public function test_cutoff_is_null_when_the_method_has_none(): void
    {
        $this->assertNull($this->shippingMethodPayload(null)['order_cutoff_at']);
    }
}
