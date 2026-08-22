<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('shipping_weight_slabs')) {
            return;
        }

        Schema::create('shipping_weight_slabs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shipping_method_id')->index();
            $table->uuid('country_id')->index();
            $table->unsignedInteger('min_weight_grams');
            $table->unsignedInteger('max_weight_grams')->nullable();
            $table->unsignedBigInteger('extra_fee_cents')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('shipping_method_id')->references('id')->on('shipping_methods')->onDelete('cascade');
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->index(['shipping_method_id', 'country_id', 'min_weight_grams', 'max_weight_grams'], 'weight_slabs_composite_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_weight_slabs');
    }
};
