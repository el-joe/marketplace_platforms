<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_package_pricing_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('travel_package_id')->constrained('travel_packages')->cascadeOnDelete();
            $table->unsignedInteger('travelers_count');
            $table->unsignedBigInteger('price_cents');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['travel_package_id', 'travelers_count'], 'tp_pricing_tiers_package_count_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_package_pricing_tiers');
    }
};
