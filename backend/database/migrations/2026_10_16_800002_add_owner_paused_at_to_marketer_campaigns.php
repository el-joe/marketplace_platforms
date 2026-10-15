<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v3 refactor (R8): an owner (e.g. a travel agency) can pause its own campaign on purpose. The availability sweep
 * (MarketerCampaignService::checkStockAndUpdateStatus) resumes campaigns it paused itself when the item becomes
 * available again; a campaign paused by its owner must stay paused until the owner resumes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('marketer_campaigns', 'owner_paused_at')) {
            Schema::table('marketer_campaigns', function (Blueprint $table) {
                $table->timestamp('owner_paused_at')->nullable()->comment('Set while the owner has paused the campaign on purpose');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('marketer_campaigns', 'owner_paused_at')) {
            Schema::table('marketer_campaigns', fn (Blueprint $table) => $table->dropColumn('owner_paused_at'));
        }
    }
};
