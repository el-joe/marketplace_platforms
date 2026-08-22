<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fbn_daily_overage_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignUuid('warehouse_inventory_id')->constrained('warehouse_inventories')->cascadeOnDelete();
            $table->foreignUuid('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->date('received_at');
            $table->date('free_period_ends_at');
            $table->date('fee_date');
            $table->unsignedInteger('units');
            $table->bigInteger('fee_per_unit');
            $table->bigInteger('total_fee');
            $table->char('currency', 3);
            $table->enum('status', ['pending', 'invoiced', 'paid'])->default('pending');
            $table->timestamps();

            $table->unique(['warehouse_inventory_id', 'fee_date'], 'unique_inventory_fee_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fbn_daily_overage_fees');
    }
};
