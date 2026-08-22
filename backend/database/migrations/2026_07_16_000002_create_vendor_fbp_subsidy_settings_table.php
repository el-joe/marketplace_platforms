<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendor_fbp_subsidy_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('admin_subsidy_cents')
                ->default(0)
                ->comment('Fixed amount the admin/platform covers toward delivery cost per shipment');
            $table->integer('full_coverage_threshold_cents')
                ->default(0)
                ->comment('If total delivery cost is <= this, admin subsidy covers it fully (free_platform)');
            $table->json('exceptional_zone_shipping_zone_ids')
                ->nullable()
                ->comment('Shipping zone IDs treated as exceptional (higher-cost) zones eligible for combined admin+vendor coverage');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_fbp_subsidy_settings');
    }
};
