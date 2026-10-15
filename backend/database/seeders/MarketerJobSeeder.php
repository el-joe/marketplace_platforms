<?php

namespace Database\Seeders;

use App\Models\MarketerJob;
use App\Models\MarketerJobCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the base marketer_jobs lookup rows (influencer, affiliate, broker) for fresh
 * installs. Mirrors the idempotent-by-key logic of the
 * 2026_09_22_190400_migrate_marketer_type_to_marketer_jobs data migration.
 */
class MarketerJobSeeder extends Seeder
{
    public function run(): void
    {
        $jobs = [
            'influencer' => ['name_en' => 'Influencer', 'name_ar' => 'مؤثر', 'sort_order' => 0],
            'affiliate' => ['name_en' => 'Affiliate', 'name_ar' => 'مسوق بالعمولة', 'sort_order' => 1],
            'broker' => ['name_en' => 'Broker', 'name_ar' => 'وسيط', 'sort_order' => 2],
        ];

        foreach ($jobs as $key => $attrs) {
            MarketerJob::firstOrCreate(
                ['key' => $key],
                [
                    'name_en' => $attrs['name_en'],
                    'name_ar' => $attrs['name_ar'],
                    'is_active' => true,
                    'sort_order' => $attrs['sort_order'],
                ]
            );
        }

        foreach (['affiliate', 'broker'] as $key) {
            $job = MarketerJob::where('key', $key)->first();
            if ($job) {
                MarketerJobCategory::firstOrCreate([
                    'marketer_job_id' => $job->id,
                    'category_type' => 'product',
                ]);
            }
        }
    }
}
