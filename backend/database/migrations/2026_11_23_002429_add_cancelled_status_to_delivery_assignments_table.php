<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WS2 (order cancellation consistency): an open delivery assignment (assigned / accepted / picked_up) of a
 * sub-order that gets cancelled is closed as 'cancelled' instead of being left open for the rider, or being
 * mislabelled 'failed' (which would count against the agent and the RTO fail limit).
 */
return new class extends Migration
{
    private const OLD_ENUM = "ENUM('assigned','accepted','picked_up','delivered','failed')";

    private const NEW_ENUM = "ENUM('assigned','accepted','picked_up','delivered','failed','cancelled')";

    public function up(): void
    {
        if ($this->isMysql()) {
            DB::statement('ALTER TABLE `delivery_assignments` MODIFY COLUMN `status` '.self::NEW_ENUM." NOT NULL DEFAULT 'assigned'");
        }

        if (! Schema::hasColumn('delivery_assignments', 'cancelled_at')) {
            Schema::table('delivery_assignments', function (Blueprint $table) {
                $table->timestamp('cancelled_at')->nullable()->after('failed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('delivery_assignments', 'cancelled_at')) {
            Schema::table('delivery_assignments', function (Blueprint $table) {
                $table->dropColumn('cancelled_at');
            });
        }

        if ($this->isMysql()) {
            DB::table('delivery_assignments')->where('status', 'cancelled')->update(['status' => 'failed']);
            DB::statement('ALTER TABLE `delivery_assignments` MODIFY COLUMN `status` '.self::OLD_ENUM." NOT NULL DEFAULT 'assigned'");
        }
    }

    private function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
