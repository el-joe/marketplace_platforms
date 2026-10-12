<?php

namespace Tests\Feature\Commission;

use App\Models\CartItem;
use App\Models\Commission;
use App\Models\CountryCategory;
use App\Models\Order;
use App\Services\Customer\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * Client rules: FBM has its own commission pair (falls back to FBP when empty), the fixed fee may be fractional
 * (1.9 in the order country's currency), and VAT at the order country's rate is charged on the platform commission
 * and deducted from the seller payout.
 */
class FbmFractionalFeeAndCommissionVatTest extends TestCase
{
    use RefreshDatabase;

    private function placeThreeUnitFbmOrder(?float $fbmPct, ?string $fbmFixed): Order
    {
        Notification::fake();
        $s = MarketplaceScenario::make()->build();
        $s->country->update(['site_code' => 'ae-'.Str::lower(Str::random(6)), 'vat_rate' => 5]);
        Commission::query()->delete();
        $s->vendor->forceFill(['commission_rate' => 0])->save();
        CountryCategory::where('category_id', $s->category->id)->delete();
        // fixture category: FBP = 10% + 200, FBN = 12% + 300; FBM pair under test
        $s->category->forceFill(['commission_fbm_pct' => $fbmPct, 'commission_fbm_fixed' => $fbmFixed])->save();

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

        return Order::where('order_number', $response->json('data.order.order_number') ?? $response->json('data.order_number'))->firstOrFail();
    }

    public function test_fbm_uses_its_own_pair_with_a_fractional_fixed_fee_and_vat_is_billed_to_the_seller(): void
    {
        $sub = $this->placeThreeUnitFbmOrder(5, '1.9')->subOrders()->firstOrFail();

        // 5% of 3000 = 150, plus 1.9 x 3 units = 5.7
        $this->assertEqualsWithDelta(155.7, (float) $sub->platform_commission, 0.0001);
        // 5% VAT on the commission = 7.785
        $this->assertEqualsWithDelta(7.785, (float) $sub->commission_vat, 0.0001);
        $this->assertEqualsWithDelta(5.0, (float) $sub->commission_vat_rate, 0.0001);
        $this->assertEqualsWithDelta(
            3000 - 155.7 - 7.785 - (float) $sub->gateway_fee - (float) $sub->vendor_contribution_amount,
            (float) $sub->vendor_payout,
            0.0001
        );
    }

    public function test_fbm_without_its_own_pair_falls_back_to_fbp(): void
    {
        $sub = $this->placeThreeUnitFbmOrder(null, null)->subOrders()->firstOrFail();

        // FBP pair: 10% of 3000 + 200 x 3
        $this->assertEqualsWithDelta(900.0, (float) $sub->platform_commission, 0.0001);
        $this->assertEqualsWithDelta(45.0, (float) $sub->commission_vat, 0.0001);
    }
}
