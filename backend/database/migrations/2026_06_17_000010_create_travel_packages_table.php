<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('travel_agency_id')->constrained('travel_agencies')->cascadeOnDelete();
            $table->string('title_en');
            $table->string('title_ar');
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('destination_country', 100);
            $table->string('destination_city', 100)->nullable();
            $table->unsignedBigInteger('price_cents');
            $table->char('currency', 3);
            $table->unsignedInteger('duration_days');
            $table->unsignedInteger('duration_nights');
            $table->date('departure_date');
            $table->date('return_date');
            $table->unsignedInteger('available_seats')->nullable();
            $table->unsignedInteger('seats_booked')->default(0);
            $table->json('inclusions')->nullable();
            $table->enum('status', ['draft', 'pending_review', 'active', 'sold_out', 'expired'])->default('draft');
            $table->foreignUuid('approved_by_admin_id')->nullable()->constrained('admins');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_packages');
    }
};
