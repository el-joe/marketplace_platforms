<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-managed storefront presentation of classified categories (sections).
     *
     * - placements: JSON list of the storefront surfaces the section appears on (nav, landing, home).
     *   NULL = category from before this migration: keeps appearing in the navigation and on the landing page.
     * - description_en / description_ar: intro text of the category landing page (also its meta description).
     * - classified_category_country: the countries a category is offered in. No rows = every country, so
     *   existing categories stay visible everywhere.
     * The category image is a polymorphic `files` row (same mechanism as product categories); `icon` stays as is.
     */
    public function up(): void
    {
        Schema::table('classified_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('classified_categories', 'placements')) {
                $table->json('placements')->nullable()->after('icon');
            }

            if (! Schema::hasColumn('classified_categories', 'description_en')) {
                $table->text('description_en')->nullable()->after('placements');
                $table->text('description_ar')->nullable()->after('description_en');
            }
        });

        if (! Schema::hasTable('classified_category_country')) {
            Schema::create('classified_category_country', function (Blueprint $table) {
                $table->uuid('classified_category_id');
                $table->uuid('country_id');
                $table->timestamps();

                $table->primary(['classified_category_id', 'country_id'], 'ccc_category_country_primary');
                $table->foreign('classified_category_id', 'ccc_category_foreign')->references('id')->on('classified_categories')->cascadeOnDelete();
                $table->foreign('country_id', 'ccc_country_foreign')->references('id')->on('countries')->cascadeOnDelete();
                $table->index('country_id', 'ccc_country_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('classified_category_country');

        Schema::table('classified_categories', function (Blueprint $table) {
            $table->dropColumn(['placements', 'description_en', 'description_ar']);
        });
    }
};
