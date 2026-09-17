<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * enhancement.md P-07 task 2: `refunds.initiated_by_customer_id` is
 * NOT NULL, which blocks admin- or system-initiated refunds (there is no
 * customer to attribute a platform-fault or carrier-fault refund to).
 * Make it nullable and add a polymorphic `initiated_by_type`/
 * `initiated_by_id` pair so RefundService can record who actually
 * triggered the refund (customer, admin, or system) without forcing a
 * customer id.
 *
 * Backfill-safe: existing rows keep `initiated_by_customer_id` as-is; we
 * backfill `initiated_by_type`/`initiated_by_id` from it so historical
 * rows are queryable the same way going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('initiated_by_type', 20)->nullable()->after('initiated_by_customer_id');
            $table->char('initiated_by_id', 36)->nullable()->after('initiated_by_type');
        });

        DB::statement("UPDATE refunds SET initiated_by_type = 'customer', initiated_by_id = initiated_by_customer_id WHERE initiated_by_customer_id IS NOT NULL");

        // No doctrine/dbal in this project, so modify the column with raw
        // SQL rather than Blueprint::change().
        DB::statement('ALTER TABLE refunds MODIFY initiated_by_customer_id CHAR(36) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE refunds SET initiated_by_customer_id = initiated_by_id WHERE initiated_by_customer_id IS NULL AND initiated_by_type = 'customer'");
        DB::statement('ALTER TABLE refunds MODIFY initiated_by_customer_id CHAR(36) NOT NULL');

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn(['initiated_by_type', 'initiated_by_id']);
        });
    }
};
