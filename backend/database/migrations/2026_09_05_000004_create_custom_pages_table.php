<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->boolean('has_filters')->default(false)
                  ->comment('When true, the frontend shows the filter sidebar on this custom page.');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title_en')->nullable();
            $table->string('seo_title_ar')->nullable();
            $table->string('seo_description_en')->nullable();
            $table->string('seo_description_ar')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_pages');
    }
};
