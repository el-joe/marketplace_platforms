<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_id')->index();
            $table->string('page_type', 30);
            $table->uuid('reference_id')->nullable(); // category_id | brand_id | seller_profile_id
            $table->string('name', 150);
            $table->string('slug', 255);
            $table->string('status', 20)->default('draft'); // draft|published|scheduled|archived
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('unpublish_at')->nullable();
            $table->uuid('published_by_admin_id')->nullable()->index();
            $table->uuid('last_edited_by_admin_id')->nullable()->index();
            $table->integer('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->string('og_image_url', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['country_id', 'slug']);
            $table->index(['country_id', 'page_type', 'is_default']);
            $table->index(['status', 'publish_at']);
            $table->index(['country_id', 'page_type', 'reference_id']);
            $table->index('deleted_at');

            $table->foreign('country_id')->references('id')->on('countries')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
