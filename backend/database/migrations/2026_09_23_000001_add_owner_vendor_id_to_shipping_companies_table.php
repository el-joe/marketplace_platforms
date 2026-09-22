<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FBM (vendor-owned shipping) — client feature request section 4.
 *
 * Adds vendor-scoping to shipping companies: null = public shipping company
 * visible to every vendor (existing behavior, unchanged); non-null = private
 * to that vendor only (e.g. a vendor's own in-house delivery staff).
 *
 * No FK constraint is declared, matching the existing `warehouses.owner_vendor_id`
 * column (see mysql-schema.sql) which uses a plain indexed column instead of a
 * hard foreign key to the vendors table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_companies', function (Blueprint $table) {
            $table->char('owner_vendor_id', 36)->nullable()->after('country_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('shipping_companies', function (Blueprint $table) {
            $table->dropIndex(['owner_vendor_id']);
            $table->dropColumn('owner_vendor_id');
        });
    }
};
