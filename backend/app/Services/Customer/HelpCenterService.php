<?php

namespace App\Services\Customer;

use App\Models\Country;
use App\Models\HelpCenterArticle;
use App\Models\HelpCenterCategory;
use App\Support\Bilingual;
use Illuminate\Support\Collection;

class HelpCenterService
{
    /**
     * Nested help-center tree for the customer help sheet: top-level
     * categories, their one level of sub-categories (help_center_categories
     * only supports one level — see the column comment on parent_id), and
     * each leaf category's published articles.
     *
     * `help_center_categories`/`help_center_articles` have no context/scope
     * column, so there is nothing to filter a `context` query param
     * against — every active category/article for the country is returned
     * and the frontend decides which branch to open.
     */
    public function getTree(Country $country): array
    {
        $categories = HelpCenterCategory::query()
            ->active()
            ->forCountry($country->site_code)
            ->with([
                'children' => fn ($q) => $q->active()->forCountry($country->site_code),
                'articles' => fn ($q) => $q->published()->forCountry($country->site_code)->orderBy('is_featured', 'desc')->orderBy('published_at'),
                'children.articles' => fn ($q) => $q->published()->forCountry($country->site_code)->orderBy('is_featured', 'desc')->orderBy('published_at'),
            ])
            ->topLevel()
            ->orderBy('sort_order')
            ->get();

        return $categories->map(fn (HelpCenterCategory $category) => $this->mapCategory($category))->all();
    }

    protected function mapCategory(HelpCenterCategory $category): array
    {
        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'title' => Bilingual::pair($category, 'name'),
            'description' => Bilingual::pair($category, 'description'),
            'icon' => $category->icon,
            'children' => $category->children
                ->sortBy('sort_order')
                ->map(fn (HelpCenterCategory $child) => $this->mapCategory($child))
                ->values()
                ->all(),
            'articles' => $this->mapArticles($category->articles),
        ];
    }

    protected function mapArticles(Collection $articles): array
    {
        return $articles->map(fn (HelpCenterArticle $article) => [
            'id' => $article->id,
            'slug' => $article->slug,
            'title' => Bilingual::pair($article, 'title'),
            'excerpt' => Bilingual::pair($article, 'excerpt'),
            'body' => Bilingual::pair($article, 'body'),
        ])->values()->all();
    }
}
