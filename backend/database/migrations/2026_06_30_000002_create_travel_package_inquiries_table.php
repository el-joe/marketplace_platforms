<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_package_inquiries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('travel_package_id')->constrained('travel_packages')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->unsignedInteger('travelers_count')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['new', 'contacted', 'converted', 'closed'])->default('new');
            $table->foreignUuid('converted_to_booking_id')->nullable()
                ->constrained('travel_bookings')->nullOnDelete();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            $table->index(['travel_package_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_package_inquiries');
    }
};
