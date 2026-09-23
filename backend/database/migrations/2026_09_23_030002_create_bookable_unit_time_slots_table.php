<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional per-period (morning/evening/custom) slots a unit can be
     * booked in, instead of a whole day.
     */
    public function up(): void
    {
        Schema::create('bookable_unit_time_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bookable_unit_id')->constrained('bookable_units')->cascadeOnDelete();
            $table->enum('slot_type', ['morning', 'evening', 'custom'])->default('custom');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedBigInteger('price');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_unit_time_slots');
    }
};
