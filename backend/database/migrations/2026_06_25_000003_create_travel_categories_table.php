<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('name_en', 150);
            $table->string('name_ar', 150);
            $table->string('slug', 150)->unique();
            $table->string('icon', 50)->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('travel_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_categories');
    }
};
