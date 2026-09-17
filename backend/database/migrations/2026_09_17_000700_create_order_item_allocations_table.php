<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-13 task 2: record the EXACT warehouse_inventory row
 * that was reserved (and later committed/released/returned) for every
 * order_item, instead of every later step re-deriving "the" row from
 * (vendor_listing_id|admin_listing_id) + sub_orders.warehouse_id — which
 * silently breaks for a listing stocked in more than one warehouse.
 *
 * One order_item can, in principle, be split across more than one
 * warehouse_inventory row (partial availability at the preferred
 * warehouse), so this is a one-to-many child table rather than a single
 * warehouse_inventory_id column on order_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_item_id');
            $table->uuid('warehouse_inventory_id');
            $table->integer('quantity');
            $table->enum('status', ['reserved', 'committed', 'released', 'returned'])->default('reserved');
            $table->timestamps();

            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('warehouse_inventory_id')->references('id')->on('warehouse_inventories')->cascadeOnDelete();
            $table->index(['order_item_id', 'status']);
            $table->index(['warehouse_inventory_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_allocations');
    }
};
