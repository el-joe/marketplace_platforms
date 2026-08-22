<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\HelpCenterArticle;
use App\Models\HelpCenterCategory;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HelpCenterArticleController extends Controller
{
    use HasDataTable;

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $stats = [
            'published' => HelpCenterArticle::where('status', 'published')->count(),
            'draft' => HelpCenterArticle::where('status', 'draft')->count(),
            'total_views' => HelpCenterArticle::sum('views_count'),
        ];

        $categories = HelpCenterCategory::whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return view('admin.helpcenter.articles.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => __('admin.helpcenter.articles')],
            ],
            'stats' => $stats,
            'categories' => $categories,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $query = HelpCenterArticle::query()
            ->select('help_center_articles.*')
            ->with(['category'])
            ->withoutTrashed();

        $query = $this->applyFilters($query, $request, [
            'status' => fn ($q, $v) => $q->where('help_center_articles.status', $v),
            'help_center_category_id' => fn ($q, $v) => $q->where('help_center_articles.help_center_category_id', $v),
        ]);

        $columns = [
            ['searchable_columns' => ['help_center_articles.title'], 'orderable_column' => 'help_center_articles.title'],
            ['searchable_columns' => [], 'orderable_column' => null], // category
            ['searchable_columns' => [], 'orderable_column' => 'help_center_articles.status'],
            ['searchable_columns' => [], 'orderable_column' => 'help_center_articles.published_at'],
            ['searchable_columns' => [], 'orderable_column' => 'help_center_articles.views_count'],
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        if (!$query->getQuery()->orders) {
            $query->orderByDesc('help_center_articles.updated_at');
        }

        $statusColors = [
            'draft' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'label' => 'Draft'],
            'published' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'label' => 'Published'],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (HelpCenterArticle $row) use ($statusColors) {
            $featuredStar = $row->is_featured
                ? '<span class="inline-block text-amber-400" title="Featured on home page">★</span> '
                : '';

            $titleHtml = '<div class="min-w-0">'
                . '<div class="font-medium text-gray-900 truncate max-w-xs">' . $featuredStar . e($row->title) . '</div>'
                . '<div class="text-xs text-gray-400 truncate max-w-xs font-mono">' . e($row->slug) . '</div>'
                . '</div>';

            $categoryHtml = $row->category
                ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-primary-50 text-primary-700">' . e($row->category->name) . '</span>'
                : '<span class="text-gray-400 text-xs">—</span>';

            $sc = $statusColors[$row->status->value] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'label' => $row->status->label()];
            $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' . $sc['bg'] . ' ' . $sc['text'] . '">' . $sc['label'] . '</span>';

            $dateHtml = $row->published_at
                ? '<div class="text-xs text-gray-700">' . $row->published_at->format('d M Y') . '</div>'
                : '<span class="text-gray-300 text-xs">—</span>';

            $editUrl = route('admin.helpcenter.articles.edit', $row->id);
            $featureUrl = route('admin.helpcenter.articles.feature', $row->id);
            $deleteUrl = route('admin.helpcenter.articles.destroy', $row->id);

            $featureLabel = $row->is_featured ? 'Unfeature' : 'Feature on home page';

            $actionsHtml = '<div class="relative" x-data="{open:false}">'
                . '<button @click="open=!open" class="btn btn-ghost btn-sm">⋮</button>'
                . '<div x-show="open" @click.away="open=false" x-cloak class="absolute right-0 z-10 w-48 bg-white border border-gray-200 rounded-lg shadow-lg py-1 text-sm">'
                . '<a href="' . $editUrl . '" class="block px-4 py-2 text-gray-700 hover:bg-gray-50">Edit</a>'
                . '<button type="button" class="btn-feature w-full text-left px-4 py-2 text-gray-600 hover:bg-gray-50" data-id="' . $row->id . '" data-url="' . $featureUrl . '">' . $featureLabel . '</button>'
                . '<button type="button" class="btn-delete w-full text-left px-4 py-2 text-red-500 hover:bg-gray-50" data-id="' . $row->id . '" data-url="' . $deleteUrl . '">Delete</button>'
                . '</div></div>';

            return [
                'title' => $titleHtml,
                'category' => $categoryHtml,
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
        return view('admin.helpcenter.articles.create', $this->formData());
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

        $article = HelpCenterArticle::create($data);

        $this->syncFeatured($article);

        return redirect()->route('admin.helpcenter.articles.edit', $article->id)
            ->with('success', 'Article saved.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(HelpCenterArticle $article): View
    {
        return view('admin.helpcenter.articles.edit', array_merge($this->formData(), [
            'article' => $article,
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => __('admin.helpcenter.articles'), 'url' => route('admin.helpcenter.articles.index')],
                ['label' => e($article->title)],
            ],
        ]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, HelpCenterArticle $article): RedirectResponse
    {
        $validated = $this->validateArticle($request, $article->id);

        $slug = $request->filled('slug') ? $this->uniqueSlug($request->slug, $article->id) : $article->slug;

        $data = $this->buildArticleData($request, $validated, $slug);
        $data = $this->applyStatusLogic($request, $data, $article);

        $article->update($data);

        $this->syncFeatured($article->fresh());

        return redirect()->route('admin.helpcenter.articles.edit', $article->id)
            ->with('success', 'Article updated.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(HelpCenterArticle $article): JsonResponse
    {
        $article->delete();

        return response()->json(['success' => true, 'message' => 'Article deleted.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Feature (home page featured callout)
    // ─────────────────────────────────────────────────────────────────────────

    public function feature(HelpCenterArticle $article): JsonResponse
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
        $categories = HelpCenterCategory::whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $allArticles = HelpCenterArticle::orderBy('title')->get(['id', 'title']);
        $countries = Country::orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'flag_emoji']);

        return [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => __('admin.helpcenter.articles'), 'url' => route('admin.helpcenter.articles.index')],
                ['label' => __('admin.helpcenter.new_article')],
            ],
            'categories' => $categories,
            'allArticles' => $allArticles,
            'countries' => $countries,
        ];
    }

    private function validateArticle(Request $request, ?string $excludeArticleId = null): array
    {
        return $request->validate([
            'help_center_category_id' => 'required|string|exists:help_center_categories,id',
            'country_id' => 'nullable|string|exists:countries,site_code',
            'title' => 'nullable|string|max:255',
            'title_en' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:help_center_articles,slug,' . ($excludeArticleId ?? 'NULL'),
            'excerpt' => 'nullable|string|max:500',
            'excerpt_en' => 'nullable|string|max:500',
            'excerpt_ar' => 'nullable|string|max:500',
            'body' => 'required|string',
            'body_en' => 'required|string',
            'body_ar' => 'required|string',
            'related_article_ids' => 'nullable|array',
            'related_article_ids.*' => 'string|exists:help_center_articles,id',
            'is_featured' => 'nullable|boolean',
        ]);
    }

    private function buildArticleData(Request $request, array $validated, string $slug): array
    {
        return [
            'help_center_category_id' => $validated['help_center_category_id'],
            'author_admin_id' => auth('admin')->id(),
            'country_id' => $validated['country_id'] ?? null,
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

    private function applyStatusLogic(Request $request, array $data, ?HelpCenterArticle $existing = null): array
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
     * Only one article should be featured on the Help Center home page at a time.
     */
    private function syncFeatured(HelpCenterArticle $article): void
    {
        if ($article->is_featured) {
            HelpCenterArticle::where('is_featured', true)
                ->where('id', '!=', $article->id)
                ->update(['is_featured' => false]);
        }
    }

    private function uniqueSlug(string $base, ?string $excludeId = null): string
    {
        $slug = $base;
        $i = 1;
        while (
            HelpCenterArticle::withTrashed()
                ->where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
