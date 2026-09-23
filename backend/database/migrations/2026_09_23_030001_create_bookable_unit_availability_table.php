<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (bookable_unit x date) managed by the agency's calendar,
     * carrying that day's availability flag, optional capacity override, and
     * the two prices (day-only vs. with overnight).
     */
    public function up(): void
    {
        Schema::create('bookable_unit_availability', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bookable_unit_id')->constrained('bookable_units')->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('capacity_override')->nullable();
            $table->unsignedBigInteger('price_day_only')->nullable();
            $table->unsignedBigInteger('price_with_overnight')->nullable();
            $table->timestamps();

            $table->unique(['bookable_unit_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_unit_availability');
    }
};
