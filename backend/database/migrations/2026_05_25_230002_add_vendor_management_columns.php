<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add vendor management columns to existing vendor tables.
 * Uses hasColumn / hasIndex guards to be idempotent (safe after a partial run).
 *
 * - vendors:          add approved_at (timestamp), rejection_reason, ensure global_status has 'rejected'
 * - vendor_strikes:   add is_active flag, expand severity enum to include 'warning'
 * - vendor_documents: add file_path, notes, expand status enum to include 'verified' and 'expired'
 */
return new class extends Migration {
    public function up(): void
    {
        // ── vendors ───────────────────────────────────────────────────────────
        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by_admin_id');
            }
            if (!Schema::hasColumn('vendors', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_at');
            }
        });

        // Ensure status enum includes 'rejected' value (only if column exists with that name)
        if (Schema::hasColumn('vendors', 'global_status')) {
            DB::statement(
                "ALTER TABLE vendors MODIFY COLUMN global_status ENUM('pending','active','suspended','rejected','blacklisted','under_review') NOT NULL DEFAULT 'pending'"
            );
        } elseif (Schema::hasColumn('vendors', 'status')) {
            DB::statement(
                "ALTER TABLE vendors MODIFY COLUMN status ENUM('active','inactive','suspended','rejected') NOT NULL DEFAULT 'active'"
            );
        }

        // ── vendor_strikes ────────────────────────────────────────────────────
        // Expand severity enum to include 'warning' (idempotent)
        DB::statement(
            "ALTER TABLE vendor_strikes MODIFY COLUMN severity ENUM('warning','minor','major','critical') NULL DEFAULT 'minor'"
        );

        if (!Schema::hasColumn('vendor_strikes', 'is_active')) {
            Schema::table('vendor_strikes', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->index()->after('expires_at');
            });
        }

        // ── vendor_documents ──────────────────────────────────────────────────
        // Expand status enum to include 'verified' and 'expired' (idempotent)
        DB::statement(
            "ALTER TABLE vendor_documents MODIFY COLUMN status ENUM('pending','approved','verified','rejected','expired') NULL DEFAULT 'pending'"
        );

        Schema::table('vendor_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_documents', 'file_path')) {
                $table->string('file_path')->nullable()->after('document_type');
            }
            if (!Schema::hasColumn('vendor_documents', 'notes')) {
                $table->text('notes')->nullable()->after('rejection_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_documents', function (Blueprint $table) {
            $cols = array_filter(['file_path', 'notes'], fn($c) => Schema::hasColumn('vendor_documents', $c));
            if ($cols)
                $table->dropColumn(array_values($cols));
        });
        DB::statement("ALTER TABLE vendor_documents MODIFY COLUMN status ENUM('pending','approved','rejected') NULL DEFAULT 'pending'");

        if (Schema::hasColumn('vendor_strikes', 'is_active')) {
            Schema::table('vendor_strikes', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
        DB::statement("ALTER TABLE vendor_strikes MODIFY COLUMN severity ENUM('minor','major','critical') NULL DEFAULT 'minor'");

        DB::statement(
            "ALTER TABLE vendors MODIFY COLUMN global_status ENUM('pending','active','suspended','blacklisted','under_review') NOT NULL DEFAULT 'pending'"
        );

        Schema::table('vendors', function (Blueprint $table) {
            $cols = array_filter(['approved_at', 'rejection_reason'], fn($c) => Schema::hasColumn('vendors', $c));
            if ($cols)
                $table->dropColumn(array_values($cols));
        });
    }
};
