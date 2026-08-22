<?php

namespace App\Http\Controllers\Portal;

use App\Enums\HelpCenterArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\HelpCenterArticle;
use App\Models\HelpCenterCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HelpCenterController extends Controller
{
    public function index(?string $country = null): View
    {
        $country = Country::resolveSiteCode($country);

        $categories = HelpCenterCategory::active()
            ->topLevel()
            ->forCountry($country)
            ->with(['children' => fn ($q) => $q->active()])
            ->get();

        $featuredArticle = HelpCenterArticle::published()
            ->forCountry($country)
            ->where('is_featured', true)
            ->first();

        return view('portal.helpcenter.index', compact('country', 'categories', 'featuredArticle'));
    }

    public function search(Request $request, ?string $country = null): View
    {
        $country = Country::resolveSiteCode($country);

        $q = trim((string) $request->get('q', ''));

        $articles = HelpCenterArticle::published()
            ->forCountry($country)
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                    ->orWhere('title_en', 'like', "%{$q}%")
                    ->orWhere('title_ar', 'like', "%{$q}%")
                    ->orWhere('excerpt', 'like', "%{$q}%")
                    ->orWhere('excerpt_en', 'like', "%{$q}%")
                    ->orWhere('excerpt_ar', 'like', "%{$q}%")
                    ->orWhere('body', 'like', "%{$q}%")
                    ->orWhere('body_en', 'like', "%{$q}%")
                    ->orWhere('body_ar', 'like', "%{$q}%");
            }))
            ->orderByDesc('views_count')
            ->limit(30)
            ->get();

        return view('portal.helpcenter.search', compact('country', 'q', 'articles'));
    }

    public function category(?string $country, HelpCenterCategory $category): View
    {
        $country = Country::resolveSiteCode($country);

        abort_unless($category->is_active, 404);

        $category->load([
            'children' => fn ($q) => $q->active(),
            'children.articles' => fn ($q) => $q->published()->orderBy('created_at'),
            'articles' => fn ($q) => $q->published()->orderBy('created_at'),
            'parent',
        ]);

        return view('portal.helpcenter.category', [
            'country' => $country,
            'category' => $category,
        ]);
    }

    public function article(?string $country, HelpCenterArticle $article): View
    {
        $country = Country::resolveSiteCode($country);

        abort_unless($article->status === HelpCenterArticleStatus::Published, 404);

        $article->load('category.parent');

        DB::table('help_center_articles')->where('id', $article->id)->increment('views_count');

        return view('portal.helpcenter.article', [
            'country' => $country,
            'article' => $article,
            'relatedArticles' => $article->resolvedRelatedArticles(),
        ]);
    }
}
