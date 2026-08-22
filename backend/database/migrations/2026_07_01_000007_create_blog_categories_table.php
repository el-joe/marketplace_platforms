<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()
                ->comment('Supports one level of sub-categories — a category with a parent cannot itself be a parent (enforce at app layer, not DB constraint, for simplicity)');
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('slug')->unique();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('color_hex', 7)->nullable()
                ->comment('Brand accent color for this category, e.g. #3B82F6 — used for category pill/badge on the portal');
            $table->string('icon_name')->nullable()
                ->comment('Heroicon or similar name for category badge');
            $table->string('seo_title_en')->nullable();
            $table->string('seo_title_ar')->nullable();
            $table->text('seo_description_en')->nullable();
            $table->text('seo_description_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
            $table->foreign('parent_id')->references('id')->on('blog_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};
