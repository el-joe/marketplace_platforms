<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('booking_number', 30)->unique();
            $table->foreignUuid('travel_package_id')->constrained('travel_packages');
            $table->foreignUuid('customer_id')->constrained('customers');
            $table->unsignedInteger('travelers_count')->default(1);
            $table->unsignedBigInteger('total_price_cents');
            $table->string('passport_file_path')->nullable();
            $table->timestamp('contract_signed_at')->nullable();
            $table->text('contract_signature_data')->nullable();
            $table->enum('status', ['pending_documents', 'confirmed', 'cancelled', 'completed'])->default('pending_documents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_bookings');
    }
};
