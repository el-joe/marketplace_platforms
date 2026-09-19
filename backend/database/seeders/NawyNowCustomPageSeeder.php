<?php

namespace Database\Seeders;

use App\Models\CustomPage;
use App\Models\Slug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent "Nawy Now" custom page: admin listings only, all categories.
 * Re-running never duplicates rows and never overwrites admin edits: existing
 * pages are left untouched (only a missing slug record is restored).
 */
class NawyNowCustomPageSeeder extends Seeder
{
    public const SLUG = 'nawy-now';

    public function run(): void
    {
        DB::transaction(function () {
            $slug = Slug::where('slug_url', self::SLUG)->first();

            if ($slug && $slug->sluggable_type !== CustomPage::class) {
                throw new \RuntimeException(
                    "Cannot seed Nawy Now: slug '" . self::SLUG . "' is already owned by {$slug->sluggable_type} ({$slug->sluggable_id})."
                );
            }

            $page = $slug ? CustomPage::withTrashed()->find($slug->sluggable_id) : null;

            if (!$page) {
                $page = CustomPage::create([
                    'name_en' => 'Nawy Now',
                    'name_ar' => 'نوي الآن',
                    'description_en' => 'Fast picks sold and fulfilled directly by Nawy.',
                    'description_ar' => 'منتجات مختارة تباع وتُشحن مباشرة من نوي.',
                    'has_filters' => true,
                    'listing_types' => ['admin'],
                    'all_categories' => true,
                    'is_active' => true,
                    'sort_order' => 0,
                    'seo_title_en' => 'Nawy Now | Shop Nawy direct offers',
                    'seo_title_ar' => 'نوي الآن | عروض نوي المباشرة',
                    'seo_description_en' => 'Shop products sold directly by Nawy across every category.',
                    'seo_description_ar' => 'تسوق منتجات تباع مباشرة من نوي في جميع الفئات.',
                ]);
            }

            Slug::upsertFor($page, self::SLUG);
        });
    }
}
