<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explicit "requires individual contract" switches and per-country contract assignments.
     *
     * - categories / classified_categories.requires_contract: nullable on purpose. NULL = legacy row that never
     *   used the switch, treated as "required when a contract template is linked" (the behaviour before this
     *   migration). Existing rows are backfilled so current behaviour is unchanged.
     * - products.contract_requirement: inherit | required | exempt (NULL = inherit).
     * - products.contract_template_id: the product's contract for countries without a specific assignment.
     * - contract_country_assignments: which template a category / product uses for one country.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'requires_contract')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->boolean('requires_contract')->nullable()->after('contract_template_id')
                    ->comment('NULL = legacy: required when contract_template_id is set.');
            });
        }

        if (! Schema::hasColumn('classified_categories', 'requires_contract')) {
            Schema::table('classified_categories', function (Blueprint $table) {
                $table->boolean('requires_contract')->nullable()->after('contract_template_id')
                    ->comment('NULL = legacy: required when contract_template_id is set.');
            });
        }

        if (! Schema::hasColumn('products', 'contract_requirement')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('contract_requirement', 16)->nullable()->after('is_hazardous')
                    ->comment('inherit | required | exempt. NULL = inherit from the category.');
                $table->uuid('contract_template_id')->nullable()->after('contract_requirement');

                $table->foreign('contract_template_id', 'products_contract_template_foreign')
                    ->references('id')->on('classified_contract_templates')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('contract_country_assignments')) {
            Schema::create('contract_country_assignments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('country_id');
                $table->uuid('contract_template_id');
                $table->uuid('product_category_id')->nullable();
                $table->uuid('classified_category_id')->nullable();
                $table->uuid('product_id')->nullable();
                $table->timestamps();

                $table->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();
                $table->foreign('contract_template_id')->references('id')->on('classified_contract_templates')->cascadeOnDelete();
                $table->foreign('product_category_id')->references('id')->on('categories')->cascadeOnDelete();
                $table->foreign('classified_category_id')->references('id')->on('classified_categories')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

                $table->unique(['product_category_id', 'country_id'], 'cca_product_category_country_unique');
                $table->unique(['classified_category_id', 'country_id'], 'cca_classified_category_country_unique');
                $table->unique(['product_id', 'country_id'], 'cca_product_country_unique');
                $table->index('contract_template_id', 'cca_template_index');
            });
        }

        $this->backfill();
    }

    /**
     * Existing links keep requiring a contract: the switch is ON wherever a template is linked, OFF elsewhere.
     * Only rows that never had the switch set are touched, so running it again changes nothing.
     */
    public function backfill(): void
    {
        foreach (['categories', 'classified_categories'] as $table) {
            DB::table($table)->whereNull('requires_contract')->whereNotNull('contract_template_id')->update(['requires_contract' => true]);
            DB::table($table)->whereNull('requires_contract')->whereNull('contract_template_id')->update(['requires_contract' => false]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_country_assignments');

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign('products_contract_template_foreign');
            $table->dropColumn(['contract_requirement', 'contract_template_id']);
        });

        Schema::table('classified_categories', function (Blueprint $table) {
            $table->dropColumn('requires_contract');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('requires_contract');
        });
    }
};
