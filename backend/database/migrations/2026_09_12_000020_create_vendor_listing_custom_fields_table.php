<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_listing_custom_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_listing_id')->constrained()->cascadeOnDelete();
            $table->string('label_en')
                  ->comment('E.g. "Chest measurement (cm)" for made-to-measure items');
            $table->string('label_ar')->nullable();
            $table->enum('field_type', ['text', 'number', 'textarea', 'date'])->default('text');
            $table->string('placeholder_en')->nullable();
            $table->string('placeholder_ar')->nullable();
            $table->string('unit')->nullable()
                  ->comment('E.g. "cm", "kg" — shown next to the input');
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['vendor_listing_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_listing_custom_fields');
    }
};
