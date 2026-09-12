<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!$this->indexExists('marketer_listings', 'ml_marketer_country_status_sold')) {
            Schema::table('marketer_listings', function (Blueprint $table) {
                $table->index(['marketer_id', 'country_id', 'status', 'total_sold'], 'ml_marketer_country_status_sold');
            });
        }

        if (!$this->indexExists('marketer_campaign_invitations', 'mci_marketer_status_idx')) {
            Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
                $table->index(['marketer_id', 'status'], 'mci_marketer_status_idx');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
        return !empty($indexes);
    }

    public function down(): void
    {
        if ($this->indexExists('marketer_listings', 'ml_marketer_country_status_sold')) {
            Schema::table('marketer_listings', function (Blueprint $table) {
                $table->dropIndex('ml_marketer_country_status_sold');
            });
        }

        if ($this->indexExists('marketer_campaign_invitations', 'mci_marketer_status_idx')) {
            Schema::table('marketer_campaign_invitations', function (Blueprint $table) {
                $table->dropIndex('mci_marketer_status_idx');
            });
        }
    }
};
