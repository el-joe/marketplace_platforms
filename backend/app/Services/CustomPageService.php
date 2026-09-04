<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CustomPage;
use App\Models\Slug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomPageService
{
    /**
     * Build a unique slug (cross-checked against categories and other custom
     * pages via the shared `slugs` table) from a requested value or the
     * English name.
     */
    public function uniqueSlug(?string $requested, string $nameEn, ?CustomPage $except = null): string
    {
        $base = $requested ? Str::slug($requested) : Str::slug($nameEn);
        $base = $base ?: 'custom-page';

        $slug = $base;
        $i = 1;
        while (Slug::isTaken($slug, CustomPage::class, $except?->id)) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function syncCategories(CustomPage $customPage, array $categoryIds): void
    {
        DB::transaction(function () use ($customPage, $categoryIds) {
            DB::table('custom_page_category_map')->where('custom_page_id', $customPage->id)->delete();

            foreach (array_values($categoryIds) as $i => $categoryId) {
                DB::table('custom_page_category_map')->insert([
                    'id' => (string) Str::uuid(),
                    'custom_page_id' => $customPage->id,
                    'category_id' => $categoryId,
                    'sort_order' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        \App\Services\Customer\CategoryService::flushCache();
    }

    public function reorder(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                DB::table('custom_pages')
                    ->where('id', $item['id'])
                    ->update(['sort_order' => (int) ($item['sort_order'] ?? 0), 'updated_at' => now()]);
            }
        });
    }

    /**
     * Filterable attributes shared by every category currently linked to
     * this custom page — shown read-only in the admin form so the operator
     * can see what filter facets the storefront will surface.
     */
    public function filterableAttributes(CustomPage $customPage): \Illuminate\Support\Collection
    {
        $categoryIds = $customPage->categories()->pluck('categories.id');

        if ($categoryIds->isEmpty()) {
            return collect();
        }

        return \App\Models\Attribute::query()
            ->where('is_filterable', true)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->orderBy('sort_order')
            ->get(['id', 'name_en', 'name_ar', 'code']);
    }
}
