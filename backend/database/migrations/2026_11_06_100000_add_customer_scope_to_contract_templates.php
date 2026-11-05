<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the `customer` category scope: customer purchase contracts (customer <-> platform / seller, signed before
     * buying a classified item such as a property or a car). Like every other scope it shares only template storage and
     * versioning; the contract_kind column is derived from the scope by the model (`customer_purchase`).
     *
     * The generated `default_key` column depends on category_scope, so it is dropped and re-created around the enum
     * change (it is a virtual column: no data is lost). Customer templates never are a "country default", exactly
     * like platform and marketer templates.
     */
    public function up(): void
    {
        if (Schema::hasColumn('classified_contract_templates', 'default_key')) {
            Schema::table('classified_contract_templates', function (Blueprint $table) {
                $table->dropUnique('cct_one_default_per_country_scope_unique');
            });
            Schema::table('classified_contract_templates', function (Blueprint $table) {
                $table->dropColumn('default_key');
            });
        }

        DB::statement("ALTER TABLE classified_contract_templates MODIFY category_scope ENUM('classified','product','platform','marketer','customer') NOT NULL DEFAULT 'classified'");

        Schema::table('classified_contract_templates', function (Blueprint $table) {
            $table->string('default_key', 80)->nullable()->after('is_country_default')->virtualAs(
                "IF(is_country_default = 1 AND is_published = 1 AND is_active = 1 AND country_id IS NOT NULL AND category_scope NOT IN ('platform', 'marketer', 'customer'), CONCAT(country_id, '|', category_scope), NULL)"
            );
            $table->unique('default_key', 'cct_one_default_per_country_scope_unique');
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: customer-scope templates may exist, so the enum value stays.
    }
};
