<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AdSupportArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\AdSupportArticle;
use App\Models\AdSupportCollection;
use App\Models\Country;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdSupportController extends Controller
{
    public function index(?string $country = null): View
    {
        $country = Country::resolveSiteCode($country);

        $collections = AdSupportCollection::active()
            ->topLevel()
            ->with('children')
            ->get();

        $featuredArticle = AdSupportArticle::published()
            ->where('is_featured', true)
            ->first();

        return view('portal.adsupport.index', compact('country', 'collections', 'featuredArticle'));
    }

    public function collection(?string $country, AdSupportCollection $collection): View
    {
        $country = Country::resolveSiteCode($country);

        abort_unless($collection->is_active, 404);

        $collection->load([
            'children' => fn ($q) => $q->active(),
            'children.articles' => fn ($q) => $q->published()->orderBy('created_at'),
            'articles' => fn ($q) => $q->published()->orderBy('created_at'),
        ]);

        return view('portal.adsupport.collection', [
            'country' => $country,
            'collection' => $collection,
        ]);
    }

    public function article(?string $country, AdSupportArticle $article): View
    {
        $country = Country::resolveSiteCode($country);

        abort_unless($article->status === AdSupportArticleStatus::Published, 404);

        $article->load('collection.parent');

        DB::table('ad_support_articles')->where('id', $article->id)->increment('views_count');

        return view('portal.adsupport.article', [
            'country' => $country,
            'article' => $article,
            'relatedArticles' => $article->resolvedRelatedArticles(),
        ]);
    }
}
