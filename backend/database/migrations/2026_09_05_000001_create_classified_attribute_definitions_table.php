<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classified_attribute_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 60)->unique()
                  ->comment('Machine key stored in listings.attributes JSON — e.g. "year", "brand", "km"');
            $table->string('label_en', 120);
            $table->string('label_ar', 120);
            $table->enum('input_type', ['text', 'number', 'select', 'boolean'])
                  ->default('text');
            $table->json('options')->nullable()
                  ->comment('For select: [{"value":"new","label_en":"New","label_ar":"جديد"},…]');
            $table->string('unit_en', 30)->nullable();
            $table->string('unit_ar', 30)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classified_attribute_definitions');
    }
};
