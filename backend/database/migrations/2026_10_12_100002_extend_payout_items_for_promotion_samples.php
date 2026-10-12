<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Statement lines that are not a sub-order: a promotion sample is a 0-priced
     * line carrying only the admin commission. The unique campaign_sample_id is
     * the hard guarantee that a sample is charged on a statement at most once.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE payout_items MODIFY COLUMN sub_order_id CHAR(36) NULL');
        DB::statement("ALTER TABLE payout_items MODIFY COLUMN item_type ENUM('sub_order','marketer_commission','promotion_sample') NOT NULL DEFAULT 'sub_order'");

        Schema::table('payout_items', function (Blueprint $table) {
            if (! Schema::hasColumn('payout_items', 'description')) {
                $table->string('description', 255)->nullable()->after('net');
            }
            if (! Schema::hasColumn('payout_items', 'campaign_sample_id')) {
                $table->foreignUuid('campaign_sample_id')->nullable()->after('marketer_conversion_id')
                    ->constrained('marketer_campaign_samples')->nullOnDelete();
                $table->unique('campaign_sample_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payout_items', function (Blueprint $table) {
            $table->dropUnique(['campaign_sample_id']);
            $table->dropConstrainedForeignId('campaign_sample_id');
            $table->dropColumn('description');
        });

        DB::table('payout_items')->where('item_type', 'promotion_sample')->delete();
        DB::statement("ALTER TABLE payout_items MODIFY COLUMN item_type ENUM('sub_order','marketer_commission') NOT NULL DEFAULT 'sub_order'");
        DB::statement('ALTER TABLE payout_items MODIFY COLUMN sub_order_id CHAR(36) NOT NULL');
    }
};
