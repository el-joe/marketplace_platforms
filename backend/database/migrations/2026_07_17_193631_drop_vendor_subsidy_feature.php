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
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropColumn(['admin_subsidy', 'vendor_deduction', 'delivery_subsidy_ledgered']);
        });

        Schema::dropIfExists('vendor_subsidy_settings');
        Schema::dropIfExists('vendor_exceptional_zones');
        Schema::dropIfExists('vendor_fbp_subsidy_settings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('vendor_fbp_subsidy_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('admin_subsidy')->default(0);
            $table->integer('full_coverage_threshold')->default(0);
            $table->json('exceptional_zone_shipping_zone_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vendor_exceptional_zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['vendor_id', 'shipping_zone_id']);
        });

        Schema::create('vendor_subsidy_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('country_id')->constrained()->cascadeOnDelete();
            $table->integer('admin_support')->default(0);
            $table->integer('vendor_share')->default(0);
            $table->string('currency', 3);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'country_id', 'effective_from', 'effective_until'], 'vendor_subsidy_settings_lookup_idx');
        });

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_subsidy')->default(0)->after('shipping');
            $table->unsignedBigInteger('vendor_deduction')->default(0)->after('admin_subsidy');
            $table->boolean('delivery_subsidy_ledgered')->default(false)->after('vendor_deduction');
        });
    }
};
