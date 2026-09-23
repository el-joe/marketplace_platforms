<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flight_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('booking_number', 30)->unique();
            $table->uuid('customer_id');
            $table->uuid('travel_agency_id')->nullable();
            $table->string('airline_name')->nullable();
            $table->string('flight_number')->nullable();
            $table->string('origin_city');
            $table->string('destination_city');
            $table->timestamp('departure_at');
            $table->timestamp('arrival_at')->nullable();
            $table->integer('passengers_count')->default(1);
            $table->unsignedBigInteger('total_price')->comment('BIGINT base-currency.');
            $table->string('currency', 3);
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('travel_agency_id')->references('id')->on('travel_agencies')->onDelete('set null');
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flight_bookings');
    }
};
