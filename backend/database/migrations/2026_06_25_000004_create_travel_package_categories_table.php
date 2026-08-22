<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Judgment call: many-to-many pivot — a package can belong to multiple categories
// (e.g. "Beach" AND "Family-friendly"). Confirm with product team if single-category
// is actually wanted; if so, replace this with a travel_category_id FK on travel_packages.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_package_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('travel_package_id');
            $table->uuid('travel_category_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('travel_package_id')->references('id')->on('travel_packages')->cascadeOnDelete();
            $table->foreign('travel_category_id')->references('id')->on('travel_categories')->cascadeOnDelete();
            $table->unique(['travel_package_id', 'travel_category_id'], 'tp_categories_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_package_categories');
    }
};
