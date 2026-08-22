<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Links an accepted vendor_exceptional_zone_alerts row to the
     * platform_shipping_subsidies / warehouse_exceptional_zones rows an
     * admin created for it.
     */
    public function up(): void
    {
        Schema::create('vendor_exceptional_zone_alert_results', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('alert_id')
                ->constrained('vendor_exceptional_zone_alerts')->cascadeOnDelete();

            $table->foreignUuid('shipping_zone_id')
                ->constrained('shipping_zones')->cascadeOnDelete()
                ->comment('Zone admin selected when resolving this alert');

            $table->foreignUuid('shipping_method_id')
                ->constrained('shipping_methods')->cascadeOnDelete();

            $table->foreignUuid('created_subsidy_id')->nullable()
                ->constrained('platform_shipping_subsidies')->nullOnDelete();

            $table->foreignUuid('created_exceptional_zone_id')->nullable()
                ->constrained('warehouse_exceptional_zones', indexName: 'vezar_created_exceptional_zone_id_foreign')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_exceptional_zone_alert_results');
    }
};
