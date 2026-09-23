<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookable_unit_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bookable_unit_id')->constrained('bookable_units')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->foreignUuid('time_slot_id')->nullable()->constrained('bookable_unit_time_slots')->nullOnDelete();
            $table->boolean('includes_overnight')->default(false);
            $table->unsignedBigInteger('total_price');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->timestamps();

            $table->index(['bookable_unit_id', 'date_from', 'date_to'], 'bookable_unit_reservations_unit_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_unit_reservations');
    }
};
