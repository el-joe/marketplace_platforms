<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->foreignUuid('country_id')->constrained('countries');
            $table->string('name', 150);
            $table->string('code', 20)->unique();
            $table->json('city_ids')->nullable()->comment('Array of city IDs this zone covers');
            $table->json('polygon_coordinates')->nullable()->comment('GeoJSON polygon for map display');
            $table->bigInteger('base_delivery_fee_cents')->default(0);
            $table->bigInteger('cod_fee_cents')->default(0);
            $table->integer('max_active_agents')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
