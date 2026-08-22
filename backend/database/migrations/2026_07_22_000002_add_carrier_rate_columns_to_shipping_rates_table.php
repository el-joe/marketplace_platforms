<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->bigInteger('carrier_rate')->default(0)->after('base_fee')
                ->comment('What the carrier charges platform per delivery in this zone, base currency units. 0 = same as base_fee (non-exceptional zone)');

            $table->bigInteger('carrier_rate_per_kg')->default(0)->after('carrier_rate')
                ->comment('Additional carrier cost per kg over min_weight_grams, base currency units');
        });

        DB::table('shipping_rates')->update([
            'carrier_rate' => DB::raw('base_fee'),
            'carrier_rate_per_kg' => DB::raw('rate_per_kg'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->dropColumn(['carrier_rate', 'carrier_rate_per_kg']);
        });
    }
};
