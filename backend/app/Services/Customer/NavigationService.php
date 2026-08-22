<?php

namespace App\Services\Customer;

use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\TravelCategory;
use Illuminate\Support\Facades\Cache;

class NavigationService
{
    /**
     * Unified nav tree merging all 3 category verticals.
     * Cached 10 min per country. The 'database' cache store doesn't support
     * tagging, so this uses a plain key; call self::forgetTree() (or let the
     * 10 min TTL expire) after category create/update/delete.
     *
     * Order: products → classifieds → travel (products are the primary business).
     * // VERIFY: confirm this ordering with the nav UI design before shipping.
     *
     * Icon note: the categories (products) table has no icon column — product
     * nodes will have icon: null. // VERIFY: add a default icon per product
     * category or accept null and let the frontend use a fallback glyph.
     *
     * Country note: category visibility is global; only product availability is
     * country-scoped (via product_country_settings). Country param is kept in the
     * cache key for forward-compatibility and route consistency.
     * // VERIFY: confirm categories are indeed global, not country-filtered.
     */
    public function getTree(Country $country): array
    {
        return Cache::remember("nav_tree:{$country->id}", 600, function () {
            return array_merge(
                $this->productNodes(),
                $this->classifiedNodes(),
                $this->travelNodes(),
            );
        });
    }

    public function forgetTree(Country $country): void
    {
        Cache::forget("nav_tree:{$country->id}");
    }

    private function productNodes(): array
    {
        $roots = Category::where('is_active', true)
            ->where('is_visible', true)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->where('is_visible', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return $roots->map(fn ($cat) => $this->mapCategory($cat, 'product'))->values()->all();
    }

    private function classifiedNodes(): array
    {
        $roots = ClassifiedCategory::where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return $roots->map(fn ($cat) => $this->mapCategory($cat, 'classified'))->values()->all();
    }

    private function travelNodes(): array
    {
        $roots = TravelCategory::where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return $roots->map(fn ($cat) => $this->mapCategory($cat, 'travel'))->values()->all();
    }

    private function mapCategory(mixed $cat, string $type): array
    {
        $lang = app()->getLocale() === 'ar' ? 'ar' : 'en';

        return [
            'type'     => $type,
            'id'       => $cat->id,
            'name'     => $cat->{'name_' . $lang},
            'slug'     => $cat->slug,
            'icon'     => $cat->icon ?? null,
            'children' => $cat->children
                ->map(fn ($child) => $this->mapCategory($child, $type))
                ->values()
                ->all(),
        ];
    }
}
