<?php

namespace Tests\Feature;

use App\DTOs\UnifiedBookingDTO;
use App\Models\FlightBooking;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MyBookingsUnifiedTest extends TestCase
{
    use DatabaseTransactions;

    public function test_flight_table_dto_and_route(): void
    {
        $this->assertTrue(Schema::hasTable('flight_bookings'));
        $this->assertStringEndsWith('/my-bookings', route('customer.my-bookings', ['country' => 'eg'], false));
        $dto = new UnifiedBookingDTO('1', 'flight', 'FLT-1', 't', 'a', 'b', 5, 'EGP', 'pending', null, null);
        $this->assertSame('flight', $dto->toArray()['type']);
        $this->assertContains('booking_number', (new FlightBooking)->getFillable());
    }
}
