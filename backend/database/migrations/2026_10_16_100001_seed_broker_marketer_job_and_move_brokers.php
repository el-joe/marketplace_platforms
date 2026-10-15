<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Broker becomes a real marketer type (v3 refactor, R1).
 *
 * 1. Creates the `broker` marketer_jobs row if missing (and its 'product' eligibility row).
 * 2. Moves marketers who were registered as "broker" (previously aliased to affiliate, detectable by a
 *    broker specialization category on their profile) from the affiliate job to the broker job.
 *    Their category scope rows hang off the pivot row, so re-pointing the pivot keeps them.
 *
 * Idempotent: safe to run on a database that already has the broker job.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketer_jobs') || ! Schema::hasTable('marketer_marketer_job')) {
            return;
        }

        DB::transaction(function () {
            $brokerId = DB::table('marketer_jobs')->where('key', 'broker')->value('id');

            if (! $brokerId) {
                $brokerId = (string) Str::uuid();
                DB::table('marketer_jobs')->insert([
                    'id' => $brokerId,
                    'key' => 'broker',
                    'name_en' => 'Broker',
                    'name_ar' => 'وسيط',
                    'is_active' => true,
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasTable('marketer_job_categories')
                && ! DB::table('marketer_job_categories')->where('marketer_job_id', $brokerId)->where('category_type', 'product')->exists()) {
                DB::table('marketer_job_categories')->insert([
                    'id' => (string) Str::uuid(),
                    'marketer_job_id' => $brokerId,
                    'category_type' => 'product',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $affiliateId = DB::table('marketer_jobs')->where('key', 'affiliate')->value('id');
            if (! $affiliateId || ! Schema::hasColumn('marketer_profiles', 'broker_category_id')) {
                return;
            }

            $brokerMarketerIds = DB::table('marketer_profiles')->whereNotNull('broker_category_id')->pluck('marketer_id');

            foreach ($brokerMarketerIds->chunk(200) as $chunk) {
                foreach ($chunk as $marketerId) {
                    $affiliateRow = DB::table('marketer_marketer_job')
                        ->where('marketer_id', $marketerId)->where('marketer_job_id', $affiliateId)->first();
                    if (! $affiliateRow) {
                        continue;
                    }

                    $hasBroker = DB::table('marketer_marketer_job')
                        ->where('marketer_id', $marketerId)->where('marketer_job_id', $brokerId)->exists();

                    if ($hasBroker) {
                        // Already a broker: drop the stale affiliate assignment (its scope rows cascade).
                        DB::table('marketer_marketer_job')->where('id', $affiliateRow->id)->delete();
                    } else {
                        DB::table('marketer_marketer_job')->where('id', $affiliateRow->id)
                            ->update(['marketer_job_id' => $brokerId, 'updated_at' => now()]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Irreversible data move: broker rows stay (the former affiliate alias cannot be reconstructed).
    }
};
