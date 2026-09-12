<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_listing_addon_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_listing_id')->constrained()->cascadeOnDelete();
            $table->string('name_en')
                  ->comment('E.g. "Gift wrapping", "Engraving"');
            $table->string('name_ar')->nullable();
            $table->enum('selection_type', ['single', 'multiple'])->default('single')
                  ->comment('single = radio (choose one option), multiple = checkboxes');
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['vendor_listing_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_listing_addon_groups');
    }
};
