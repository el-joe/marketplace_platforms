<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packaging_supplies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name_en', 150);
            $table->string('name_ar', 150);
            $table->enum('type', ['box', 'bag', 'tape', 'label', 'other']);
            $table->string('size', 50)->nullable();
            $table->unsignedBigInteger('unit_cost_cents')->default(0);
            $table->integer('stock_available')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('image_path', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packaging_supplies');
    }
};
