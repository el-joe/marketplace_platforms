<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Extend item_type enum to include marketer_commission
        if (Schema::hasColumn('payout_items', 'item_type')) {
            DB::statement("ALTER TABLE payout_items MODIFY COLUMN item_type
                ENUM('sub_order', 'marketer_commission') NOT NULL DEFAULT 'sub_order'");
        } else {
            DB::statement("ALTER TABLE payout_items ADD COLUMN item_type
                ENUM('sub_order', 'marketer_commission') NOT NULL DEFAULT 'sub_order' AFTER sub_order_id");
        }

        // Add marketer conversion reference column
        Schema::table('payout_items', function (Blueprint $table) {
            $table->foreignUuid('marketer_conversion_id')
                  ->nullable()
                  ->after('sub_order_id')
                  ->constrained('marketer_campaign_conversions')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payout_items', function (Blueprint $table) {
            $table->dropForeign(['marketer_conversion_id']);
            $table->dropColumn('marketer_conversion_id');
        });
        DB::statement("ALTER TABLE payout_items MODIFY COLUMN item_type
            ENUM('sub_order') NOT NULL DEFAULT 'sub_order'");
    }
};
