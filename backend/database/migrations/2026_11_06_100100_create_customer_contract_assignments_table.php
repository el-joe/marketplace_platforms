<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which customer purchase contract a classified category uses, for one country or for every country without a
     * contract of its own (country_id NULL = the legacy / all-countries contract).
     *
     * Customer contracts have their own assignment table so that the seller contract of a category (contract_country_assignments,
     * classified_categories.contract_template_id) and its customer purchase contract can never be mixed up: the two kinds
     * are independent and a category can carry both.
     */
    public function up(): void
    {
        if (Schema::hasTable('customer_contract_assignments')) {
            return;
        }

        Schema::create('customer_contract_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('classified_category_id');
            $table->uuid('country_id')->nullable();
            $table->uuid('contract_template_id');
            $table->timestamps();

            $table->string('scope_key', 80)->virtualAs("CONCAT(classified_category_id, '|', IFNULL(country_id, 'all'))");

            $table->foreign('classified_category_id', 'cca2_category_foreign')->references('id')->on('classified_categories')->cascadeOnDelete();
            $table->foreign('country_id', 'cca2_country_foreign')->references('id')->on('countries')->restrictOnDelete();
            $table->foreign('contract_template_id', 'cca2_template_foreign')->references('id')->on('classified_contract_templates')->cascadeOnDelete();

            $table->unique('scope_key', 'cca2_one_contract_per_category_country_unique');
            $table->index('contract_template_id', 'cca2_template_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contract_assignments');
    }
};
