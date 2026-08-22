<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('blog_category_id')
                ->constrained('blog_categories')->restrictOnDelete();
            $table->foreignUuid('country_id')->nullable()
                ->constrained('countries')->nullOnDelete()
                ->comment('NULL = shown on all country portals; set to a specific country to restrict visibility to that portal only');
            $table->foreignUuid('author_admin_id')
                ->constrained('admins')->restrictOnDelete();

            // Bilingual content
            $table->string('title_en');
            $table->string('title_ar');
            $table->string('slug')->unique()
                ->comment('URL slug — shared across both languages, language is determined by the portal request locale');
            $table->string('excerpt_en')->nullable()
                ->comment('Short summary shown on listing cards, max 300 chars');
            $table->string('excerpt_ar')->nullable();
            $table->longText('body_en')
                ->comment('Full article body — HTML from a rich text editor');
            $table->longText('body_ar');
            $table->string('featured_image_path')->nullable()
                ->comment('Hero image shown at top of post and on listing cards');
            $table->string('featured_image_alt_en')->nullable();
            $table->string('featured_image_alt_ar')->nullable();

            // Publishing lifecycle
            $table->enum('status', ['draft', 'scheduled', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable()
                ->comment('When status became published — set automatically on first publish action, not manually editable');
            $table->timestamp('scheduled_for')->nullable()
                ->comment('When status=scheduled, auto-publishes at this datetime via a scheduled job');
            $table->timestamp('archived_at')->nullable();
            $table->foreignUuid('published_by_admin_id')->nullable()
                ->constrained('admins')->nullOnDelete();

            // SEO
            $table->string('seo_title_en')->nullable();
            $table->string('seo_title_ar')->nullable();
            $table->text('seo_description_en')->nullable();
            $table->text('seo_description_ar')->nullable();
            $table->string('og_image_path')->nullable()
                ->comment('Social share image — may differ from featured_image');

            // Engagement
            $table->unsignedInteger('views_count')->default(0);
            $table->boolean('allow_comments')->default(false)
                ->comment('Reserved for future use — blog launches without comments, toggle ready for when/if it is added');
            $table->boolean('is_featured')->default(false)
                ->comment('Pinned/featured post shown in a hero slot on the blog index — only one featured post per country should be active at a time; enforce at app layer');
            $table->unsignedInteger('reading_time_minutes')->default(0)
                ->comment('Computed from body word count at save time — ~200 words per minute average');

            // Tags (free-form, no separate table)
            $table->json('tags')->nullable()
                ->comment('Free-form string tags, e.g. ["ecommerce","tips"]');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['blog_category_id', 'status']);
            $table->index(['country_id', 'status', 'published_at']);
            $table->index(['is_featured', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
