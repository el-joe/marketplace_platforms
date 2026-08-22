<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('travel_country_id')->constrained('travel_countries')->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ar');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['travel_country_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_cities');
    }
};
