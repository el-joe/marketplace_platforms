<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── FBN Inbound Requests ──────────────────────────────────────────────
        Schema::create('fbn_inbound_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('request_number', 30)->unique();
            $table->uuid('vendor_id')->index();
            $table->uuid('vendor_listing_id')->index();
            $table->uuid('warehouse_id')->index();
            $table->integer('quantity_requested');
            $table->integer('quantity_received')->default(0);
            $table->enum('status', ['draft', 'submitted', 'approved', 'shipped', 'received', 'rejected'])->default('draft');
            $table->uuid('admin_approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->date('expected_arrival')->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('vendor_notes')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('vendor_listing_id')->references('id')->on('vendor_listings')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            $table->foreign('admin_approved_by')->references('id')->on('admins')->nullOnDelete();
        });

        // ── FBN Storage Fees ──────────────────────────────────────────────────
        Schema::create('fbn_storage_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_id')->index();
            $table->uuid('warehouse_inventory_id')->index();
            $table->date('month')->comment('First day of the billed month e.g. 2026-06-01');
            $table->integer('units_stored');
            $table->bigInteger('rate_per_unit_cents');
            $table->bigInteger('total_fee_cents');
            $table->char('currency', 3);
            $table->enum('status', ['pending', 'invoiced', 'paid'])->default('pending');
            $table->timestamps();

            $table->unique(['vendor_id', 'warehouse_inventory_id', 'month']);
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('warehouse_inventory_id')->references('id')->on('warehouse_inventories')->cascadeOnDelete();
        });

        // ── Marketplace Shipping Rules ────────────────────────────────────────
        Schema::create('marketplace_shipping_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('vendor_listing_id')->unique()->index();
            $table->tinyInteger('requires_special_vehicle')->default(0);
            $table->tinyInteger('requires_refrigeration')->default(0);
            $table->decimal('max_weight_kg', 8, 2)->nullable();
            $table->string('max_dimensions_cm', 50)->nullable()->comment('LxWxH e.g. 200x100x80');
            $table->text('special_handling_notes')->nullable();
            $table->enum('commission_type', ['fixed', 'percentage', 'mixed'])->default('percentage');
            $table->decimal('commission_value', 10, 2);
            $table->bigInteger('extra_delivery_fee_cents')->default(0);
            $table->timestamps();

            $table->foreign('vendor_listing_id')->references('id')->on('vendor_listings')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_shipping_rules');
        Schema::dropIfExists('fbn_storage_fees');
        Schema::dropIfExists('fbn_inbound_requests');
    }
};
