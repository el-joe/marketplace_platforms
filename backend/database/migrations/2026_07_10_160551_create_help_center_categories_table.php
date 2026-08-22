<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_center_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()
                ->comment('Supports one level of sub-categories — a category with a parent cannot itself be a parent (enforce at app layer, not DB constraint, for simplicity)');
            $table->string('country_id', 20)->nullable()
                ->comment('Matches countries.site_code (the {country} portal route segment). Null = shown for every country.');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable()
                ->comment('Icon URL shown on the category card (defaults to a noon-style svg icon)');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
            $table->index('country_id');
            $table->foreign('parent_id')->references('id')->on('help_center_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_center_categories');
    }
};
