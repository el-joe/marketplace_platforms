<?php

namespace Tests\Feature;

use App\Models\BookableUnitReservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookableUnitAdminTest extends TestCase
{
    use DatabaseTransactions;

    public function test_schema_and_reservation_number_generation(): void
    {
        $this->assertTrue(Schema::hasColumn('bookable_units', 'status'));
        $this->assertTrue(Schema::hasColumn('bookable_unit_reservations', 'reservation_number'));
        $this->assertTrue(route('admin.travel.bookable-units.approve', ['bookableUnit' => 'x']) !== '');
        $this->assertContains('reservation_number', (new BookableUnitReservation)->getFillable());
    }
}
