<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class BlogController extends Controller
{
    private function resolveCountry(): ?Country
    {
        return Country::where('site_domain', request()->getHost())->first();
    }

    public function index(Request $request): View
    {
        $country = $this->resolveCountry();
        $locale  = app()->getLocale();

        $featuredPost = BlogPost::published()
            ->forCountry($country)
            ->where('is_featured', true)
            ->with(['category', 'author'])
            ->first();

        $categories = BlogCategory::active()
            ->topLevel()
            ->withCount(['posts' => fn($q) => $q->published()->forCountry($country)])
            ->having('posts_count', '>', 0)
            ->with('children')
            ->get();

        $posts = BlogPost::published()
            ->forCountry($country)
            ->when($request->category, fn($q, $cat) =>
                $q->whereHas('category', fn($q) => $q->where('slug', $cat)))
            ->when($request->tag, fn($q, $tag) =>
                $q->whereJsonContains('tags', $tag))
            ->when($request->search, fn($q, $s) =>
                $q->where("title_{$locale}", 'like', "%{$s}%")
                  ->orWhere("excerpt_{$locale}", 'like', "%{$s}%"))
            ->with(['category', 'author'])
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('portal.blog.index', compact(
            'featuredPost', 'categories', 'posts', 'country', 'locale'
        ));
    }

    public function show(string $slug): View
    {
        $country = $this->resolveCountry();
        $locale  = app()->getLocale();

        $post = BlogPost::where('slug', $slug)
            ->published()
            ->forCountry($country)
            ->with(['category.parent', 'author'])
            ->firstOrFail();

        $relatedPosts = BlogPost::published()
            ->forCountry($country)
            ->where('blog_category_id', $post->blog_category_id)
            ->where('id', '!=', $post->id)
            ->with(['category'])
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('portal.blog.show', compact('post', 'relatedPosts', 'country', 'locale'));
    }

    public function incrementViews(BlogPost $post): JsonResponse
    {
        DB::table('blog_posts')->where('id', $post->id)->increment('views_count');

        return response()->json(['ok' => true]);
    }
}
