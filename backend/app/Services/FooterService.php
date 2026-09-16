<?php

namespace App\Services;

use App\Models\Category;
use App\Models\FooterLink;
use App\Support\Bilingual;
use App\Support\SafeCache;
use Illuminate\Support\Facades\Cache;

class FooterService
{
    public const CACHE_VERSION_KEY = 'footer_cache_version';

    public function getFooterData(): array
    {
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);

        return SafeCache::remember("footer_v{$version}", 3600, function () {
            return [
                'categories' => $this->footerCategories(),
                'social_links' => $this->linksFor('social'),
                'bottom_nav_links' => $this->linksFor('bottom_nav'),
                'app_store_links' => $this->linksFor('app_store'),
                'payment_methods' => $this->linksFor('payment_method'),
            ];
        });
    }

    public static function flushCache(): void
    {
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);
        Cache::put(self::CACHE_VERSION_KEY, $version + 1);
    }

    private function footerCategories(): array
    {
        $parents = Category::whereNull('parent_id')
            ->where('is_active', true)
            ->where('show_in_footer', true)
            ->orderBy('sort_order')
            ->orderBy('lft')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->get();

        return $parents->map(fn (Category $category) => [
            'id' => $category->id,
            'name' => Bilingual::pair($category, 'name'),
            'slug' => $category->slug,
            'link' => "/browse/product/{$category->id}",
            'children' => $category->children->map(fn (Category $child) => [
                'id' => $child->id,
                'name' => Bilingual::pair($child, 'name'),
                'slug' => $child->slug,
                'link' => "/browse/product/{$child->id}",
            ])->values()->all(),
        ])->values()->all();
    }

    private function linksFor(string $group): array
    {
        return FooterLink::query()
            ->where('group', $group)
            ->active()
            ->ordered()
            ->get()
            ->map(fn (FooterLink $link) => [
                'id' => $link->id,
                'platform' => $link->platform,
                'label' => Bilingual::pairFromKeys($link, 'label_ar', 'label_en'),
                'url' => $link->url,
                'icon' => $link->icon_path,
            ])
            ->values()
            ->all();
    }
}
