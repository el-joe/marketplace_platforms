<?php

namespace App\Services;

use App\Models\Category;
use App\Models\FooterLink;
use App\Models\StaticPage;
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
                'bottom_nav_links' => $this->bottomNavLinks(),
                'app_store_links' => $this->linksFor('app_store'),
                'payment_methods' => $this->linksFor('payment_method'),
                'static_pages' => $this->staticPageLinks(),
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

    /** Hosts of the placeholder links FooterLinkSeeder used to create (never real pages). */
    private const PLACEHOLDER_HOSTS = ['example.com', 'example.org', 'example.net'];

    /**
     * Admin-managed static pages (Admin > Static Pages) for the storefront footer,
     * already ordered by sort_order. `link` is the storefront route (/pages/{slug}).
     *
     * @return array<int, array{id: string, slug: string, title: array{ar: ?string, en: ?string}, footer_group: ?string, sort_order: int, link: string}>
     */
    private function staticPageLinks(): array
    {
        return array_map(
            fn (array $page) => $page + ['link' => '/pages/'.$page['slug']],
            StaticPage::footerLinksFor(StaticPage::AUDIENCE_STOREFRONT),
        );
    }

    /**
     * Bottom-bar links, minus leftover placeholder rows (https://example.com/...):
     * Privacy / Terms now come from `static_pages`, and a placeholder URL is a dead
     * link either way.
     */
    private function bottomNavLinks(): array
    {
        return array_values(array_filter(
            $this->linksFor('bottom_nav'),
            fn (array $link) => ! $this->isPlaceholderUrl($link['url']),
        ));
    }

    private function isPlaceholderUrl(?string $url): bool
    {
        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));

        foreach (self::PLACEHOLDER_HOSTS as $placeholder) {
            if ($host === $placeholder || str_ends_with($host, '.'.$placeholder)) {
                return true;
            }
        }

        return false;
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
