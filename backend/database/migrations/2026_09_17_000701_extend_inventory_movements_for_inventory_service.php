<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-13 task 6:
 * - reference_type is missing values the new InventoryService's callers
 *   need (sub_order, campaign_sample, warranty_replacement, rto).
 *   'return' and the others already exist from earlier prompts.
 * - created_by_user_id is NOT NULL in the live schema but several
 *   existing call sites already write null (vendor/system-initiated
 *   movements) — widen it and add actor_type so every movement can say
 *   *who* (or what) caused it even when there is no users-table id.
 * Backfill-safe: widening an enum and adding a nullable column never
 * touches existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE inventory_movements MODIFY reference_type ENUM('order','inbound_shipment','transfer','adjustment','return','sub_order','campaign_sample','warranty_replacement','rto') NULL");

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('actor_type', 30)->nullable()->after('created_by_user_id');
        });

        DB::statement('ALTER TABLE inventory_movements MODIFY created_by_user_id CHAR(36) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE inventory_movements SET reference_type = 'adjustment' WHERE reference_type IN ('sub_order','campaign_sample','warranty_replacement','rto')");
        DB::statement("ALTER TABLE inventory_movements MODIFY reference_type ENUM('order','inbound_shipment','transfer','adjustment','return') NULL");

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn('actor_type');
        });
    }
};
