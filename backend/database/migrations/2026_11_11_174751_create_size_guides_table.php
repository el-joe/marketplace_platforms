<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendor-owned, reusable size guides ("size map" pages) attached per
     * vendor listing. Rows live in their own table so the storefront can
     * filter a category by size label with a plain indexed join.
     */
    public function up(): void
    {
        Schema::create('size_guides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('name');
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('intro_en')->nullable();
            $table->text('intro_ar')->nullable();
            $table->text('how_to_measure_en')->nullable();
            $table->text('how_to_measure_ar')->nullable();
            $table->string('unit', 4)->default('cm');
            $table->json('columns');
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index('vendor_id');
        });

        Schema::create('size_guide_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('size_guide_id')->constrained('size_guides')->cascadeOnDelete();
            $table->string('size_label', 50);
            $table->json('values');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['size_guide_id', 'sort_order']);
            $table->index('size_label');
        });

        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->foreignUuid('size_guide_id')->nullable()->constrained('size_guides')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('size_guide_id');
        });
        Schema::dropIfExists('size_guide_rows');
        Schema::dropIfExists('size_guides');
    }
};
