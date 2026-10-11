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
class CommissionSnapshotInvariantTest extends TestCase
{
    use RefreshDatabase;

    private function placeThreeUnitFbmOrder(): OrderItem
    {
        Notification::fake();
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6))]);
        Commission::query()->delete();
        $s->vendor->forceFill(['commission_rate' => 0])->save();

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

    public function test_charged_commission_for_an_fbm_listing_uses_the_fbp_pair(): void
    {
        $item = $this->placeThreeUnitFbmOrder();

        // 10% of 3000 + 200 fixed x 3 units
        $this->assertSame(900, (int) $item->commission_amount);
    }

    public function test_characterization_stored_breakdown_currently_follows_global_system_type_not_the_charge_rule(): void
    {
        $item = $this->placeThreeUnitFbmOrder();

        // Pins today's behaviour so any fix is a deliberate, reviewed change to persisted order-item values.
        $this->assertSame(12.0, (float) $item->commission_rate_pct, 'snapshot shows the FBN percentage');
        $this->assertSame(300, (int) $item->commission_fixed, 'snapshot shows the FBN fixed amount');
    }

    public function test_stored_breakdown_explains_the_charged_amount(): void
    {
        $this->markTestSkipped(
            'Known issue awaiting a decision: the order-item snapshot (12% + 300, FBN pair) disagrees with the amount charged '
            .'(900 = 10% + 200 x 3, FBP pair). Fixing it changes persisted values for new orders only and needs sign-off; see '
            .'CheckoutController L1211/L1229 and CheckoutCalculationService::calculateCommission.'
        );

        $item = $this->placeThreeUnitFbmOrder();
        $expected = (int) floor((int) $item->line_subtotal * (float) $item->commission_rate_pct / 100) + (int) $item->commission_fixed * (int) $item->quantity;

        $this->assertSame($expected, (int) $item->commission_amount);
    }
}
