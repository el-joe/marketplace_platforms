<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_unit_days', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('travel_booking_id')->constrained('travel_bookings')->cascadeOnDelete();
            $table->foreignUuid('bookable_unit_id')->constrained('bookable_units')->cascadeOnDelete();
            $table->date('date');
            $table->boolean('includes_overnight')->default(false);
            $table->foreignUuid('time_slot_id')->nullable()->constrained('bookable_unit_time_slots')->nullOnDelete();
            $table->unsignedBigInteger('price');
            $table->timestamps();

            $table->index(['travel_booking_id']);
            $table->unique(['travel_booking_id', 'bookable_unit_id', 'date'], 'booking_unit_days_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_unit_days');
    }
};
