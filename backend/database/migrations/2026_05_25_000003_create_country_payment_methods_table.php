<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('country_payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_id')->index();
            $table->string('method_type', 50);                         // card, wallet, cod, bnpl, bank_transfer
            $table->string('provider', 50)->nullable();                // stripe, paymob, paytabs, tap, …
            $table->string('display_name_en', 100);
            $table->string('display_name_ar', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('fee_pct', 5, 2)->default(0.00);
            $table->unsignedBigInteger('fee_fixed_cents')->default(0);
            $table->unsignedBigInteger('min_order_cents')->default(0);
            $table->unsignedBigInteger('max_order_cents')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_payment_methods');
    }
};
