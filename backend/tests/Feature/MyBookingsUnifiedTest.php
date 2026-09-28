<?php

namespace Tests\Feature;

use App\Enums\BookableUnitReservationStatus;
use App\Enums\TravelAgencyStatus;
use App\Enums\TravelBookingStatus;
use App\Models\BookableUnit;
use App\Models\BookableUnitReservation;
use App\Models\Country;
use App\Models\Customer;
use App\Models\TravelAgency;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class MyBookingsUnifiedTest extends TestCase
{
    use DatabaseTransactions;

    private function country(): Country
    {
        return Country::factory()->create([
            'site_code' => 'eg',
            'is_active' => true,
        ]);
    }

    private function agency(Country $country): TravelAgency
    {
        return TravelAgency::create([
            'name' => 'Test Travel Agency',
            'email' => 'travel-'.Str::lower(Str::random(8)).'@example.test',
            'phone' => '+9715'.random_int(10000000, 99999999),
            'password' => bcrypt('password'),
            'license_number' => 'TL-'.Str::upper(Str::random(8)),
            'country_id' => $country->id,
            'status' => TravelAgencyStatus::Active,
            'approved_at' => now(),
        ]);
    }

    private function customer(): Customer
    {
        return Customer::create([
            'name' => 'Test Customer',
            'email' => 'customer-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('password'),
        ]);
    }

    private function package(TravelAgency $agency): TravelPackage
    {
        return TravelPackage::create([
            'travel_agency_id' => $agency->id,
            'slug' => 'package-'.Str::lower(Str::random(8)),
            'title_en' => 'Test Package',
            'title_ar' => 'باقة تجريبية',
            'destination_country' => 'AE',
            'destination_city' => 'Dubai',
            'price' => 1000,
            'currency' => 'AED',
            'duration_days' => 5,
            'duration_nights' => 4,
            'departure_date' => '2026-11-01',
            'return_date' => '2026-11-05',
            'available_seats' => 10,
            'seats_booked' => 0,
            'status' => 'active',
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

    public function test_my_bookings_returns_only_the_authenticated_customers_bookings(): void
    {
        $country = $this->country();
        $agency = $this->agency($country);
        $me = $this->customer();
        $otherCustomer = $this->customer();

        $myPackage = $this->package($agency);
        TravelBooking::create([
            'travel_package_id' => $myPackage->id,
            'customer_id' => $me->id,
            'travelers_count' => 2,
            'total_price' => 2000,
            'status' => TravelBookingStatus::Confirmed,
        ]);

        $myUnit = $this->unit($agency);
        BookableUnitReservation::create([
            'bookable_unit_id' => $myUnit->id,
            'customer_id' => $me->id,
            'currency' => 'AED',
            'date_from' => '2026-10-10',
            'date_to' => '2026-10-11',
            'includes_overnight' => true,
            'total_price' => 300,
            'status' => BookableUnitReservationStatus::Pending,
        ]);

        // Belongs to a different customer — must never appear in `$me`'s response.
        $otherPackage = $this->package($agency);
        TravelBooking::create([
            'travel_package_id' => $otherPackage->id,
            'customer_id' => $otherCustomer->id,
            'travelers_count' => 1,
            'total_price' => 500,
            'status' => TravelBookingStatus::Confirmed,
        ]);

        $response = $this->actingAs($me, 'customer')
            ->getJson(route('customer.my-bookings', ['country' => $country->site_code]));

        $response->assertSuccessful();
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertEqualsCanonicalizing(
            ['travel_package', 'bookable_unit'],
            array_column($data, 'type'),
        );
        $this->assertTrue(
            collect($data)->every(fn ($booking) => $booking['total_price'] !== 500),
            'A booking belonging to another customer leaked into the response.',
        );
    }

    public function test_my_bookings_requires_authentication(): void
    {
        $country = $this->country();

        $response = $this->getJson(route('customer.my-bookings', ['country' => $country->site_code]));

        $response->assertUnauthorized();
    }
}
