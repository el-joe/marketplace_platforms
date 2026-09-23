<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily-calendar bookable units (chalets/hotel rooms) for travel agencies.
     * Entirely new and separate from travel_packages/travel_bookings (fixed-date
     * travel packages) — the two coexist as different options for agencies.
     */
    public function up(): void
    {
        Schema::create('bookable_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('travel_agency_id')->constrained('travel_agencies')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['chalet', 'hotel_room', 'other'])->default('chalet');
            $table->unsignedInteger('capacity')->default(1);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_units');
    }
};
