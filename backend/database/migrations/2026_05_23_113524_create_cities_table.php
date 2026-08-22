<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_id')->index();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->uuid('shipping_zone_id')->index()->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('cod_available')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
