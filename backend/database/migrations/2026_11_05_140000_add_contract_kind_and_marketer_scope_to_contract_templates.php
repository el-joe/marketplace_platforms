<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contract kind (the business relationship a template regulates) and the seller-marketer collaboration scope.
     *
     * - classified_contract_templates.contract_kind: seller_platform | seller_category | seller_marketer (customer_purchase
     *   is added by the customer-contract work package without another schema change). Backfilled from the existing
     *   category_scope, which keeps meaning "which resolver serves it".
     * - category_scope gets the extra value `marketer`, so seller-marketer collaboration templates are invisible to the
     *   seller-contract resolvers (they all filter by scope) and share only the template storage / versioning.
     * - show_on_public_profile: whether the contract may be listed (type only, never text) on the public marketer profile.
     *
     * The generated `default_key` column depends on category_scope, so it is dropped and re-created around the enum change
     * (it is a virtual column: no data is lost).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('classified_contract_templates', 'contract_kind')) {
            Schema::table('classified_contract_templates', function (Blueprint $table) {
                $table->string('contract_kind', 32)->default('seller_category')->after('category_scope');
                $table->boolean('show_on_public_profile')->default(false)->after('is_country_default');
                $table->index(['contract_kind', 'is_published', 'is_active'], 'cct_kind_state_index');
            });

            DB::table('classified_contract_templates')->where('category_scope', 'platform')->update(['contract_kind' => 'seller_platform']);
            DB::table('classified_contract_templates')->whereIn('category_scope', ['classified', 'product'])->update(['contract_kind' => 'seller_category']);
        }

        if (Schema::hasColumn('classified_contract_templates', 'default_key')) {
            Schema::table('classified_contract_templates', function (Blueprint $table) {
                $table->dropUnique('cct_one_default_per_country_scope_unique');
            });
            Schema::table('classified_contract_templates', function (Blueprint $table) {
                $table->dropColumn('default_key');
            });
        }

        DB::statement("ALTER TABLE classified_contract_templates MODIFY category_scope ENUM('classified','product','platform','marketer') NOT NULL DEFAULT 'classified'");

        Schema::table('classified_contract_templates', function (Blueprint $table) {
            $table->string('default_key', 80)->nullable()->after('is_country_default')->virtualAs(
                "IF(is_country_default = 1 AND is_published = 1 AND is_active = 1 AND country_id IS NOT NULL AND category_scope NOT IN ('platform', 'marketer'), CONCAT(country_id, '|', category_scope), NULL)"
            );
            $table->unique('default_key', 'cct_one_default_per_country_scope_unique');
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: marketer-scope templates may exist, so the enum value stays.
        Schema::table('classified_contract_templates', function (Blueprint $table) {
            $table->dropIndex('cct_kind_state_index');
            $table->dropColumn(['contract_kind', 'show_on_public_profile']);
        });
    }
};
