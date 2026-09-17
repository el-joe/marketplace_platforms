<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * enhancement.md P-10 task 3: return inspection restocks need to write an
 * inventory movement that references the return_requests row, not the
 * generic 'adjustment' bucket the old admin restock code used. The
 * reference_type column is a real DB enum (not app-enforced), so widening
 * it needs a migration. Backfill-safe: adding a new enum value never
 * touches existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE inventory_movements MODIFY reference_type ENUM('order','inbound_shipment','transfer','adjustment','return') NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE inventory_movements SET reference_type = 'adjustment' WHERE reference_type = 'return'");
        DB::statement("ALTER TABLE inventory_movements MODIFY reference_type ENUM('order','inbound_shipment','transfer','adjustment') NULL");
    }
};
