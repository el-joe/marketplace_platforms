<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_page_category_map', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('custom_page_id', 36)->index('custom_page_cat_map_page_idx');
            $table->char('category_id', 36)->index('custom_page_cat_map_category_idx');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['custom_page_id', 'category_id'], 'custom_page_cat_unique');

            $table->foreign('custom_page_id', 'custom_page_cat_map_page_fk')
                  ->references('id')->on('custom_pages')
                  ->onDelete('cascade');

            $table->foreign('category_id', 'custom_page_cat_map_category_fk')
                  ->references('id')->on('categories')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_page_category_map');
    }
};
