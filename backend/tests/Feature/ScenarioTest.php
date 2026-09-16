<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssertsOrderMoney;
use Tests\Support\MarketplaceScenario;
use Tests\TestCase;

/**
 * P-00 (Phase A — Safety Net): proves the MarketplaceScenario builder can
 * seed the whole minimal world with no SQL errors, and lists one
 * pending/skipped placeholder per P-01..P-13 scenario so later prompts have
 * a named test to fill in.
 */
class ScenarioTest extends TestCase
{
    use RefreshDatabase;
    use AssertsOrderMoney;

    public function test_scenario_builds_every_entity_with_no_sql_errors(): void
    {
        $scenario = MarketplaceScenario::make()->build();

        // Geo
        $this->assertSame('AED', $scenario->country->currency_code);
        $this->assertSame('5.00', (string) $scenario->country->vat_rate);
        $this->assertNotNull($scenario->city->id);
        $this->assertNotNull($scenario->shippingZone->id);
        $this->assertSame($scenario->shippingZone->id, $scenario->city->shipping_zone_id);

        // Category / commission / brand
        $this->assertSame('10.00', (string) $scenario->category->commission_fbp_pct);
        $this->assertSame('12.00', (string) $scenario->category->commission_fbn_pct);
        $this->assertNotNull($scenario->brand->id);
        $this->assertCount(2, $scenario->commissions);

        // Product + 2 variants (one with images, one without)
        $this->assertNotNull($scenario->product->id);
        $this->assertCount(2, $scenario->variants);
        $this->assertSame(2, $scenario->variants[0]->images()->count());
        $this->assertSame(0, $scenario->variants[1]->images()->count());

        // Vendor listings (FBP + FBN) with warehouse inventory
        $this->assertSame('fbm', $scenario->vendorListingFbp->fulfillment_model);
        $this->assertSame('fbn', $scenario->vendorListingFbn->fulfillment_model);
        $this->assertStock($scenario->vendorListingFbp, 50, 0);
        $this->assertStock($scenario->vendorListingFbn, 40, 0);

        // Admin listing with inventory
        $this->assertNotNull($scenario->adminListing->id);
        $this->assertStock($scenario->adminListing, 30, 0);

        // Marketer with accepted invitation + marketer listing
        $this->assertSame('accepted', $scenario->marketerCampaignInvitation->status);
        $this->assertNotNull($scenario->marketerListing->id);

        // Customer with address + wallet
        $this->assertNotNull($scenario->customerAddress->id);
        $this->assertSame(50000, $scenario->customerWallet->balance);

        // Coupons: one per type x funded_by
        $this->assertCount(12, $scenario->coupons);
        foreach (['percentage', 'fixed_amount', 'free_shipping', 'bogo'] as $type) {
            foreach (['platform', 'vendor', 'shared'] as $funder) {
                $this->assertArrayHasKey("{$type}_{$funder}", $scenario->coupons);
            }
        }

        // Warranty plans: flat + percentage
        $this->assertSame('flat', $scenario->warrantyPlanFlat->price_type);
        $this->assertSame('percentage', $scenario->warrantyPlanPercentage->price_type);

        // Payment gateways
        foreach (['cod', 'wallet', 'stripe', 'bank_transfer'] as $code) {
            $this->assertArrayHasKey($code, $scenario->paymentGateways);
            $this->assertArrayHasKey($code, $scenario->countryPaymentGateways);
        }

        // Delivery agent + shipping company supervisor
        $this->assertNotNull($scenario->deliveryAgent->id);
        $this->assertNotNull($scenario->shippingCompanySupervisor->id);
        $this->assertSame($scenario->shippingCompany->id, $scenario->deliveryAgent->shipping_company_id);
    }

    public function test_p01_checkout_calculators_merged_price_shown_equals_price_charged(): void
    {
        $this->markTestSkipped('P-01: merge the two checkout calculators — implemented in that prompt.');
    }

    public function test_p02_place_order_supports_admin_and_marketer_listing_items(): void
    {
        $this->markTestSkipped('P-02: place-order must not crash/refuse admin-listing and marketer-listing items.');
    }

    public function test_p03_order_money_split_vendor_platform_marketer_shipping(): void
    {
        $this->markTestSkipped('P-03: order money split (vendor/platform/marketer/shipping) must be correct.');
    }

    public function test_p04_coupons_rules_enforced_and_usage_reverted(): void
    {
        $this->markTestSkipped('P-04: coupon rules enforcement, free-shipping effect, and usage reversal.');
    }

    public function test_p05_payment_methods_wallet_cod_gateway_bank_transfer(): void
    {
        $this->markTestSkipped('P-05: payment method branches (wallet/COD/gateway/bank transfer) must work.');
    }

    public function test_p06_cancellation_engine_reverses_money_correctly(): void
    {
        $this->markTestSkipped('P-06: cancellation engine (customer/vendor/admin/payment-failure/RTO) reversal.');
    }

    public function test_p07_refunds_no_double_refund_cod_and_return_refunds(): void
    {
        $this->markTestSkipped('P-07: refunds — no double refund, COD refunds, whole sub-order return refunds.');
    }

    public function test_p08_order_status_state_machine_delivery_and_cod_capture(): void
    {
        $this->markTestSkipped('P-08: order/sub-order status state machine, delivery and COD capture.');
    }

    public function test_p09_warranty_lifecycle_purchase_activation_expiry_claims(): void
    {
        $this->markTestSkipped('P-09: warranty lifecycle (purchase, activation, expiry, claims).');
    }

    public function test_p10_return_lifecycle_eligibility_and_restock(): void
    {
        $this->markTestSkipped('P-10: return (listing return) lifecycle eligibility checks and restock.');
    }

    public function test_p11_ledger_and_payouts_reconciliation(): void
    {
        $this->markTestSkipped('P-11: ledger and payouts reconciliation (vendor/admin/marketer/shipping).');
    }

    public function test_p12_marketer_attribution_and_commission_reach_order(): void
    {
        $this->markTestSkipped('P-12: marketer attribution and commission must reach the order.');
    }

    public function test_p13_listing_quantities_single_inventory_service(): void
    {
        $this->markTestSkipped('P-13: one inventory service for every stock increment/decrement.');
    }
}
