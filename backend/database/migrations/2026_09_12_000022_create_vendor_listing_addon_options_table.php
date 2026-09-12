<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_listing_addon_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('addon_group_id')->constrained('vendor_listing_addon_groups')->cascadeOnDelete();
            $table->string('name_en');
            $table->string('name_ar')->nullable();
            $table->bigInteger('extra_price')->default(0)
                  ->comment('Additional price for this option, BASE CURRENCY CENTS/FILS. No decimals.');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['addon_group_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_listing_addon_options');
    }
};
