<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->enum('weight_class', ['light', 'medium', 'heavy'])->nullable()->after('vendor_covers_delivery')
                ->comment('Display/filter helper only, not authoritative: light <= 1000g, medium 1001-5000g, heavy > 5000g');
            $table->enum('handling_class', ['standard', 'refrigerated', 'fragile', 'special_tech'])
                ->default('standard')->after('weight_class');
            $table->unsignedInteger('declared_weight_grams')->nullable()->after('handling_class')
                ->comment('Vendor-declared actual weight at listing time, used in shipping fee calculation; may differ from product_variants.weight_grams due to packaging');
            $table->decimal('declared_length_cm', 8, 2)->nullable()->after('declared_weight_grams')
                ->comment('Vendor-declared packaged dimension, may differ from product_variants (product-only) dimensions');
            $table->decimal('declared_width_cm', 8, 2)->nullable()->after('declared_length_cm');
            $table->decimal('declared_height_cm', 8, 2)->nullable()->after('declared_width_cm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_listings', function (Blueprint $table) {
            $table->dropColumn([
                'weight_class',
                'handling_class',
                'declared_weight_grams',
                'declared_length_cm',
                'declared_width_cm',
                'declared_height_cm',
            ]);
        });
    }
};
