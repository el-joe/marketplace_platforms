<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BlogPostStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Country;
use App\Models\File;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    use HasDataTable;
    use HasExport;

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportPosts($request);
        }

        $stats = [
            'published' => BlogPost::where('status', 'published')->count(),
            'draft' => BlogPost::where('status', 'draft')->count(),
            'scheduled' => BlogPost::where('status', 'scheduled')->count(),
            'archived' => BlogPost::where('status', 'archived')->count(),
            'views_month' => BlogPost::whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->sum('views_count'),
        ];

        $categories = BlogCategory::whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        $countries = Country::where('is_launched', true)->orderBy('name_en')->get();

        $authors = Admin::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('admin.blog.posts.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.blog_posts')],
            ],
            'stats' => $stats,
            'categories' => $categories,
            'countries' => $countries,
            'authors' => $authors,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DataTable
    // ─────────────────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $query = $this->buildPostsQuery($request);

        $columns = [
            ['searchable_columns' => [], 'orderable_column' => null], // image
            ['searchable_columns' => ['blog_posts.title_en', 'blog_posts.title_ar'], 'orderable_column' => 'blog_posts.title_en'],
            ['searchable_columns' => [], 'orderable_column' => null], // author
            ['searchable_columns' => [], 'orderable_column' => null], // country
            ['searchable_columns' => [], 'orderable_column' => 'blog_posts.status'],
            ['searchable_columns' => [], 'orderable_column' => 'blog_posts.published_at'],
            ['searchable_columns' => [], 'orderable_column' => 'blog_posts.views_count'],
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        if (!$query->getQuery()->orders) {
            $query->orderByDesc('blog_posts.published_at');
        }

        $statusColors = [
            'draft' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'label' => 'Draft'],
            'scheduled' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'label' => 'Scheduled'],
            'published' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'label' => 'Published'],
            'archived' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'label' => 'Archived'],
        ];

        return $this->dataTableResponse($request, $query, $columns, function (BlogPost $row) use ($statusColors) {
            $imgHtml = $row->featured_image_path
                ? '<img src="' . e(Storage::disk('public')->url($row->featured_image_path)) . '" class="w-10 h-10 object-cover rounded-lg">'
                : '<div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-gray-300 text-xs">—</div>';

            $categoryBadge = $row->category
                ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-primary-50 text-primary-700">' . e($row->category->name_en) . '</span>'
                : '';

            $titleHtml = '<div class="min-w-0">'
                . '<div class="font-medium text-gray-900 truncate max-w-xs">' . e($row->title_en) . '</div>'
                . '<div class="text-xs text-gray-400 truncate max-w-xs" dir="rtl">' . e($row->title_ar) . '</div>'
                . '<div class="mt-0.5">' . $categoryBadge . '</div>'
                . '</div>';

            $countryHtml = $row->country
                ? e(($row->country->flag_emoji ? $row->country->flag_emoji . ' ' : '') . $row->country->name_en)
                : '<span class="text-gray-400 text-xs">All Countries</span>';

            $sc = $statusColors[$row->status->value] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'label' => $row->status->label()];
            $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' . $sc['bg'] . ' ' . $sc['text'] . '">' . $sc['label'] . '</span>';

            $dateHtml = $row->status === BlogPostStatus::Scheduled && $row->scheduled_for
                ? '<div class="text-xs text-blue-600">' . $row->scheduled_for->format('d M Y H:i') . '</div><div class="text-xs text-gray-400">Scheduled</div>'
                : ($row->published_at
                    ? '<div class="text-xs text-gray-700">' . $row->published_at->format('d M Y') . '</div>'
                    : '<span class="text-gray-300 text-xs">—</span>');

            $featuredStar = $row->is_featured
                ? '<span class="inline-block text-amber-400" title="Featured">★</span> '
                : '';

            $editUrl = route('admin.blog.posts.edit', $row->id);
            $archiveUrl = route('admin.blog.posts.archive', $row->id);
            $featureUrl = route('admin.blog.posts.feature', $row->id);
            $deleteUrl = route('admin.blog.posts.destroy', $row->id);

            $featureLabel = $row->is_featured ? 'Unfeature' : 'Feature';
            $featureClass = $row->is_featured ? 'text-amber-500' : 'text-gray-500';

            $actionsHtml = '<div class="relative" x-data="{open:false}">'
                . '<button @click="open=!open" class="btn btn-ghost btn-sm">⋮</button>'
                . '<div x-show="open" @click.away="open=false" x-cloak class="absolute right-0 z-10 w-40 bg-white border border-gray-200 rounded-lg shadow-lg py-1 text-sm">'
                . '<a href="' . $editUrl . '" class="block px-4 py-2 text-gray-700 hover:bg-gray-50">Edit</a>'
                . '<button type="button" class="btn-feature w-full text-left px-4 py-2 ' . $featureClass . ' hover:bg-gray-50" data-id="' . $row->id . '" data-url="' . $featureUrl . '">' . $featuredStar . $featureLabel . '</button>'
                . '<button type="button" class="btn-archive w-full text-left px-4 py-2 text-amber-600 hover:bg-gray-50" data-id="' . $row->id . '" data-url="' . $archiveUrl . '">Archive</button>'
                . '<button type="button" class="btn-delete w-full text-left px-4 py-2 text-red-500 hover:bg-gray-50" data-id="' . $row->id . '" data-url="' . $deleteUrl . '">Delete</button>'
                . '</div></div>';

            return [
                'image' => $imgHtml,
                'title' => $titleHtml,
                'author' => e($row->author?->name ?? '—'),
                'country' => $countryHtml,
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
        return view('admin.blog.posts.create', $this->formData());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Store
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePost($request);

        $slug = $this->uniqueSlug($request->slug ?: Str::slug($request->title_en));

        $data = $this->buildPostData($request, $validated, $slug);
        $data = $this->applyStatusLogic($request, $data);

        $featuredImagePath = $this->handleImageUpload($request, 'featured_image');
        $ogImagePath = $this->handleImageUpload($request, 'og_image');

        if ($featuredImagePath) {
            $data['featured_image_path'] = $featuredImagePath;
        }
        if ($ogImagePath) {
            $data['og_image_path'] = $ogImagePath;
        }

        $data['reading_time_minutes'] = $this->computeReadingTime($request->input('body_en', ''));
        $data['tags'] = $this->parseTags($request->input('tags', ''));

        $post = DB::transaction(function () use ($data, $request) {
            $post = BlogPost::create($data);
            $this->storeAttachments($request, $post);

            return $post;
        });

        return redirect()->route('admin.blog.posts.edit', $post->id)
            ->with('success', __('admin.blog_posts_section.saved'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(BlogPost $post): View
    {
        return view('admin.blog.posts.edit', array_merge($this->formData(), [
            'post' => $post,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.blog_posts'), 'url' => route('admin.blog.posts.index')],
                ['label' => e($post->title_en)],
            ],
        ]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $validated = $this->validatePost($request, $post->id);

        $slug = $request->filled('slug') ? $this->uniqueSlug($request->slug, $post->id) : $post->slug;

        $data = $this->buildPostData($request, $validated, $slug);
        $data = $this->applyStatusLogic($request, $data, $post);

        $featuredImagePath = $this->handleImageUpload($request, 'featured_image');
        $ogImagePath = $this->handleImageUpload($request, 'og_image');

        if ($featuredImagePath) {
            $data['featured_image_path'] = $featuredImagePath;
        }
        if ($ogImagePath) {
            $data['og_image_path'] = $ogImagePath;
        }

        $data['reading_time_minutes'] = $this->computeReadingTime($request->input('body_en', ''));
        $data['tags'] = $this->parseTags($request->input('tags', ''));

        DB::transaction(function () use ($post, $data, $request) {
            $post->update($data);
            $this->deleteAttachmentIds($post, $request->input('delete_attachment_ids', []));
            $this->storeAttachments($request, $post);
        });

        return redirect()->route('admin.blog.posts.edit', $post->id)
            ->with('success', __('admin.blog_posts_section.updated'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Attachments
    // ─────────────────────────────────────────────────────────────────────────

    public function deleteAttachment(BlogPost $post, File $file): JsonResponse
    {
        if ($file->model_type !== BlogPost::class || $file->model_id !== $post->id) {
            abort(403);
        }

        $file->delete();

        return response()->json(['success' => true]);
    }

    private function storeAttachments(Request $request, BlogPost $post): void
    {
        if (!$request->hasFile('attachments')) {
            return;
        }

        $position = (int) $post->attachments()->max('position');

        foreach ($request->file('attachments') as $uploaded) {
            $position++;
            $path = $uploaded->store('blog-attachments/' . $post->id, 'public');

            File::create([
                'model_type' => BlogPost::class,
                'model_id' => $post->id,
                'file_type' => 'blog_attachment',
                'storage_type' => 'public',
                'path' => $path,
                'mime_type' => $uploaded->getMimeType(),
                'extension' => $uploaded->getClientOriginalExtension(),
                'size' => $uploaded->getSize(),
                'position' => $position,
            ]);
        }
    }

    private function deleteAttachmentIds(BlogPost $post, array $fileIds): void
    {
        if (empty($fileIds)) {
            return;
        }

        $post->attachments()->whereIn('id', $fileIds)->get()->each->delete();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Destroy
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(BlogPost $post): JsonResponse
    {
        $post->delete();

        return response()->json(['success' => true, 'message' => __('admin.blog_posts_section.deleted')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Archive
    // ─────────────────────────────────────────────────────────────────────────

    public function archive(BlogPost $post): JsonResponse
    {
        $post->update([
            'status' => 'archived',
            'archived_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => __('admin.blog_posts_section.archived')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Feature
    // ─────────────────────────────────────────────────────────────────────────

    public function feature(BlogPost $post): JsonResponse
    {
        $newFeatured = !$post->is_featured;

        if ($newFeatured) {
            // Unfeat all other published posts in the same country scope
            BlogPost::where('is_featured', true)
                ->where('id', '!=', $post->id)
                ->where(function ($q) use ($post) {
                    if ($post->country_id) {
                        $q->where('country_id', $post->country_id);
                    } else {
                        $q->whereNull('country_id');
                    }
                })
                ->update(['is_featured' => false]);
        }

        $post->update(['is_featured' => $newFeatured]);

        return response()->json(['success' => true, 'is_featured' => $newFeatured]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Increment Views (public, no auth)
    // ─────────────────────────────────────────────────────────────────────────

    public function incrementViews(BlogPost $post): JsonResponse
    {
        DB::table('blog_posts')->where('id', $post->id)->increment('views_count');

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Query building / Export
    // ─────────────────────────────────────────────────────────────────────────

    private function buildPostsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = BlogPost::query()
            ->select('blog_posts.*')
            ->with(['category', 'country', 'author'])
            ->withoutTrashed();

        return $this->applyFilters($query, $request, [
            'search' => fn($q, $v) => $q->where(function ($qq) use ($v) {
                $qq->where('blog_posts.title_en', 'like', '%' . $v . '%')
                    ->orWhere('blog_posts.slug', 'like', '%' . $v . '%');
            }),
            'status' => fn($q, $v) => $q->where('blog_posts.status', $v),
            'category_id' => fn($q, $v) => $q->where('blog_posts.blog_category_id', $v),
            'blog_category_id' => fn($q, $v) => $q->where('blog_posts.blog_category_id', $v),
            'country_id' => fn($q, $v) => $q->where('blog_posts.country_id', $v),
            'author_admin_id' => fn($q, $v) => $q->where('blog_posts.author_admin_id', $v),
            'is_published' => fn($q, $v) => $q->when(
                (bool) $v,
                fn($qq) => $qq->whereNotNull('blog_posts.published_at'),
                fn($qq) => $qq->whereNull('blog_posts.published_at')
            ),
            'date_from' => fn($q, $v) => $q->whereDate('blog_posts.published_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('blog_posts.published_at', '<=', $v),
        ]);
    }

    private function exportPosts(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $posts = $this->buildPostsQuery($request)->orderByDesc('blog_posts.published_at')->get();

        $headers = ['Title', 'Category', 'Published', 'Views', 'Date'];

        $rows = $posts->map(fn($post) => [
            $post->title_en,
            $post->category?->name_en,
            $post->published_at ? 'Yes' : 'No',
            (int) $post->views_count,
            optional($post->published_at ?? $post->created_at)->format('d M Y H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('blog_posts', $headers, $rows),
            'csv' => $this->exportCsv('blog_posts', $headers, $rows),
            'word' => $this->exportWord('blog_posts', 'Blog Posts', $rows),
            default => abort(400, __('admin.common.invalid_export_format')),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function formData(): array
    {
        $categories = BlogCategory::whereNull('parent_id')
            ->with(['children' => fn($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $countries = Country::where('is_launched', true)->orderBy('name_en')->get(['id', 'name_en', 'flag_emoji']);

        $authors = Admin::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.nav.blog_posts'), 'url' => route('admin.blog.posts.index')],
                ['label' => __('admin.blog.new_post')],
            ],
            'categories' => $categories,
            'countries' => $countries,
            'authors' => $authors,
        ];
    }

    private function validatePost(Request $request, ?string $excludePostId = null): array
    {
        return $request->validate([
            'blog_category_id' => 'required|string|exists:blog_categories,id',
            'country_id' => 'nullable|string|exists:countries,id',
            'author_admin_id' => 'nullable|string|exists:admins,id',
            'title_en' => 'required|string|max:255',
            'title_ar' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:blog_posts,slug,' . ($excludePostId ?? 'NULL'),
            'excerpt_en' => 'nullable|string|max:500',
            'excerpt_ar' => 'nullable|string|max:500',
            'body_en' => 'required|string',
            'body_ar' => 'required|string',
            'featured_image' => 'nullable|image|max:5120',
            'featured_image_alt_en' => 'nullable|string|max:255',
            'featured_image_alt_ar' => 'nullable|string|max:255',
            'og_image' => 'nullable|image|max:5120',
            'seo_title_en' => 'nullable|string|max:200',
            'seo_title_ar' => 'nullable|string|max:200',
            'seo_description_en' => 'nullable|string|max:500',
            'seo_description_ar' => 'nullable|string|max:500',
            'allow_comments' => 'nullable|boolean',
            'scheduled_for' => 'nullable|date',
            'tags' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,png,jpg,jpeg|max:20480',
            'delete_attachment_ids' => 'nullable|array',
            'delete_attachment_ids.*' => 'integer|exists:files,id',
        ]);
    }

    private function buildPostData(Request $request, array $validated, string $slug): array
    {
        return [
            'blog_category_id' => $validated['blog_category_id'],
            'country_id' => $validated['country_id'] ?? null,
            'author_admin_id' => $validated['author_admin_id'] ?? auth('admin')->id(),
            'title_en' => $validated['title_en'],
            'title_ar' => $validated['title_ar'],
            'slug' => $slug,
            'excerpt_en' => $validated['excerpt_en'] ?? null,
            'excerpt_ar' => $validated['excerpt_ar'] ?? null,
            'body_en' => $validated['body_en'],
            'body_ar' => $validated['body_ar'],
            'featured_image_alt_en' => $validated['featured_image_alt_en'] ?? null,
            'featured_image_alt_ar' => $validated['featured_image_alt_ar'] ?? null,
            'seo_title_en' => $validated['seo_title_en'] ?? null,
            'seo_title_ar' => $validated['seo_title_ar'] ?? null,
            'seo_description_en' => $validated['seo_description_en'] ?? null,
            'seo_description_ar' => $validated['seo_description_ar'] ?? null,
            'allow_comments' => $request->boolean('allow_comments', true),
            'scheduled_for' => $validated['scheduled_for'] ?? null,
        ];
    }

    private function applyStatusLogic(Request $request, array $data, ?BlogPost $existing = null): array
    {
        $action = $request->input('action', 'draft');

        if ($action === 'publish') {
            $data['status'] = 'published';
            $data['published_at'] = now();
            $data['published_by_admin_id'] = auth('admin')->id();
            $data['archived_at'] = null;
        } elseif ($action === 'schedule' && !empty($data['scheduled_for'])) {
            $data['status'] = 'scheduled';
        } elseif ($action === 'archive') {
            $data['status'] = 'archived';
            $data['archived_at'] = now();
        } else {
            $data['status'] = 'draft';
        }

        return $data;
    }

    private function handleImageUpload(Request $request, string $field): ?string
    {
        if (!$request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        return $file->store('blog/images', 'public');
    }

    private function computeReadingTime(string $body): int
    {
        return (int) ceil(str_word_count(strip_tags($body)) / 200) ?: 1;
    }

    private function parseTags(string $raw): array
    {
        if (empty(trim($raw))) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function uniqueSlug(string $base, ?string $excludeId = null): string
    {
        $slug = $base;
        $i = 1;
        while (
            BlogPost::withTrashed()
                ->where('slug', $slug)
                ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
