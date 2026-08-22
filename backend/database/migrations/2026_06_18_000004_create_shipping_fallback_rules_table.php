<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_fallback_rules', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('unserved_city_id', 36)->index();
            $table->foreign('unserved_city_id')->references('id')->on('cities')->cascadeOnDelete();
            $table->char('fallback_shipping_company_id', 36)->index();
            $table->foreign('fallback_shipping_company_id')->references('id')->on('shipping_companies')->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(1);
            $table->timestamps();

            $table->index(['unserved_city_id', 'priority'], 'fallback_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_fallback_rules');
    }
};
