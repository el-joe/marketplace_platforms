<?php

namespace Tests\Feature\Ads;

use App\Models\Admin;
use App\Models\Country;
use App\Models\PaidAdBooking;
use App\Models\PaidAdSlot;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Services\Ads\AdBillingService;
use App\Services\Ads\AdBookingService;
use App\Services\FinancialReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdBillingSeparationTest extends TestCase
{
    use RefreshDatabase;

    private Country $country;

    private Vendor $vendor;

    private PaidAdSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->country = Country::factory()->create(['vat_rate' => 0, 'currency_code' => 'AED', 'timezone' => 'Asia/Dubai']);
        $this->vendor = Vendor::factory()->create(['country_id' => $this->country->id]);
        Wallet::create(['owner_type' => 'vendor', 'owner_id' => $this->vendor->id, 'currency' => 'AED', 'balance' => 1000000, 'pending_balance' => 0]);
        $this->slot = PaidAdSlot::create([
            'country_id' => $this->country->id,
            'created_by_admin_id' => Admin::factory()->create()->id,
            'name' => 'Slot', 'slot_code' => 'slot-'.Str::random(8),
            'pricing_model' => 'fixed_daily', 'base_rate' => 1000, 'currency' => 'AED',
            'min_booking_days' => 1, 'max_booking_days' => 30, 'is_available' => true,
            'requires_approval' => false, 'max_concurrent' => 1, 'lead_time_days' => 0,
            'allowed_advertisers' => 'vendor',
        ]);
    }

    private function booking(array $o = []): PaidAdBooking
    {
        return PaidAdBooking::create(array_merge([
            'booking_reference' => 'ADB-T-'.Str::random(6),
            'paid_ad_slot_id' => $this->slot->id,
            'advertiser_type' => 'vendor',
            'vendor_id' => $this->vendor->id,
            'country_id' => $this->country->id,
            'pricing_model' => 'fixed_daily',
            'pricing_units' => 5,
            'unit_rate' => 1000,
            'agreed_rate' => 1000,
            'quoted_amount' => 5000,
            'tax_amount' => 0,
            'booked_from' => Carbon::today(),
            'booked_until' => Carbon::today()->addDays(4),
            'currency' => 'AED',
            'status' => 'active',
            'payment_status' => 'paid',
            'payment_method' => 'wallet',
            'started_at' => now(),
        ], $o));
    }

    private function cpc(int $rate = 100, int $budget = 500): PaidAdBooking
    {
        return $this->booking([
            'pricing_model' => 'cpc', 'unit_rate' => $rate, 'agreed_rate' => $rate,
            'quoted_amount' => $budget, 'budget_amount' => $budget,
        ]);
    }

    public function test_fixed_wallet_payment_sets_subscription_charged_only(): void
    {
        $b = $this->booking(['status' => 'approved', 'payment_status' => 'unpaid', 'tax_amount' => 250]);
        app(AdBillingService::class)->collect($b);
        $b->refresh();
        $this->assertSame(5000, $b->subscription_charged);
        $this->assertSame(0, $b->total_charged);
        $this->assertSame(5000, $b->total_spend);
        $this->assertSame(5250, (int) $b->charges()->where('type', 'fixed')->value('amount'));
    }

    public function test_fixed_booking_survives_clicks_and_impressions(): void
    {
        $b = $this->booking(['subscription_charged' => 5000]);
        $billing = app(AdBillingService::class);
        $billing->recordDelivery($b, 0, 1);
        $billing->recordDelivery($b, 1000, 0);
        $b->refresh();
        $this->assertSame('active', $b->status->value);
        $this->assertSame(0, $b->total_charged);
        $this->assertSame(1, $b->clicks_delivered);
        $this->assertSame(1000, $b->impressions_delivered);
        $this->assertSame(0, $b->charges()->whereIn('type', ['cpc', 'cpm'])->count());
    }

    public function test_cpc_budget_exhausted_completes_with_no_refund(): void
    {
        $b = $this->cpc();
        app(AdBillingService::class)->recordDelivery($b, 0, 5);
        $b->refresh();
        $this->assertSame('completed', $b->status->value);
        $this->assertSame(500, $b->total_charged);
        $this->assertSame(0, $b->subscription_charged);
        $this->assertSame(0, $b->charges()->where('type', 'refund')->count());
    }

    public function test_cpc_three_clicks_then_cancel_refunds_unspent(): void
    {
        $b = $this->cpc();
        app(AdBillingService::class)->recordDelivery($b, 0, 3);
        $b->refresh();
        $this->assertSame(300, $b->total_charged);
        app(AdBookingService::class)->cancel($b, 'admin', 'x', Admin::factory()->create());
        $refund = $b->charges()->where('type', 'refund')->first();
        $this->assertNotNull($refund);
        $this->assertEquals(-200, $refund->amount);
    }

    public function test_cpm_blocks_charge_unit_rate_and_cap_at_budget(): void
    {
        $b = $this->booking([
            'pricing_model' => 'cpm', 'unit_rate' => 500, 'agreed_rate' => 500,
            'quoted_amount' => 1200, 'budget_amount' => 1200,
        ]);
        app(AdBillingService::class)->recordDelivery($b, 5000, 0);
        $b->refresh();
        $amounts = $b->charges()->where('type', 'cpm')->orderBy('created_at')->pluck('amount')->map(fn ($a) => (int) $a)->all();
        $this->assertSame([500, 500, 200], $amounts);
        $this->assertSame(1200, $b->total_charged);
        $this->assertSame('completed', $b->status->value);
    }

    public function test_offline_payment_sets_subscription_charged(): void
    {
        $b = $this->booking(['status' => 'approved', 'payment_status' => 'unpaid', 'payment_method' => 'offline']);
        app(AdBookingService::class)->markOfflinePaid($b, Admin::factory()->create(), null, 'proofs/x.png');
        $b->refresh();
        $this->assertSame(5000, $b->subscription_charged);
        $this->assertSame(0, $b->total_charged);
    }

    public function test_fixed_cancel_refund_includes_prorated_tax_and_matches_ledger(): void
    {
        $b = $this->booking([
            'booked_from' => Carbon::today()->subDays(3), 'booked_until' => Carbon::today()->addDays(6),
            'pricing_units' => 10, 'quoted_amount' => 10000, 'tax_amount' => 500, 'subscription_charged' => 10000,
        ]);
        \App\Models\PaidAdCharge::create([
            'paid_ad_booking_id' => $b->id, 'advertiser_type' => 'vendor', 'vendor_id' => $this->vendor->id,
            'country_id' => $this->country->id, 'currency' => 'AED', 'type' => 'fixed',
            'amount' => 10500, 'tax_amount' => 500, 'settlement' => 'wallet', 'settled_at' => now(),
        ]);
        app(AdBookingService::class)->cancel($b, 'admin', 'x', Admin::factory()->create());
        $refund = $b->charges()->where('type', 'refund')->first();
        $this->assertEquals(-intdiv(10500 * 6, 10), $refund->amount);
    }

    public function test_backfill_moves_fixed_row_only(): void
    {
        $fixed = $this->booking(['total_charged' => 5000]);
        $cpc = $this->cpc();
        $cpc->update(['total_charged' => 300]);

        $migration = require database_path('migrations/2026_09_19_200000_add_subscription_charged_to_paid_ad_bookings.php');
        $migration->up();
        $migration->up(); // idempotent

        $fixed->refresh();
        $cpc->refresh();
        $this->assertSame(5000, $fixed->subscription_charged);
        $this->assertSame(0, $fixed->total_charged);
        $this->assertSame(0, $cpc->subscription_charged);
        $this->assertSame(300, $cpc->total_charged);
    }

    public function test_effective_rates_null_without_denominators_and_computed_otherwise(): void
    {
        $b = $this->booking(['subscription_charged' => 1000, 'total_charged' => 500]);
        $this->assertNull($b->effective_cpc);
        $this->assertNull($b->effective_cpm);
        $b->clicks_delivered = 3;
        $b->impressions_delivered = 2000;
        $this->assertSame(500, $b->effective_cpc);
        $this->assertSame(750, $b->effective_cpm);
    }

    public function test_ad_spend_by_country_includes_fixed_fees(): void
    {
        $this->booking(['subscription_charged' => 5000]);
        $c = $this->cpc();
        $c->update(['total_charged' => 300]);
        $rows = app(FinancialReportService::class)->adSpendByCountry(Carbon::today()->subDay(), Carbon::today());
        $this->assertSame(5300, (int) $rows->firstWhere('country_id', $this->country->id)->spend);
    }
}
