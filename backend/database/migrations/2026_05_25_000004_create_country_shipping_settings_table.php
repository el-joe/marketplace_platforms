<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('country_shipping_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_id')->index();
            $table->uuid('shipping_method_id')->index();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('free_shipping_threshold_cents')->nullable();
            $table->timestamps();

            $table->unique(['country_id', 'shipping_method_id']);
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->foreign('shipping_method_id')->references('id')->on('shipping_methods')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_shipping_settings');
    }
};
