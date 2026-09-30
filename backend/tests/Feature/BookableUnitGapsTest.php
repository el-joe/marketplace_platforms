<?php

namespace Tests\Feature;

use App\Enums\TravelAgencyStatus;
use App\Enums\TravelBookingStatus;
use App\Models\Admin;
use App\Models\BookableUnit;
use App\Models\BookableUnitAvailability;
use App\Models\BookingUnitDay;
use App\Models\Country;
use App\Models\Customer;
use App\Models\TravelAgency;
use App\Models\TravelAgencyMember;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BookableUnitGapsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function admin(): Admin
    {
        Permission::firstOrCreate(['name' => 'vendors.assigned_only', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'travel.view', 'guard_name' => 'admin']);
        Permission::firstOrCreate(['name' => 'travel.manage', 'guard_name' => 'admin']);
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['travel.view', 'travel.manage']);

        return $admin;
    }

    private function agency(): TravelAgency
    {
        return TravelAgency::create([
            'name' => 'Test Travel Agency',
            'email' => 'travel-'.Str::lower(Str::random(8)).'@example.test',
            'phone' => '+9715'.random_int(10000000, 99999999),
            'password' => bcrypt('password'),
            'license_number' => 'TL-'.Str::upper(Str::random(8)),
            'country_id' => Country::factory()->create()->id,
            'status' => TravelAgencyStatus::Active,
            'approved_at' => now(),
        ]);
    }

    private function owner(TravelAgency $agency): TravelAgencyMember
    {
        return TravelAgencyMember::create([
            'travel_agency_id' => $agency->id,
            'name' => 'Owner Member',
            'email' => 'owner-'.Str::lower(Str::random(8)).'@example.test',
            'phone' => '+9715'.random_int(10000000, 99999999),
            'password' => bcrypt('password'),
            'role' => 'owner',
            'is_owner' => true,
            'is_active' => true,
        ]);
    }

    private function unit(TravelAgency $agency): BookableUnit
    {
        return BookableUnit::create([
            'travel_agency_id' => $agency->id,
            'name' => 'Chalet Yasmin',
            'type' => 'chalet',
            'capacity' => 4,
            'status' => 'draft',
        ]);
    }

    public function test_admin_can_reject_a_pending_unit_with_a_reason(): void
    {
        $admin = $this->admin();
        $unit = $this->unit($this->agency());

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.travel.bookable-units.reject', $unit), [
                'rejection_reason' => 'Photos do not match the description.',
            ]);

        $response->assertRedirect();
        $unit->refresh();
        $this->assertSame('rejected', $unit->status);
        $this->assertSame('Photos do not match the description.', $unit->rejection_reason);
        $this->assertSame($admin->id, $unit->rejected_by_admin_id);
        $this->assertNotNull($unit->rejected_at);
    }

    public function test_rejecting_requires_a_reason(): void
    {
        $admin = $this->admin();
        $unit = $this->unit($this->agency());

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.travel.bookable-units.reject', $unit), []);

        $response->assertSessionHasErrors('rejection_reason');
        $this->assertSame('draft', $unit->fresh()->status);
    }

    public function test_closing_a_date_with_an_active_reservation_is_blocked(): void
    {
        $agency = $this->agency();
        $unit = $this->unit($agency);
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'customer-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'),
        ]);

        BookableUnitAvailability::create([
            'bookable_unit_id' => $unit->id,
            'date' => '2026-10-10',
            'is_available' => false,
            'price_day_only' => 200,
            'price_with_overnight' => 300,
        ]);

        // Unit days are now part of travel bookings.
        $pkg = TravelPackage::create([
            'travel_agency_id' => $agency->id,
            'slug' => 'pkg-'.Str::lower(Str::random(8)),
            'title_en' => 'Test Pkg',
            'title_ar' => 'باقة',
            'destination_country' => 'AE',
            'destination_city' => 'Dubai',
            'price' => 1000,
            'currency' => 'AED',
            'duration_days' => 1,
            'duration_nights' => 1,
            'departure_date' => '2026-10-10',
            'return_date' => '2026-10-10',
            'status' => 'active',
        ]);
        $booking = TravelBooking::create([
            'travel_package_id' => $pkg->id,
            'bookable_unit_id' => $unit->id,
            'customer_id' => $customer->id,
            'travelers_count' => 1,
            'total_price' => 300,
            'status' => TravelBookingStatus::PendingDocuments,
        ]);
        BookingUnitDay::create([
            'travel_booking_id' => $booking->id,
            'bookable_unit_id' => $unit->id,
            'date' => '2026-10-10',
            'includes_overnight' => true,
            'price' => 300,
        ]);

        $response = $this->actingAs($this->owner($agency), 'travel_agency')
            ->post(route('travel-agency.bookable-units.availability.upsert', $unit), [
                'date' => '2026-10-10',
                'is_available' => '0',
            ]);

        $response->assertSessionHasErrors('is_available');
    }

    public function test_closing_an_unbooked_date_still_works(): void
    {
        $agency = $this->agency();
        $unit = $this->unit($agency);

        $response = $this->actingAs($this->owner($agency), 'travel_agency')
            ->post(route('travel-agency.bookable-units.availability.upsert', $unit), [
                'date' => '2026-10-11',
                'is_available' => '0',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertFalse(
            BookableUnitAvailability::where('bookable_unit_id', $unit->id)->where('date', '2026-10-11')->first()->is_available
        );
    }
}
