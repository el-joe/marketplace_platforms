<?php

namespace Tests\Feature;

use App\Models\BookingUnitDay;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookableUnitAdminTest extends TestCase
{
    use DatabaseTransactions;

    public function test_schema_has_booking_unit_days_table(): void
    {
        $this->assertTrue(Schema::hasColumn('bookable_units', 'status'));
        $this->assertTrue(Schema::hasTable('booking_unit_days'));
        $this->assertTrue(Schema::hasColumn('booking_unit_days', 'travel_booking_id'));
        $this->assertTrue(route('admin.travel.bookable-units.approve', ['bookableUnit' => 'x']) !== '');
        $this->assertContains('price', (new BookingUnitDay)->getFillable());
    }
}
