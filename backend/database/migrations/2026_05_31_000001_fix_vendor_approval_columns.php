<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Step 1: Make approved_at nullable so we can clear existing 0/1 values.
        DB::statement("ALTER TABLE vendors MODIFY approved_at TINYINT(1) NULL DEFAULT NULL");

        // Step 2: Set all rows to NULL — the column held boolean 0/1, not real
        // timestamps. Approved vendors retain their status via global_status.
        DB::statement("UPDATE vendors SET approved_at = NULL");

        // Step 3: Convert the now-empty column to a proper nullable timestamp.
        DB::statement("ALTER TABLE vendors MODIFY approved_at TIMESTAMP NULL DEFAULT NULL");

        // Step 4: Restore a best-effort approval timestamp for already-active vendors.
        DB::statement("UPDATE vendors SET approved_at = updated_at WHERE global_status = 'active' AND approved_at IS NULL");

        // Step 5: Add onboarding_completed_at if missing.
        if (!Schema::hasColumn('vendors', 'onboarding_completed_at')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->timestamp('onboarding_completed_at')->nullable()->after('approved_by_admin_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'onboarding_completed_at')) {
                $table->dropColumn('onboarding_completed_at');
            }

            $table->boolean('approved_at')->default(false)->change();
        });
    }
};
