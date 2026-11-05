<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Database guard against duplicate signing: a vendor can hold only one active signature per
     * (scope, template, category / product). The generated key is NULL for superseded and revoked rows.
     *
     * Production safety: the unique index is only added when no duplicate active signatures exist already;
     * otherwise the index is skipped (and logged) and the service-level lock remains the guard.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('vendor_contracts', 'active_key')) {
            Schema::table('vendor_contracts', function (Blueprint $table) {
                $table->string('active_key', 255)->nullable()->after('status')->virtualAs(
                    "IF(status = 'active', CONCAT_WS('|', vendor_id, category_scope, IFNULL(contract_template_id, ''), COALESCE(product_category_id, classified_category_id, product_id, '')), NULL)"
                );
            });
        }

        $hasIndex = collect(Schema::getIndexes('vendor_contracts'))->contains(fn (array $index) => $index['name'] === 'vc_one_active_signature_unique');

        if ($hasIndex) {
            return;
        }

        $duplicates = count(DB::select(
            'select active_key from vendor_contracts where active_key is not null group by active_key having count(*) > 1 limit 1'
        ));

        if ($duplicates > 0) {
            Log::warning('vendor_contracts: unique active-signature index skipped, duplicate active signatures exist.', ['duplicates' => $duplicates]);

            return;
        }

        Schema::table('vendor_contracts', function (Blueprint $table) {
            $table->unique('active_key', 'vc_one_active_signature_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_contracts', function (Blueprint $table) {
            if (collect(Schema::getIndexes('vendor_contracts'))->contains(fn (array $index) => $index['name'] === 'vc_one_active_signature_unique')) {
                $table->dropUnique('vc_one_active_signature_unique');
            }

            $table->dropColumn('active_key');
        });
    }
};
