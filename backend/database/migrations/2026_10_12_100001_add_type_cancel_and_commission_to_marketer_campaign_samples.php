<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promotion samples, per marketer + one fixed admin sample.
     *
     * - sample_type tells the three kinds apart (admin / influencer / affiliate)
     *   so each can be created exactly once.
     * - status gains `cancelled` (invitation rejected / timed out / campaign cancelled
     *   before the sample was dispatched).
     * - commission_amount snapshots the admin sale commission computed when the
     *   sample leaves stock (BIGINT base units); it is charged on the seller's
     *   statement exactly once (see payout_items.campaign_sample_id).
     */
    public function up(): void
    {
        Schema::table('marketer_campaign_samples', function (Blueprint $table) {
            $table->string('sample_type', 20)->default('influencer')->after('sample_owner');
            $table->unsignedBigInteger('commission_amount')->default(0)->after('stock_deducted_at');
            $table->char('commission_currency', 3)->nullable()->after('commission_amount');
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
        });

        DB::statement("ALTER TABLE marketer_campaign_samples MODIFY COLUMN status ENUM('pending','dispatched','delivered','returned','cancelled') NOT NULL DEFAULT 'pending'");

        DB::table('marketer_campaign_samples')->where('sample_owner', 'platform')->update(['sample_type' => 'admin']);
    }

    public function down(): void
    {
        DB::table('marketer_campaign_samples')->where('status', 'cancelled')->update(['status' => 'pending']);
        DB::statement("ALTER TABLE marketer_campaign_samples MODIFY COLUMN status ENUM('pending','dispatched','delivered','returned') NOT NULL DEFAULT 'pending'");

        Schema::table('marketer_campaign_samples', function (Blueprint $table) {
            $table->dropColumn(['sample_type', 'commission_amount', 'commission_currency', 'cancelled_at']);
        });
    }
};
