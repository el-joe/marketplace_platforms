<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('commission_fbp_pct', 5, 2)->default(0)
                ->after('commission_rate')
                ->comment('Percentage commission for merchant_fbp listings (e.g. 8.00 = 8%)');
            $table->bigInteger('commission_fbp_fixed_cents')->default(0)
                ->after('commission_fbp_pct')
                ->comment('Fixed fee per item per unit for merchant_fbp, in platform base currency cents');
            $table->decimal('commission_fbn_pct', 5, 2)->default(0)
                ->after('commission_fbp_fixed_cents')
                ->comment('Percentage commission for express_fbn listings');
            $table->bigInteger('commission_fbn_fixed_cents')->default(0)
                ->after('commission_fbn_pct')
                ->comment('Fixed fee per item per unit for express_fbn, in platform base currency cents');
        });

        // Backfill: copy existing commission_rate into both FBP and FBN percentage columns.
        // Fixed cents stay at 0 — no fixed fee existed before this change.
        DB::statement('
            UPDATE categories
            SET commission_fbp_pct = commission_rate,
                commission_fbn_pct = commission_rate
        ');
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'commission_fbp_pct',
                'commission_fbp_fixed_cents',
                'commission_fbn_pct',
                'commission_fbn_fixed_cents',
            ]);
        });
    }
};
