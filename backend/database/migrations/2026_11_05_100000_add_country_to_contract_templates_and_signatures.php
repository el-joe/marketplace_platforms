<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Country dimension for contracts (additive, backward compatible).
     *
     * - classified_contract_templates.country_id: NULL = legacy / country-agnostic template. It keeps working for
     *   every country that has no country-specific template of its own. A country-specific template is only ever
     *   used for that country.
     * - classified_contract_templates.is_country_default: marks the default individual (product / classified)
     *   contract of a country. default_key (generated) + unique index guarantees at most one *published, active*
     *   default per (country, scope), so conflicting assignments are rejected by the database.
     * - vendor_contracts.country_id: audit trail of the country the contract was resolved for when it was signed.
     * - vendor_contracts.product_id: signatures of a single product's individual contract.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('classified_contract_templates', 'country_id')) {
            Schema::table('classified_contract_templates', function (Blueprint $table) {
                $table->uuid('country_id')->nullable()->after('product_category_id')
                    ->comment('NULL = legacy country-agnostic template (serves countries without a specific one).');
                $table->boolean('is_country_default')->default(false)->after('country_id');

                $table->foreign('country_id', 'cct_country_foreign')->references('id')->on('countries')->restrictOnDelete();
                $table->index(['country_id', 'category_scope', 'is_published', 'is_active'], 'cct_country_scope_state_index');
            });
        }

        if (! Schema::hasColumn('classified_contract_templates', 'default_key')) {
            Schema::table('classified_contract_templates', function (Blueprint $table) {
                $table->string('default_key', 80)->nullable()->after('is_country_default')->virtualAs(
                    "IF(is_country_default = 1 AND is_published = 1 AND is_active = 1 AND country_id IS NOT NULL AND category_scope <> 'platform', CONCAT(country_id, '|', category_scope), NULL)"
                );
                $table->unique('default_key', 'cct_one_default_per_country_scope_unique');
            });
        }

        if (! Schema::hasColumn('vendor_contracts', 'country_id')) {
            Schema::table('vendor_contracts', function (Blueprint $table) {
                $table->uuid('country_id')->nullable()->after('contract_template_id')
                    ->comment('Country the contract was resolved for when signed (the signer\'s country).');
                $table->uuid('product_id')->nullable()->after('product_category_id')
                    ->comment('Set when the signature is for one product\'s individual contract.');

                $table->foreign('country_id', 'vc_country_foreign')->references('id')->on('countries')->nullOnDelete();
                $table->foreign('product_id', 'vc_product_foreign')->references('id')->on('products')->restrictOnDelete();
                $table->index(['country_id', 'category_scope', 'status'], 'vc_country_scope_status_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vendor_contracts', function (Blueprint $table) {
            $table->dropForeign('vc_country_foreign');
            $table->dropForeign('vc_product_foreign');
            $table->dropIndex('vc_country_scope_status_index');
            $table->dropColumn(['country_id', 'product_id']);
        });

        Schema::table('classified_contract_templates', function (Blueprint $table) {
            $table->dropUnique('cct_one_default_per_country_scope_unique');
            $table->dropColumn('default_key');
            $table->dropForeign('cct_country_foreign');
            $table->dropIndex('cct_country_scope_state_index');
            $table->dropColumn(['country_id', 'is_country_default']);
        });
    }
};
