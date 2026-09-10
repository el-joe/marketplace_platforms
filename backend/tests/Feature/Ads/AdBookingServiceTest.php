<?php

namespace Tests\Feature\Ads;

use App\Models\Country;
use App\Models\Marketer;
use App\Models\PaidAdBooking;
use App\Models\PaidAdCreative;
use App\Models\PaidAdSlot;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Services\Ads\AdBookingService;
use App\Services\Ads\AdSlotQuoteService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdBookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeCountry(): Country
    {
        return Country::factory()->create([
            'vat_rate' => 0,
            'currency_code' => 'AED',
            'timezone' => 'Asia/Dubai',
        ]);
    }

    private function makeSlot(Country $country, array $overrides = []): PaidAdSlot
    {
        $admin = \App\Models\Admin::factory()->create();

        return PaidAdSlot::create(array_merge([
            'country_id' => $country->id,
            'created_by_admin_id' => $admin->id,
            'name' => 'Test Slot',
            'slot_code' => 'slot-'.Str::random(8),
            'pricing_model' => 'fixed_daily',
            'base_rate' => 1000,
            'currency' => $country->currency_code,
            'min_booking_days' => 1,
            'max_booking_days' => 30,
            'is_available' => true,
            'requires_approval' => false,
            'max_concurrent' => 1,
            'lead_time_days' => 0,
            'allowed_advertisers' => 'vendor',
        ], $overrides));
    }

    private function makeVendor(Country $country): Vendor
    {
        return Vendor::factory()->create(['country_id' => $country->id]);
    }

    private function makeMarketer(Country $country): Marketer
    {
        return Marketer::create([
            'name' => 'Test Marketer',
            'email' => Str::random(10).'@example.com',
            'marketer_type' => 'affiliate',
            'global_status' => 'active',
            'country_id' => $country->id,
        ]);
    }

    private function approvedCreative(PaidAdBooking $booking): PaidAdCreative
    {
        return PaidAdCreative::create([
            'paid_ad_booking_id' => $booking->id,
            'version' => 1,
            'vendor_id' => $booking->vendor_id,
            'marketer_id' => $booking->marketer_id,
            'destination_url' => '/seller/'.$booking->vendor_id,
            'destination_type' => 'store',
            'status' => 'approved',
            'is_current' => true,
            'approved_at' => now(),
        ]);
    }

    public function test_overlapping_bookings_on_single_concurrency_slot_throws(): void
    {
        $country = $this->makeCountry();
        $slot = $this->makeSlot($country, ['max_concurrent' => 1]);
        $vendorA = $this->makeVendor($country);
        $vendorB = $this->makeVendor($country);
        Wallet::create(['owner_type' => 'vendor', 'owner_id' => $vendorA->id, 'currency' => $country->currency_code, 'balance' => 1000000, 'pending_balance' => 0]);
        Wallet::create(['owner_type' => 'vendor', 'owner_id' => $vendorB->id, 'currency' => $country->currency_code, 'balance' => 1000000, 'pending_balance' => 0]);

        $service = app(AdBookingService::class);

        $from = Carbon::today()->addDays(2);
        $to = $from->copy()->addDays(4);

        $bookingA = $service->createDraft($slot, $vendorA, $from, $to, null, 'wallet');
        $this->approvedCreative($bookingA);
        $service->submit($bookingA);

        $bookingB = $service->createDraft($slot, $vendorB, $from, $to, null, 'wallet');
        $this->approvedCreative($bookingB);

        $this->expectException(DomainException::class);
        $service->submit($bookingB);
    }

    public function test_weekly_slot_with_ten_days_throws(): void
    {
        $country = $this->makeCountry();
        $slot = $this->makeSlot($country, ['pricing_model' => 'fixed_weekly', 'max_booking_days' => 30]);
        $vendor = $this->makeVendor($country);

        $from = Carbon::today()->addDays(2);
        $to = $from->copy()->addDays(9); // 10 days

        $this->expectException(DomainException::class);
        app(AdSlotQuoteService::class)->quote($slot, $from, $to);
    }

    public function test_wallet_vendor_insufficient_balance_leaves_booking_unpaid(): void
    {
        $country = $this->makeCountry();
        $slot = $this->makeSlot($country, ['base_rate' => 100000, 'requires_approval' => true]);
        $vendor = $this->makeVendor($country);
        $wallet = Wallet::create(['owner_type' => 'vendor', 'owner_id' => $vendor->id, 'currency' => $country->currency_code, 'balance' => 500000, 'pending_balance' => 0]);

        $service = app(AdBookingService::class);

        $from = Carbon::today()->addDays(2);
        $to = $from->copy()->addDays(4);

        $booking = $service->createDraft($slot, $vendor, $from, $to, null, 'wallet');
        $this->approvedCreative($booking);
        $service->submit($booking); // soft balance check passes: 500,000 available vs 500,000 required

        // Balance drops below the required amount before approval (e.g. spent elsewhere).
        $wallet->update(['balance' => 100]);

        $booking->refresh();
        $service->approve($booking, null);

        $booking->refresh();
        $this->assertEquals('approved', $booking->status->value);
        $this->assertEquals('unpaid', $booking->payment_status->value);
        $this->assertNotNull($booking->payment_due_at);
    }

    public function test_payout_deduction_fixed_booking_creates_unsettled_charge(): void
    {
        $country = $this->makeCountry();
        $slot = $this->makeSlot($country, ['requires_approval' => true]);
        $vendor = $this->makeVendor($country);

        $service = app(AdBookingService::class);

        $from = Carbon::today()->addDays(2);
        $to = $from->copy()->addDays(4);

        $booking = $service->createDraft($slot, $vendor, $from, $to, null, 'payout_deduction');
        $this->approvedCreative($booking);
        $service->submit($booking);

        $booking->refresh();
        $service->approve($booking, null);

        $booking->refresh();
        $this->assertEquals('reserved', $booking->payment_status->value);
        $this->assertEquals('scheduled', $booking->status->value);
        $this->assertEquals(1, $booking->charges()->where('type', 'fixed')->where('settlement', 'payout_deduction')->count());
    }

    public function test_cpm_booking_bills_in_thousand_blocks(): void
    {
        $country = $this->makeCountry();
        $slot = $this->makeSlot($country, ['pricing_model' => 'cpm', 'base_rate' => 500, 'min_booking_days' => 1, 'requires_approval' => true]);
        $vendor = $this->makeVendor($country);
        Wallet::create(['owner_type' => 'vendor', 'owner_id' => $vendor->id, 'currency' => $country->currency_code, 'balance' => 1000000, 'pending_balance' => 0]);

        $service = app(AdBookingService::class);
        $billing = app(\App\Services\Ads\AdBillingService::class);

        $from = Carbon::today()->addDays(2);
        $to = $from->copy()->addDays(4);

        $booking = $service->createDraft($slot, $vendor, $from, $to, 500000, 'wallet');
        $this->approvedCreative($booking);
        $service->submit($booking);
        $booking->refresh();
        $service->approve($booking, null);
        $booking->refresh();

        $billing->recordDelivery($booking, 2500, 0);

        $booking->refresh();
        $this->assertEquals(2, $booking->charges()->where('type', 'cpm')->count());
        $this->assertEquals(2000, $booking->cpm_impressions_billed);
    }

    public function test_admin_cancel_active_fixed_booking_refunds_prorated_amount(): void
    {
        $country = $this->makeCountry();
        $slot = $this->makeSlot($country, ['base_rate' => 1000, 'min_booking_days' => 1]);
        $vendor = $this->makeVendor($country);
        Wallet::create(['owner_type' => 'vendor', 'owner_id' => $vendor->id, 'currency' => $country->currency_code, 'balance' => 1000000, 'pending_balance' => 0]);

        $service = app(AdBookingService::class);

        $from = Carbon::today()->subDays(3);
        $to = $from->copy()->addDays(9); // 10 days total

        $booking = PaidAdBooking::create([
            'booking_reference' => 'ADB-TEST-'.Str::random(5),
            'paid_ad_slot_id' => $slot->id,
            'advertiser_type' => 'vendor',
            'vendor_id' => $vendor->id,
            'country_id' => $country->id,
            'pricing_model' => 'fixed_daily',
            'pricing_units' => 10,
            'unit_rate' => 1000,
            'agreed_rate' => 1000,
            'quoted_amount' => 10000,
            'tax_amount' => 0,
            'booked_from' => $from,
            'booked_until' => $to,
            'currency' => $country->currency_code,
            'status' => 'active',
            'payment_status' => 'paid',
            'payment_method' => 'wallet',
            'started_at' => $from,
        ]);
        \App\Models\PaidAdCharge::create([
            'paid_ad_booking_id' => $booking->id,
            'advertiser_type' => 'vendor',
            'vendor_id' => $vendor->id,
            'country_id' => $country->id,
            'currency' => $country->currency_code,
            'type' => 'fixed',
            'amount' => $booking->quoted_amount,
            'settlement' => 'wallet',
            'settled_at' => now(),
        ]);

        $admin = \App\Models\Admin::factory()->create();

        $service->cancel($booking, 'admin', 'test cancel', $admin);

        $booking->refresh();
        $this->assertEquals('cancelled', $booking->status->value);
        // day 4 of 10 (3 elapsed + today) -> 6 remaining days
        $expectedRefund = intdiv($booking->quoted_amount * 6, 10);
        $refundCharge = $booking->charges()->where('type', 'refund')->first();
        $this->assertNotNull($refundCharge);
        $this->assertEquals(-$expectedRefund, $refundCharge->amount);
    }

    public function test_marketer_booking_with_payout_deduction_throws(): void
    {
        $country = $this->makeCountry();
        $slot = $this->makeSlot($country, ['allowed_advertisers' => 'marketer']);
        $marketer = $this->makeMarketer($country);

        $service = app(AdBookingService::class);

        $from = Carbon::today()->addDays(2);
        $to = $from->copy()->addDays(4);

        $this->expectException(DomainException::class);
        $service->createDraft($slot, $marketer, $from, $to, null, 'payout_deduction');
    }
}
