<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add country_id (nullable) to category_commission_tiers so tiers can be
     * scoped per-country (matching the country_categories override pattern).
     *
     * NULL country_id = global default (applies when no country-specific tier matches).
     * Non-null = overrides the global tier for that country only.
     */
    public function up(): void
    {
        // Re-runnable: an earlier attempt added the column + FK before failing on the index step.
        if (! Schema::hasColumn('category_commission_tiers', 'country_id')) {
            Schema::table('category_commission_tiers', function (Blueprint $table) {
                $table->uuid('country_id')->nullable()->after('category_id')
                    ->comment('NULL = global tier; non-null = applies to this country only');
            });
        }

        $hasForeign = collect(Schema::getForeignKeys('category_commission_tiers'))
            ->contains(fn ($fk) => $fk['columns'] === ['country_id']);
        if (! $hasForeign) {
            Schema::table('category_commission_tiers', function (Blueprint $table) {
                $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
            });
        }

        $indexes = collect(Schema::getIndexes('category_commission_tiers'))->pluck('name');

        if (! $indexes->contains('cct_category_country_sort_index')) {
            Schema::table('category_commission_tiers', function (Blueprint $table) {
                $table->index(['category_id', 'country_id', 'sort_order'], 'cct_category_country_sort_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('category_commission_tiers', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropIndex('cct_category_country_sort_index');
            $table->dropColumn('country_id');
        });
    }
};
