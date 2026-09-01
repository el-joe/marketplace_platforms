<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\WarrantyPlan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WarrantyPlanService
{
    public const CACHE_VERSION_KEY = 'warranty_plans_cache_version';

    public function getPlansForProduct(Product $product, string $countryId, string $currency, int $listingPrice = 0): array
    {
        $categoryId = $product->category_id;

        if (!$categoryId) {
            return [];
        }

        $version = Cache::get(self::CACHE_VERSION_KEY, 1);
        $cacheKey = "warranty_plans_v{$version}_{$categoryId}_{$countryId}_{$listingPrice}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($categoryId, $countryId, $listingPrice) {
            $category = Category::find($categoryId);

            if (!$category) {
                return [];
            }

            if ($category->lft === null || $category->rgt === null) {
                $ancestorIds = [];
            } else {
                $ancestorIds = DB::table('categories')
                    ->where('lft', '<=', $category->lft)
                    ->where('rgt', '>=', $category->rgt)
                    ->where('is_active', 1)
                    ->orderBy('lft', 'asc')
                    ->pluck('id')
                    ->all();
            }

            if (!in_array($categoryId, $ancestorIds, true)) {
                $ancestorIds[] = $categoryId;
            }

            $plans = WarrantyPlan::query()
                ->whereIn('category_id', $ancestorIds)
                ->where('is_active', true)
                ->where(function ($q) use ($countryId) {
                    $q->whereNull('country_ids')
                        ->orWhereJsonContains('country_ids', $countryId);
                })
                ->orderBy('sort_order', 'asc')
                ->get();


            return $plans->map(fn (WarrantyPlan $plan) => $this->formatPlan($plan, $listingPrice))->values()->all();
        });
    }

    public static function flushCache(): void
    {
        $cacheInc = Cache::get(self::CACHE_VERSION_KEY, 1);
        Cache::put(self::CACHE_VERSION_KEY, $cacheInc + 1);
    }

    private function formatPlan(WarrantyPlan $plan, int $listingPrice = 0): array
    {
        return [
            'id' => $plan->id,
            'name' => app()->getLocale() === 'ar' ? $plan->name_ar : $plan->name_en,
            'duration_months' => $plan->duration_months,
            'duration_label' => $this->formatDurationLabel($plan->duration_months),
            'features' => app()->getLocale() === 'ar' ? $plan->features_ar : $plan->features_en,
            'price' => $plan->resolvePrice($listingPrice),
            'price_type' => $plan->price_type,
            'price_pct' => $plan->price_pct,
            'currency' => $plan->currency,
            'image_url' => $plan->image_url,
        ];
    }

    private function formatDurationLabel(int $months): string
    {
        return match (true) {
            $months === 1 => '1 month',
            $months === 6 => '6 months',
            $months === 12 => '1 year',
            $months === 24 => '2 years',
            $months <= 11 => "{$months} months",
            $months % 12 === 0 => ($months / 12).' years',
            default => "{$months} months",
        };
    }
}
