<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Influencer platform fee: VAT snapshot (countries.vat_rate at acceptance),
     * how/when it was settled, and the `influencer_fee` weekly-statement line.
     * payout_items.invitation_id is UNIQUE: an invitation fee can be on at most
     * one statement, and the status guard (pending -> paid under a row lock)
     * makes "wallet OR statement, never both" hold.
     */
    public function up(): void
    {
        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->decimal('platform_fee_vat_rate', 5, 2)->default(0)->after('platform_fee_amount');
            $table->bigInteger('platform_fee_vat_amount')->default(0)->after('platform_fee_vat_rate');
            $table->string('platform_fee_paid_via', 12)->nullable()->after('platform_fee_status'); // wallet|statement|admin
            $table->timestamp('platform_fee_paid_at')->nullable()->after('platform_fee_paid_via');
        });

        DB::statement("ALTER TABLE payout_items MODIFY COLUMN item_type ENUM('sub_order','marketer_commission','promotion_sample','influencer_fee') NOT NULL DEFAULT 'sub_order'");

        Schema::table('payout_items', function (Blueprint $table) {
            $table->bigInteger('vat_amount')->default(0)->after('net');
            $table->foreignUuid('invitation_id')->nullable()->after('campaign_sample_id')
                ->constrained('marketer_campaign_invitations')->nullOnDelete();
            $table->unique('invitation_id');
        });
    }

    public function down(): void
    {
        DB::table('payout_items')->where('item_type', 'influencer_fee')->delete();

        Schema::table('payout_items', function (Blueprint $table) {
            $table->dropUnique(['invitation_id']);
            $table->dropConstrainedForeignId('invitation_id');
            $table->dropColumn('vat_amount');
        });

        DB::statement("ALTER TABLE payout_items MODIFY COLUMN item_type ENUM('sub_order','marketer_commission','promotion_sample') NOT NULL DEFAULT 'sub_order'");

        Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
            $table->dropColumn(['platform_fee_vat_rate', 'platform_fee_vat_amount', 'platform_fee_paid_via', 'platform_fee_paid_at']);
        });
    }
};
