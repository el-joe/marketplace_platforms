<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->index()->nullable();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug')->unique();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0.00);
            $table->integer('lft')->nullable();
            $table->integer('rgt')->nullable();
            $table->integer('depth')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->text('seo_title_ar')->nullable();
            $table->text('seo_title_en')->nullable();
            $table->text('seo_description_ar')->nullable();
            $table->text('seo_description_en')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });


        Schema::create('country_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_id')->index();
            $table->uuid('category_id')->index();
            $table->boolean('is_available')->default(true);
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->string('unavailable_reason', 100)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('updated_by_admin_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_categories');
        Schema::dropIfExists('categories');
    }
};
