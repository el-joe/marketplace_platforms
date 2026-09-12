<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('admin_listings', 'al_variant_country_status_idx', function (Blueprint $t) {
            $t->index(['product_variant_id', 'country_id', 'status'], 'al_variant_country_status_idx');
        });

        $this->addIndexIfMissing('vendor_listings', 'vl_variant_country_status_score_idx', function (Blueprint $t) {
            $t->index(['product_variant_id', 'country_id', 'status', 'score'], 'vl_variant_country_status_score_idx');
        });

        $this->addIndexIfMissing('product_variants', 'pv_product_id_is_active_idx', function (Blueprint $t) {
            $t->index(['product_id', 'is_active'], 'pv_product_id_is_active_idx');
        });

        $this->addIndexIfMissing('marketer_listings', 'ml_variant_country_status_idx', function (Blueprint $t) {
            $t->index(['product_variant_id', 'country_id', 'status'], 'ml_variant_country_status_idx');
        });
    }

    private function addIndexIfMissing(string $table, string $indexName, \Closure $callback): void
    {
        $exists = DB::select(
            "SELECT COUNT(*) as cnt FROM information_schema.statistics
             WHERE table_schema = DATABASE()
             AND table_name = ?
             AND index_name = ?",
            [$table, $indexName]
        );

        if (($exists[0]->cnt ?? 0) === 0) {
            Schema::table($table, $callback);
        }
    }

    public function down(): void
    {
        Schema::table('admin_listings', fn ($t) => $t->dropIndex('al_variant_country_status_idx'));
        Schema::table('vendor_listings', fn ($t) => $t->dropIndex('vl_variant_country_status_score_idx'));
        Schema::table('product_variants', fn ($t) => $t->dropIndex('pv_product_id_is_active_idx'));
        Schema::table('marketer_listings', fn ($t) => $t->dropIndex('ml_variant_country_status_idx'));
    }
};
