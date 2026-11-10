<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed bilingual content pages (About Us, Terms, Privacy, Return /
 * Shipping Policy, ...) shown on the seller portal and/or the customer
 * storefront, optionally linked from each site's footer. See App\Models\StaticPage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('static_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('slug', 150)->unique();
            $table->string('title_en');
            $table->string('title_ar');
            $table->longText('content_en')->nullable();
            $table->longText('content_ar')->nullable();

            $table->string('seo_title_en')->nullable();
            $table->string('seo_title_ar')->nullable();
            $table->string('seo_description_en', 500)->nullable();
            $table->string('seo_description_ar', 500)->nullable();

            // portal | storefront | both (see StaticPage::AUDIENCES)
            $table->string('audience', 20)->default('both');

            $table->boolean('show_in_footer')->default(false);
            // company | policies | help (see StaticPage::FOOTER_GROUPS); null = ungrouped
            $table->string('footer_group', 30)->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('is_active')->default(true);
            $table->foreignUuid('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Footer listing: WHERE show_in_footer=1 AND is_active=1 AND audience IN (?, 'both') ORDER BY sort_order
            $table->index(['show_in_footer', 'is_active', 'audience', 'sort_order'], 'static_pages_footer_index');
            // Admin index / sitemap listing per site
            $table->index(['audience', 'is_active'], 'static_pages_audience_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('static_pages');
    }
};
