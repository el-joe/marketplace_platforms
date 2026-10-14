<?php

namespace Tests\Feature\Commission;

use App\Models\CartItem;
use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Order items carry a commission breakdown (rate, fixed) next to the charged amount. The amount comes from
 * CheckoutPricingEngine (FBN only when the sub-order's fulfillment_model is fbn); the breakdown is a display
 * snapshot from CheckoutCalculationService (FBN when the listing's global_system_type is express_fbn). When the
 * two disagree the breakdown no longer explains the amount. Fixture: category FBP = 10% + 200, FBN = 12% + 300,
 * listing fulfillment_model = fbm, global_system_type = express_fbn (the column default).
 */
class CategoryOnlyCommissionTest extends TestCase
{
    use RefreshDatabase;

    private function placeOrder(bool $withLegacyOverrides): OrderItem
    {
        Notification::fake();
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        Commission::query()->delete();
        $s->vendor->forceFill(['commission_rate' => 0])->save();
        if ($withLegacyOverrides) {
            Commission::create([
                'vendor_id' => null, 'category_id' => null, 'rate_pct' => 50, 'rate_type' => 'flat', 'priority' => 100,
                'min_commission' => 0, 'effective_from' => now()->subDay()->toDateString(),
            ]);
            $s->vendor->forceFill(['commission_rate' => 40])->save();
        }

        $this->actingAs($s->customer, 'customer');
        $cart = app(CartService::class)->getOrCreateCart($s->customer, $s->country->id, $s->country->currency_code);
        CartItem::create([
            'cart_id' => $cart->id, 'vendor_listing_id' => $s->vendorListingFbp->id, 'quantity' => 3,
            'unit_price' => (int) $s->vendorListingFbp->getRawOriginal('price'), 'added_at' => now(),
        ]);
        $response = $this->postJson("/api/customer/v1/{$s->country->site_code}/checkout/place-order", [
            'address_id' => $s->customerAddress->id,
            'country_payment_gateway_id' => $s->countryPaymentGateways['cod']->id,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

        $order = Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))->firstOrFail();

        return $order->subOrders()->firstOrFail()->items()->firstOrFail();
    }

    public function test_commission_reads_the_category_settings(): void
    {
        // category FBP: 10% of 3000 + 200 x 3
        $this->assertSame(900, (int) $this->placeOrder(false)->commission_amount);
    }

    public function test_legacy_rule_rows_and_vendor_rate_no_longer_change_the_commission(): void
    {
        $this->assertSame(900, (int) $this->placeOrder(true)->commission_amount);
    }
}
