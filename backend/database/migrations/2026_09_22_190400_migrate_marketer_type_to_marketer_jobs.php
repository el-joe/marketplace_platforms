<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $jobIds = $this->seedJobs();

            // affiliate job is scoped to 'product' categories (matches current broker_category_id behavior)
            $affiliateJobId = $jobIds['affiliate'];
            if (! DB::table('marketer_job_categories')
                ->where('marketer_job_id', $affiliateJobId)
                ->where('category_type', 'product')
                ->exists()) {
                DB::table('marketer_job_categories')->insert([
                    'id' => (string) Str::uuid(),
                    'marketer_job_id' => $affiliateJobId,
                    'category_type' => 'product',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Backfill marketer_marketer_job from marketers.marketer_type
            DB::table('marketers')->orderBy('id')->chunk(200, function ($marketers) use ($jobIds) {
                foreach ($marketers as $marketer) {
                    $type = $marketer->marketer_type;
                    if (! $type || ! isset($jobIds[$type])) {
                        continue;
                    }

                    $jobId = $jobIds[$type];

                    $exists = DB::table('marketer_marketer_job')
                        ->where('marketer_id', $marketer->id)
                        ->where('marketer_job_id', $jobId)
                        ->exists();

                    if (! $exists) {
                        DB::table('marketer_marketer_job')->insert([
                            'id' => (string) Str::uuid(),
                            'marketer_id' => $marketer->id,
                            'marketer_job_id' => $jobId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

            // Backfill marketer_marketer_job_category from marketer_profiles.broker_category_id
            DB::table('marketer_profiles')
                ->whereNotNull('broker_category_id')
                ->orderBy('id')
                ->chunk(200, function ($profiles) use ($affiliateJobId) {
                    foreach ($profiles as $profile) {
                        $mmj = DB::table('marketer_marketer_job')
                            ->where('marketer_id', $profile->marketer_id)
                            ->where('marketer_job_id', $affiliateJobId)
                            ->first();

                        if (! $mmj) {
                            continue;
                        }

                        $exists = DB::table('marketer_marketer_job_category')
                            ->where('marketer_marketer_job_id', $mmj->id)
                            ->where('category_type', 'product')
                            ->where('category_id', $profile->broker_category_id)
                            ->exists();

                        if (! $exists) {
                            DB::table('marketer_marketer_job_category')->insert([
                                'id' => (string) Str::uuid(),
                                'marketer_marketer_job_id' => $mmj->id,
                                'category_type' => 'product',
                                'category_id' => $profile->broker_category_id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                });
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $jobIds = DB::table('marketer_jobs')
                ->whereIn('key', ['influencer', 'affiliate'])
                ->pluck('id')
                ->all();

            if (empty($jobIds)) {
                return;
            }

            $mmjIds = DB::table('marketer_marketer_job')
                ->whereIn('marketer_job_id', $jobIds)
                ->pluck('id')
                ->all();

            if (! empty($mmjIds)) {
                DB::table('marketer_marketer_job_category')->whereIn('marketer_marketer_job_id', $mmjIds)->delete();
                DB::table('marketer_marketer_job')->whereIn('id', $mmjIds)->delete();
            }

            DB::table('marketer_job_categories')->whereIn('marketer_job_id', $jobIds)->delete();
            DB::table('marketer_jobs')->whereIn('id', $jobIds)->delete();
        });
    }

    /**
     * Idempotently seed the influencer/affiliate marketer_jobs rows.
     *
     * @return array<string, string> key => id
     */
    private function seedJobs(): array
    {
        $definitions = [
            'influencer' => ['name_en' => 'Influencer', 'name_ar' => 'مؤثر', 'sort_order' => 0],
            'affiliate' => ['name_en' => 'Affiliate', 'name_ar' => 'مسوق بالعمولة', 'sort_order' => 1],
        ];

        $ids = [];

        foreach ($definitions as $key => $attrs) {
            $existing = DB::table('marketer_jobs')->where('key', $key)->first();

            if ($existing) {
                $ids[$key] = $existing->id;

                continue;
            }

            $id = (string) Str::uuid();
            DB::table('marketer_jobs')->insert([
                'id' => $id,
                'key' => $key,
                'name_en' => $attrs['name_en'],
                'name_ar' => $attrs['name_ar'],
                'is_active' => true,
                'sort_order' => $attrs['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ids[$key] = $id;
        }

        return $ids;
    }
};
