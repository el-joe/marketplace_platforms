<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classified_category_attribute_map', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('classified_category_id', 36)->index('clsf_cat_attr_map_category_idx');
            $table->char('classified_attribute_definition_id', 36)->index('clsf_cat_attr_map_definition_idx');
            $table->boolean('is_required')->default(false)
                  ->comment('Seller must fill this when creating a listing');
            $table->boolean('is_shown_on_card')->default(false)
                  ->comment('Show as a pill on the browse listing card');
            $table->boolean('is_filterable')->default(false)
                  ->comment('Expose as a sidebar filter on browse page');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['classified_category_id', 'classified_attribute_definition_id'],
                'clsf_cat_attr_unique'
            );

            $table->foreign('classified_category_id', 'clsf_cat_attr_map_category_fk')
                  ->references('id')->on('classified_categories')
                  ->onDelete('cascade');

            $table->foreign('classified_attribute_definition_id', 'clsf_cat_attr_map_definition_fk')
                  ->references('id')->on('classified_attribute_definitions')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classified_category_attribute_map');
    }
};
