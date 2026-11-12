<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-country static pages. `country_id` NULL = a global page shown in every
 * country; a row with a country_id overrides the global page with the same slug
 * for that country (or adds a page only that country has).
 *
 * The slug is therefore unique per country instead of globally. MySQL treats
 * NULLs as distinct in a unique index, so "one global page per slug" is
 * enforced by the admin validation (Admin\StaticPageController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('static_pages', function (Blueprint $table) {
            $table->foreignUuid('country_id')->nullable()->after('id')
                ->constrained('countries')->nullOnDelete();
        });

        Schema::table('static_pages', function (Blueprint $table) {
            $table->dropUnique('static_pages_slug_unique');
            $table->unique(['slug', 'country_id'], 'static_pages_slug_country_unique');
            $table->index('slug', 'static_pages_slug_index');
        });
    }

    public function down(): void
    {
        Schema::table('static_pages', function (Blueprint $table) {
            $table->dropUnique('static_pages_slug_country_unique');
            $table->dropIndex('static_pages_slug_index');
            $table->dropConstrainedForeignId('country_id');
            $table->unique('slug');
        });
    }
};
