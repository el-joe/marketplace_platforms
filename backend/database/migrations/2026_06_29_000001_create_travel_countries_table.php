<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('iso_code_2', 2)->unique();
            $table->char('iso_code_3', 3)->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('flag_emoji', 10)->nullable();
            $table->string('continent', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_countries');
    }
};
