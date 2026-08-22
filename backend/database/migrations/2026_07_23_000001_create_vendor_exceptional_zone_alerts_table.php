<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * A vendor's report that a destination zone's carrier fee exceeds the
     * customer-facing rate for one of their warehouses. Reviewed by an admin,
     * who either accepts it (creating a subsidy + exceptional-zone opt-in) or
     * rejects it (no records created).
     */
    public function up(): void
    {
        Schema::create('vendor_exceptional_zone_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();

            $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete()
                ->comment('The vendor warehouse shipping FROM');

            $table->foreignUuid('destination_zone_id')->constrained('shipping_zones')->cascadeOnDelete()
                ->comment('The destination zone where the carrier charges more than the customer rate');

            $table->foreignUuid('carrier_id')->nullable()
                ->constrained('shipping_carriers')->nullOnDelete()
                ->comment('Specific carrier vendor uses for this zone, NULL = applies to all carriers');

            $table->bigInteger('reported_carrier_fee')->default(0)
                ->comment('What the carrier actually charges the vendor for this zone. BIGINT base currency, no /100.');

            $table->char('currency', 3)->comment('Currency of the reported carrier fee');

            $table->text('vendor_note')->nullable();

            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');

            $table->text('admin_note')->nullable();

            $table->foreignUuid('reviewed_by_admin_id')->nullable()
                ->constrained('admins')->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->foreignUuid('created_subsidy_id')->nullable()
                ->constrained('platform_shipping_subsidies')->nullOnDelete()
                ->comment('platform_shipping_subsidies row created when admin accepted this alert');

            $table->timestamps();

            $table->index(['vendor_id', 'status'], 'vendor_zone_alerts_vendor_status_idx');
            $table->index(['warehouse_id', 'destination_zone_id'], 'vendor_zone_alerts_warehouse_zone_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_exceptional_zone_alerts');
    }
};
