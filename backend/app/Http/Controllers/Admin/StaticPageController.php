<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaticPage;
use App\Support\HtmlSanitizer;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin CRUD for bilingual static content pages (About Us, Terms, Privacy, ...)
 * shown on the seller portal and/or the customer storefront. See App\Models\StaticPage.
 *
 * Permission-gated per action in routes/admin.php (static_pages.view|create|edit|delete).
 * Writes always go through the model so StaticPage::flushCache() runs.
 */
class StaticPageController extends Controller
{
    use HasDataTable;

    public function index(): View
    {
        return view('admin.static-pages.index', [
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.static_pages.title')],
            ],
            'stats' => [
                'total' => StaticPage::count(),
                'active' => StaticPage::active()->count(),
                'in_footer' => StaticPage::active()->inFooter()->count(),
            ],
            'audiences' => StaticPage::AUDIENCES,
            'footerGroups' => StaticPage::FOOTER_GROUPS,
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = StaticPage::query()->select('static_pages.*');

        $query = $this->applyFilters($query, $request, [
            'audience' => fn ($q, $v) => $q->where('static_pages.audience', $v),
            'footer_group' => fn ($q, $v) => $q->where('static_pages.footer_group', $v),
            'is_active' => fn ($q, $v) => $q->where('static_pages.is_active', $v === '1'),
        ]);

        $columns = [
            ['searchable_columns' => ['static_pages.title_en', 'static_pages.title_ar', 'static_pages.slug'], 'orderable_column' => 'static_pages.title_en'],
            ['searchable_columns' => [], 'orderable_column' => 'static_pages.audience'],
            ['searchable_columns' => [], 'orderable_column' => 'static_pages.footer_group'],
            ['searchable_columns' => [], 'orderable_column' => 'static_pages.show_in_footer'],
            ['searchable_columns' => [], 'orderable_column' => 'static_pages.is_active'],
            ['searchable_columns' => [], 'orderable_column' => 'static_pages.sort_order'],
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        if (! $query->getQuery()->orders) {
            $query->orderBy('static_pages.sort_order')->orderBy('static_pages.title_en');
        }

        $admin = auth('admin')->user();
        $canEdit = $admin->can('static_pages.edit');
        $canDelete = $admin->can('static_pages.delete');

        return $this->dataTableResponse($request, $query, $columns, function (StaticPage $page) use ($canEdit, $canDelete) {
            $titleHtml = '<div class="min-w-0">'
                .'<div class="font-medium text-gray-900 truncate max-w-xs">'.e($page->title_en).'</div>'
                .'<div class="text-xs text-gray-500 truncate max-w-xs" dir="rtl">'.e($page->title_ar).'</div>'
                .'<div class="text-xs text-gray-400 truncate max-w-xs font-mono">/'.e($page->slug).'</div>'
                .'</div>';

            $audienceHtml = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-50 text-primary-700">'
                .e(__('admin.static_pages.audiences.'.$page->audience)).'</span>';

            $groupHtml = $page->footer_group
                ? '<span class="text-xs text-gray-700">'.e(__('admin.static_pages.footer_groups.'.$page->footer_group)).'</span>'
                : '<span class="text-gray-300 text-xs">—</span>';

            $footerHtml = $page->show_in_footer
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">'.e(__('common.yes')).'</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">'.e(__('common.no')).'</span>';

            $activeHtml = $page->is_active
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">'.e(__('common.active')).'</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">'.e(__('common.inactive')).'</span>';

            $items = '';
            if ($canEdit) {
                $items .= '<a href="'.e(route('admin.static-pages.edit', $page->id)).'" class="block px-4 py-2 text-gray-700 hover:bg-gray-50">'.e(__('common.edit')).'</a>'
                    .'<button type="button" class="btn-toggle w-full text-start px-4 py-2 text-gray-600 hover:bg-gray-50" data-url="'.e(route('admin.static-pages.toggle', $page->id)).'">'
                    .e(__($page->is_active ? 'admin.static_pages.deactivate' : 'admin.static_pages.activate')).'</button>';
            }
            if ($canDelete) {
                $items .= '<button type="button" class="btn-delete w-full text-start px-4 py-2 text-red-500 hover:bg-gray-50" data-url="'.e(route('admin.static-pages.destroy', $page->id)).'">'.e(__('common.delete')).'</button>';
            }

            $actionsHtml = $items === ''
                ? ''
                : '<div class="relative" x-data="{open:false}">'
                    .'<button type="button" @click="open=!open" class="btn btn-ghost btn-sm">⋮</button>'
                    .'<div x-show="open" @click.away="open=false" x-cloak class="absolute end-0 z-10 w-44 bg-white border border-gray-200 rounded-lg shadow-lg py-1 text-sm">'
                    .$items
                    .'</div></div>';

            return [
                'title' => $titleHtml,
                'audience' => $audienceHtml,
                'footer_group' => $groupHtml,
                'show_in_footer' => $footerHtml,
                'is_active' => $activeHtml,
                'sort_order' => (string) $page->sort_order,
                'actions' => $actionsHtml,
            ];
        });
    }

    public function create(): View
    {
        return view('admin.static-pages.create', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $page = StaticPage::create($this->validatedData($request));

        return redirect()->route('admin.static-pages.edit', $page->id)
            ->with('success', __('admin.static_pages.created'));
    }

    public function edit(StaticPage $staticPage): View
    {
        return view('admin.static-pages.edit', $this->formData($staticPage));
    }

    public function update(Request $request, StaticPage $staticPage): RedirectResponse
    {
        $staticPage->fill($this->validatedData($request, $staticPage))->save();

        return redirect()->route('admin.static-pages.edit', $staticPage->id)
            ->with('success', __('admin.static_pages.updated'));
    }

    /** Soft delete; the slug stays reserved (unique incl. trashed rows). */
    public function destroy(StaticPage $staticPage): JsonResponse
    {
        $staticPage->delete();

        return response()->json(['success' => true, 'message' => __('admin.static_pages.deleted')]);
    }

    public function toggleActive(StaticPage $staticPage): JsonResponse
    {
        $staticPage->is_active = ! $staticPage->is_active;
        $staticPage->updated_by_admin_id = auth('admin')->id();
        $staticPage->save();

        return response()->json(['success' => true, 'is_active' => $staticPage->is_active]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function formData(?StaticPage $page): array
    {
        return [
            'page' => $page,
            'audiences' => StaticPage::AUDIENCES,
            'footerGroups' => StaticPage::FOOTER_GROUPS,
            'breadcrumbs' => [
                ['label' => __('admin.nav.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.static_pages.title'), 'url' => route('admin.static-pages.index')],
                ['label' => $page ? $page->title_en : __('admin.static_pages.new_page')],
            ],
        ];
    }

    /**
     * Validate the form and return model attributes (sanitized HTML, normalized slug).
     *
     * The slug is the given one (normalized), else derived from the English title. An
     * English title that is not Latin (e.g. Arabic) yields an empty slug, which fails
     * as "slug required" so the admin has to type one.
     *
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?StaticPage $page = null): array
    {
        $request->merge([
            'slug' => Str::slug((string) ($request->filled('slug') ? $request->input('slug') : $request->input('title_en'))),
        ]);

        $data = $request->validate([
            'title_en' => ['required', 'string', 'max:255'],
            'title_ar' => ['required', 'string', 'max:255'],
            // Rule::unique ignores soft deletes, so trashed pages keep their slug reserved.
            'slug' => ['required', 'string', 'max:150', Rule::unique('static_pages', 'slug')->ignore($page?->id)],
            'content_en' => ['nullable', 'string'],
            'content_ar' => ['nullable', 'string'],
            'seo_title_en' => ['nullable', 'string', 'max:255'],
            'seo_title_ar' => ['nullable', 'string', 'max:255'],
            'seo_description_en' => ['nullable', 'string', 'max:500'],
            'seo_description_ar' => ['nullable', 'string', 'max:500'],
            'audience' => ['required', 'string', Rule::in(StaticPage::AUDIENCES)],
            'show_in_footer' => ['nullable', 'boolean'],
            'footer_group' => ['nullable', 'required_if_accepted:show_in_footer', 'string', Rule::in(StaticPage::FOOTER_GROUPS)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'slug.required' => __('admin.static_pages.slug_required'),
            'footer_group.required_if_accepted' => __('admin.static_pages.footer_group_required'),
        ]);

        return [
            'title_en' => $data['title_en'],
            'title_ar' => $data['title_ar'],
            'slug' => $data['slug'],
            'content_en' => HtmlSanitizer::clean($data['content_en'] ?? null),
            'content_ar' => HtmlSanitizer::clean($data['content_ar'] ?? null),
            'seo_title_en' => $data['seo_title_en'] ?? null,
            'seo_title_ar' => $data['seo_title_ar'] ?? null,
            'seo_description_en' => $data['seo_description_en'] ?? null,
            'seo_description_ar' => $data['seo_description_ar'] ?? null,
            'audience' => $data['audience'],
            'show_in_footer' => $request->boolean('show_in_footer'),
            'footer_group' => $data['footer_group'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
            'updated_by_admin_id' => auth('admin')->id(),
        ];
    }
}
