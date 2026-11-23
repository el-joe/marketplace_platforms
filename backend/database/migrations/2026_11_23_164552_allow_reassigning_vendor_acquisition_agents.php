<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WS12 #15: vendor_acquisition_commissions had UNIQUE(vendor_id, admin_id), so once a deal with an agent was
 * revoked or expired the same agent could never be assigned to that vendor again (the insert failed with a
 * duplicate-key error). Deals are history: "one ACTIVE deal per vendor" is enforced in the application instead.
 * The new (vendor_id, status) index also backs the vendor_id foreign key once the unique index is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_acquisition_commissions', function (Blueprint $table) {
            $table->index(['vendor_id', 'status'], 'vac_vendor_status_index');
        });

        Schema::table('vendor_acquisition_commissions', function (Blueprint $table) {
            $table->dropUnique('vendor_acquisition_commissions_vendor_id_admin_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_acquisition_commissions', function (Blueprint $table) {
            $table->unique(['vendor_id', 'admin_id']);
        });

        Schema::table('vendor_acquisition_commissions', function (Blueprint $table) {
            $table->dropIndex('vac_vendor_status_index');
        });
    }
};
