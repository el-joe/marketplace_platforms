<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdSupportArticle;
use App\Models\AdSupportCollection;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdSupportArticleController extends Controller
{
    use HasDataTable;

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $stats = [
            'published' => AdSupportArticle::where('status', 'published')->count(),
            'draft' => AdSupportArticle::where('status', 'draft')->count(),
            'total_views' => AdSupportArticle::sum('views_count'),
        ];

        $collections = AdSupportCollection::whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return view('admin.adsupport.articles.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.adsupport.articles')],
            ],
            'stats' => $stats,
            'collections' => $collections,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $query = AdSupportArticle::query()
            ->select('ad_support_articles.*')
            ->with(['collection'])
            ->withoutTrashed();

        $query = $this->applyFilters($query, $request, [
            'status' => fn ($q, $v) => $q->where('ad_support_articles.status', $v),
            'ad_support_collection_id' => fn ($q, $v) => $q->where('ad_support_articles.ad_support_collection_id', $v),
        ]);

        $columns = [
            ['searchable_columns' => ['ad_support_articles.title'], 'orderable_column' => 'ad_support_articles.title'],
            ['searchable_columns' => [], 'orderable_column' => null], // collection
            ['searchable_columns' => [], 'orderable_column' => 'ad_support_articles.status'],
            ['searchable_columns' => [], 'orderable_column' => 'ad_support_articles.published_at'],
            ['searchable_columns' => [], 'orderable_column' => 'ad_support_articles.views_count'],
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        if (!$query->getQuery()->orders) {
            $query->orderByDesc('ad_support_articles.updated_at');
        }

        $statusColors = [
            'draft' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'label' => __('admin.adsupport.status_draft')],
            'published' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'label' => __('admin.adsupport.status_published')],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (AdSupportArticle $row) use ($statusColors) {
            $featuredStar = $row->is_featured
                ? '<span class="inline-block text-amber-400" title="' . __('admin.adsupport.featured_tooltip') . '">★</span> '
                : '';

            $titleHtml = '<div class="min-w-0">'
                . '<div class="font-medium text-gray-900 truncate max-w-xs">' . $featuredStar . e($row->title) . '</div>'
                . '<div class="text-xs text-gray-400 truncate max-w-xs font-mono">' . e($row->slug) . '</div>'
                . '</div>';

            $collectionHtml = $row->collection
                ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-primary-50 text-primary-700">' . e($row->collection->name) . '</span>'
                : '<span class="text-gray-400 text-xs">—</span>';

            $sc = $statusColors[$row->status->value] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'label' => $row->status->label()];
            $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' . $sc['bg'] . ' ' . $sc['text'] . '">' . $sc['label'] . '</span>';

            $dateHtml = $row->published_at
                ? '<div class="text-xs text-gray-700">' . $row->published_at->format('d M Y') . '</div>'
                : '<span class="text-gray-300 text-xs">—</span>';

            $editUrl = route('admin.adsupport.articles.edit', $row->id);
            $featureUrl = route('admin.adsupport.articles.feature', $row->id);
            $deleteUrl = route('admin.adsupport.articles.destroy', $row->id);

            $featureLabel = $row->is_featured ? __('admin.adsupport.unfeature') : __('admin.adsupport.feature_on_home');

            $actionsHtml = '<div class="relative" x-data="{open:false}">'
                . '<button @click="open=!open" class="btn btn-ghost btn-sm">⋮</button>'
                . '<div x-show="open" @click.away="open=false" x-cloak class="absolute right-0 z-10 w-48 bg-white border border-gray-200 rounded-lg shadow-lg py-1 text-sm">'
                . '<a href="' . $editUrl . '" class="block px-4 py-2 text-gray-700 hover:bg-gray-50">' . __('admin.edit') . '</a>'
                . '<button type="button" class="btn-feature w-full text-left px-4 py-2 text-gray-600 hover:bg-gray-50" data-id="' . $row->id . '" data-url="' . $featureUrl . '">' . $featureLabel . '</button>'
                . '<button type="button" class="btn-delete w-full text-left px-4 py-2 text-red-500 hover:bg-gray-50" data-id="' . $row->id . '" data-url="' . $deleteUrl . '">' . __('admin.delete') . '</button>'
                . '</div></div>';

            return [
                'title' => $titleHtml,
                'collection' => $collectionHtml,
                'status' => $statusBadge,
                'date' => $dateHtml,
                'views' => number_format($row->views_count),
                'actions' => $actionsHtml,
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.adsupport.articles.create', $this->formData());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Store
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateArticle($request);

        $slug = $this->uniqueSlug($request->slug ?: Str::slug($request->title_en ?: $request->title));

        $data = $this->buildArticleData($request, $validated, $slug);
        $data = $this->applyStatusLogic($request, $data);

        $article = AdSupportArticle::create($data);

        $this->syncFeatured($article);

        return redirect()->route('admin.adsupport.articles.edit', $article->id)
            ->with('success', __('admin.adsupport.article_saved'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(AdSupportArticle $article): View
    {
        return view('admin.adsupport.articles.edit', array_merge($this->formData(), [
            'article' => $article,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.adsupport.articles'), 'url' => route('admin.adsupport.articles.index')],
                ['label' => e($article->title)],
            ],
        ]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, AdSupportArticle $article): RedirectResponse
    {
        $validated = $this->validateArticle($request, $article->id);

        $slug = $request->filled('slug') ? $this->uniqueSlug($request->slug, $article->id) : $article->slug;

        $data = $this->buildArticleData($request, $validated, $slug);
        $data = $this->applyStatusLogic($request, $data, $article);

        $article->update($data);

        $this->syncFeatured($article->fresh());

        return redirect()->route('admin.adsupport.articles.edit', $article->id)
            ->with('success', __('admin.adsupport.article_updated'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(AdSupportArticle $article): JsonResponse
    {
        $article->delete();

        return response()->json(['success' => true, 'message' => __('admin.adsupport.article_deleted')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Feature (home page "New to ads?" callout)
    // ─────────────────────────────────────────────────────────────────────────

    public function feature(AdSupportArticle $article): JsonResponse
    {
        $newFeatured = !$article->is_featured;
        $article->update(['is_featured' => $newFeatured]);
        $this->syncFeatured($article);

        return response()->json(['success' => true, 'is_featured' => $newFeatured]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function formData(): array
    {
        $collections = AdSupportCollection::whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $allArticles = AdSupportArticle::orderBy('title')->get(['id', 'title']);

        return [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.adsupport.articles'), 'url' => route('admin.adsupport.articles.index')],
                ['label' => __('admin.adsupport.new_article')],
            ],
            'collections' => $collections,
            'allArticles' => $allArticles,
        ];
    }

    private function validateArticle(Request $request, ?string $excludeArticleId = null): array
    {
        return $request->validate([
            'ad_support_collection_id' => 'required|string|exists:ad_support_collections,id',
            'title' => 'nullable|string|max:255',
            'title_en' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:ad_support_articles,slug,' . ($excludeArticleId ?? 'NULL'),
            'excerpt' => 'nullable|string|max:500',
            'excerpt_en' => 'nullable|string|max:500',
            'excerpt_ar' => 'nullable|string|max:500',
            'body' => 'required|string',
            'body_en' => 'required|string',
            'body_ar' => 'required|string',
            'related_article_ids' => 'nullable|array',
            'related_article_ids.*' => 'string|exists:ad_support_articles,id',
            'is_featured' => 'nullable|boolean',
        ]);
    }

    private function buildArticleData(Request $request, array $validated, string $slug): array
    {
        return [
            'ad_support_collection_id' => $validated['ad_support_collection_id'],
            'author_admin_id' => auth('admin')->id(),
            'title' => $validated['title_en'] ?? $validated['title'],
            'title_en' => $validated['title_en'] ?? null,
            'title_ar' => $validated['title_ar'] ?? null,
            'slug' => $slug,
            'excerpt' => $validated['excerpt_en'] ?? $validated['excerpt'] ?? null,
            'excerpt_en' => $validated['excerpt_en'] ?? null,
            'excerpt_ar' => $validated['excerpt_ar'] ?? null,
            'body' => $validated['body_en'] ?? $validated['body'],
            'body_en' => $validated['body_en'] ?? null,
            'body_ar' => $validated['body_ar'] ?? null,
            'related_article_ids' => $validated['related_article_ids'] ?? [],
            'is_featured' => $request->boolean('is_featured'),
        ];
    }

    private function applyStatusLogic(Request $request, array $data, ?AdSupportArticle $existing = null): array
    {
        $action = $request->input('action', 'draft');

        if ($action === 'publish') {
            $data['status'] = 'published';
            $data['published_at'] = $existing?->published_at ?? now();
        } else {
            $data['status'] = 'draft';
        }

        return $data;
    }

    /**
     * Only one article should be featured on the Knowledge Hub home page at a time.
     */
    private function syncFeatured(AdSupportArticle $article): void
    {
        if ($article->is_featured) {
            AdSupportArticle::where('is_featured', true)
                ->where('id', '!=', $article->id)
                ->update(['is_featured' => false]);
        }
    }

    private function uniqueSlug(string $base, ?string $excludeId = null): string
    {
        $slug = $base;
        $i = 1;
        while (
            AdSupportArticle::withTrashed()
                ->where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
