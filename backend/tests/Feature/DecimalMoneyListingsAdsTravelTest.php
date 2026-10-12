<?php

namespace Tests\Feature;

use App\Helpers\CurrencyFormatter;
use App\Http\Requests\Vendor\CreateListingRequest;
use App\Http\Requests\Vendor\FlashSaleSubmissionRequest;
use App\Models\Admin;
use App\Models\Country;
use App\Models\MarketerAdPackage;
use App\Models\PaidAdBooking;
use App\Models\PaidAdSlot;
use App\Models\ProductCostReference;
use App\Models\SubscriptionPlan;
use App\Models\TravelPackage;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Services\Ads\AdBillingService;
use App\Services\Ads\AdBookingService;
use App\Services\Ads\AdSlotQuoteService;
use App\Services\Marketer\MarketerListingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Listings / ads / travel / subscriptions accept and keep decimal money (DECIMAL(19,4)).
 */
class DecimalMoneyListingsAdsTravelTest extends TestCase
{
    use RefreshDatabase;

    private function slot(Country $country, array $o = []): PaidAdSlot
    {
        return PaidAdSlot::create(array_merge([
            'country_id' => $country->id,
            'created_by_admin_id' => Admin::factory()->create()->id,
            'name' => 'Slot', 'slot_code' => 'slot-'.Str::random(8),
            'pricing_model' => 'fixed_daily', 'base_rate' => 10.5, 'currency' => 'AED',
            'min_booking_days' => 1, 'max_booking_days' => 30, 'is_available' => true,
            'requires_approval' => false, 'max_concurrent' => 1, 'lead_time_days' => 0,
            'allowed_advertisers' => 'vendor',
        ], $o));
    }

    private function cpcBooking(Country $country, Vendor $vendor, PaidAdSlot $slot, float $rate, float $budget): PaidAdBooking
    {
        return PaidAdBooking::create([
            'booking_reference' => 'ADB-D-'.Str::random(6),
            'paid_ad_slot_id' => $slot->id, 'advertiser_type' => 'vendor', 'vendor_id' => $vendor->id,
            'country_id' => $country->id, 'pricing_model' => 'cpc', 'pricing_units' => 0,
            'unit_rate' => $rate, 'agreed_rate' => $rate, 'quoted_amount' => $budget, 'budget_amount' => $budget,
            'tax_amount' => 0, 'booked_from' => Carbon::today(), 'booked_until' => Carbon::today()->addDays(4),
            'currency' => 'AED', 'status' => 'active', 'payment_status' => 'paid', 'payment_method' => 'wallet',
            'started_at' => now(),
        ]);
    }

    public function test_listing_price_rules_accept_two_decimals_and_reject_three(): void
    {
        $rules = (new CreateListingRequest)->rules();
        $base = ['price' => '19.99', 'compare_at_price' => '24.50'];

        $ok = Validator::make($base, array_intersect_key($rules, $base));
        $this->assertFalse($ok->fails(), json_encode($ok->errors()->all()));

        $bad = Validator::make(['price' => '19.999'], array_intersect_key($rules, ['price' => 1]));
        $this->assertTrue($bad->fails());

        $this->assertFalse(Validator::make(['flash_price' => '1.75'], array_intersect_key((new FlashSaleSubmissionRequest)->rules(), ['flash_price' => 1]))->fails());
    }

    public function test_ad_quote_uses_decimal_rate_and_two_decimal_vat(): void
    {
        $country = Country::factory()->create(['vat_rate' => 15, 'currency_code' => 'AED', 'timezone' => 'Asia/Dubai']);
        $slot = $this->slot($country);

        $quote = app(AdSlotQuoteService::class)->quote($slot, Carbon::today()->addDay(), Carbon::today()->addDays(3));

        $this->assertEquals(10.5, $quote['unit_rate']);
        $this->assertEquals(31.5, $quote['subtotal']);
        $this->assertEquals(4.73, $quote['tax_amount']);
        $this->assertEquals(36.23, $quote['total']);
    }

    public function test_cpc_billing_charges_fractional_click_cost_and_refunds_decimal_remainder(): void
    {
        $country = Country::factory()->create(['vat_rate' => 0, 'currency_code' => 'AED', 'timezone' => 'Asia/Dubai']);
        $vendor = Vendor::factory()->create(['country_id' => $country->id]);
        Wallet::create(['owner_type' => 'vendor', 'owner_id' => $vendor->id, 'currency' => 'AED', 'balance' => 1000, 'pending_balance' => 0]);
        $slot = $this->slot($country, ['pricing_model' => 'cpc']);
        $b = $this->cpcBooking($country, $vendor, $slot, 0.75, 10);

        app(AdBillingService::class)->recordDelivery($b, 0, 3);
        $b->refresh();

        $this->assertEquals(2.25, $b->total_charged);
        $this->assertEquals(2.25, (float) $b->charges()->where('type', 'cpc')->value('amount'));

        app(AdBookingService::class)->cancel($b, 'admin', 'x', Admin::factory()->create());
        $refund = $b->charges()->where('type', 'refund')->first();
        $this->assertEquals(-7.75, (float) $refund->amount);
    }

    public function test_marketer_price_bounds_keep_decimals(): void
    {
        config(['marketer.listing_price_bound_pct' => 20]);
        $svc = app(MarketerListingAvailabilityService::class);

        $this->assertEquals([15.99, 23.99], $svc->priceBounds(19.99));
        $this->assertTrue($svc->isPriceInBounds(20.55, 19.99));
        $this->assertFalse($svc->isPriceInBounds(24.00, 19.99));
        $this->assertEquals([800, 1200], $svc->priceBounds(1000));
    }

    public function test_travel_package_price_and_tier_are_decimal(): void
    {
        $pkg = new TravelPackage(['price' => 99.99, 'pricing_tiers_enabled' => false]);

        $this->assertEquals(199.98, $pkg->priceForTravelersCount(2));
        $this->assertSame('AED 99.99', CurrencyFormatter::formatPrice(99.99, 'AED', 'en'));
        $this->assertSame('AED 1,000', CurrencyFormatter::formatPrice(1000, 'AED', 'en'));
    }

    public function test_subscription_plan_and_ad_package_keep_decimal_prices(): void
    {
        $this->assertSame('29.99 USD', (new SubscriptionPlan(['price' => 29.99, 'currency' => 'USD']))->priceFormatted());

        $this->assertEquals(1.5, MarketerAdPackage::vatFor(10, 15));
        $this->assertEquals(2.25, MarketerAdPackage::vatFor(14.99, 15));
    }

    public function test_cost_reference_margin_with_decimal_prices(): void
    {
        $ref = new ProductCostReference(['manufacturer_cost' => 10.25, 'shipping_cost' => 2.5]);

        $this->assertEquals(12.75, $ref->computedLanded());
        $this->assertTrue($ref->isBelowLandedCost(12.5));
        $this->assertFalse($ref->isBelowLandedCost(12.76));
        $this->assertEquals(36.25, $ref->calculateMargin(20.0));
    }
}
