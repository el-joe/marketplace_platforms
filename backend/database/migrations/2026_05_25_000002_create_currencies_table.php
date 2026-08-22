<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();                     // ISO 4217, e.g. USD, SAR, AED
            $table->string('name', 100);                            // US Dollar
            $table->string('symbol', 10);                          // $, ﷼, د.إ
            $table->tinyInteger('decimal_places')->default(2);
            $table->char('base_currency_code', 3)->default('USD'); // rates are expressed relative to this
            $table->decimal('exchange_rate_to_base', 15, 6)->default(1.000000);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_manually_overridden')->default(false);
            $table->timestamp('rate_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
