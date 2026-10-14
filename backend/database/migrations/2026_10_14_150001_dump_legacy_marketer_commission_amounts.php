<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Data-safety step before the old Marketers-tab amount columns are dropped (next migration).
 *
 *  - Verifies the P1 copy: every affiliate_commission_amount > 0 must be present as affiliate_commission_flat
 *    (mode fixed); a row the copy missed is fixed here, never silently lost.
 *  - Writes every to-be-dropped value (influencer_commission_amount has no equivalent after D2/D11) to
 *    storage/app/legacy/marketer_commission_amounts_<date>.json so finance can review it, and logs the path.
 */
return new class extends Migration
{
    private const TABLE = 'marketer_commission_country_settings';

    public function up(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'influencer_commission_amount') || ! Schema::hasColumn(self::TABLE, 'affiliate_commission_amount')) {
            return;
        }

        $copied = DB::update(
            'UPDATE `'.self::TABLE.'`
             SET `affiliate_commission_flat` = `affiliate_commission_amount`, `affiliate_commission_mode` = \'fixed\'
             WHERE `affiliate_commission_amount` > 0 AND `affiliate_commission_flat` = 0'
        );
        if ($copied > 0) {
            Log::warning("marketer_commission_country_settings: {$copied} affiliate amount(s) had not been copied to affiliate_commission_flat; copied now.");
        }

        $rows = DB::table(self::TABLE)
            ->where(fn ($q) => $q->where('influencer_commission_amount', '>', 0)->orWhere('affiliate_commission_amount', '>', 0))
            ->get(['id', 'category_id', 'country_id', 'currency', 'influencer_commission_amount', 'affiliate_commission_amount', 'affiliate_commission_mode', 'affiliate_commission_flat']);

        if ($rows->isEmpty()) {
            Log::info('marketer_commission_country_settings: no legacy influencer/affiliate commission amounts to dump.');

            return;
        }

        $directory = storage_path('app/legacy');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/marketer_commission_amounts_'.now()->format('Y-m-d').'.json';

        File::put($path, json_encode([
            'dumped_at' => now()->toIso8601String(),
            'note' => 'influencer_commission_amount has no equivalent after the fee-share rewrite (D2/D11); affiliate_commission_amount lives on as affiliate_commission_flat (mode fixed).',
            'rows' => $rows->map(fn ($row) => (array) $row)->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Log::info('marketer_commission_country_settings: legacy amounts dumped to '.$path.' ('.$rows->count().' rows).');
    }

    public function down(): void
    {
        // Dump only; nothing to undo.
    }
};
