<?php

namespace Tests\Feature\Travel;

use App\Enums\TravelBookingStatus;
use App\Enums\TravelPackageStatus;
use App\Models\Customer;
use App\Models\TravelAgency;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use App\Services\Customer\TravelBookingService;
use App\Http\Requests\Customer\Travel\CreateBookingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TravelBookingSeatsTest extends TestCase
{
    use RefreshDatabase;

    private function pkg(int $seats = 5): TravelPackage
    {
        $agency = TravelAgency::forceCreate(['country_id' => \App\Models\Country::factory()->create()->id, 'name' => 'A', 'email' => 'a@x.test', 'phone' => '1', 'password' => 'x', 'status' => 'active']);
        return TravelPackage::forceCreate([
            'travel_agency_id' => $agency->id, 'title_en' => 'T', 'title_ar' => 'ت', 'price' => 1000,
            'currency' => 'AED', 'duration_days' => 5, 'duration_nights' => 4, 'available_seats' => $seats, 'seats_booked' => 0,
            'status' => TravelPackageStatus::Active, 'departure_date' => now()->addMonth(), 'return_date' => now()->addMonths(2),
        ]);
    }

    private function cust(string $e): Customer
    {
        return Customer::forceCreate(['name' => 'C', 'email' => $e, 'phone' => $e, 'password' => 'x']);
    }

    public function test_request_caps_at_10(): void
    {
        $rules = (new CreateBookingRequest)->rules();
        $this->assertTrue(Validator::make(['travelers_count' => 10], $rules)->passes());
        $this->assertTrue(Validator::make(['travelers_count' => 11], $rules)->fails());
        $this->assertTrue(Validator::make(['travelers_count' => 0], $rules)->fails());
    }

    public function test_seats_over_available_rejected_and_total_computed(): void
    {
        Bus::fake();
        $p = $this->pkg(5);
        $c = $this->cust('c1@x.test');
        $svc = app(TravelBookingService::class);
        $b = $svc->book($p, $c, ['travelers_count' => 3]);
        $this->assertSame(3000, (int) $b->total_price);
        $this->expectException(ValidationException::class);
        $p->forceFill(['seats_booked' => 4])->save();
        $svc->book($p, $c, ['travelers_count' => 2]);
    }

    public function test_customer_cancel_of_confirmed_releases_seats_and_others_cannot_view(): void
    {
        Notification::fake();
        $p = $this->pkg(5);
        $c = $this->cust('c2@x.test');
        $b = TravelBooking::forceCreate(['travel_package_id' => $p->id, 'customer_id' => $c->id, 'travelers_count' => 2, 'total_price' => 2000, 'status' => TravelBookingStatus::Confirmed]);
        $p->forceFill(['seats_booked' => 2])->save();
        $svc = app(TravelBookingService::class);
        $other = $this->cust('c3@x.test');
        try { $svc->showForCustomer($other, $b->id); $this->fail('IDOR'); } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {}
        $svc->cancel($c, $b->id, 'x');
        $this->assertSame(0, (int) $p->fresh()->seats_booked);
    }
}
