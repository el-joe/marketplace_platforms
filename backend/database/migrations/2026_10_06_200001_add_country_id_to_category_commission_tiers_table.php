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
        Schema::table('category_commission_tiers', function (Blueprint $table) {
            $table->uuid('country_id')->nullable()->after('category_id')
                ->comment('NULL = global tier; non-null = applies to this country only');
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
        });

        // Drop the old (category_id, sort_order) index and replace with one that
        // includes country_id so queries filtering by category + country are indexed.
        Schema::table('category_commission_tiers', function (Blueprint $table) {
            $table->dropIndex('category_commission_tiers_category_sort_index');
            $table->index(['category_id', 'country_id', 'sort_order'], 'cct_category_country_sort_index');
        });
    }

    public function down(): void
    {
        Schema::table('category_commission_tiers', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropIndex('cct_category_country_sort_index');
            $table->dropColumn('country_id');
            $table->index(['category_id', 'sort_order'], 'category_commission_tiers_category_sort_index');
        });
    }
};
