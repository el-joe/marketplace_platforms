<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classified_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name_en', 150);
            $table->string('name_ar', 150);
            $table->string('slug', 150)->unique();
            $table->string('icon', 50)->nullable();
            $table->uuid('parent_id')->nullable();
            $table->tinyInteger('requires_location_map')->default(0);
            $table->tinyInteger('requires_sketch_upload')->default(0);
            $table->uuid('contract_template_id')->nullable();
            $table->json('required_attachment_types')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('classified_categories')->nullOnDelete();
            $table->foreign('contract_template_id')->references('id')->on('classified_contract_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classified_categories');
    }
};
