<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PageStatus;
use App\Http\Controllers\Controller;
use App\Models\AdImageItem;
use App\Models\BlockAnalytic;
use App\Models\BlockClickEvent;
use App\Models\Banner;
use App\Models\BlockType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\FlashSale;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageBlockBrand;
use App\Models\PageBlockProduct;
use App\Models\PageBlockRevision;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\ProductVariant;
use App\Models\SliderSlide;
use Illuminate\Validation\Rule;
use App\Models\Vendor;
use App\Models\File;
use App\Services\PageBuilderService;
use App\Services\Shared\PageCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class PageBuilderController extends Controller
{
    public function __construct(
        private PageBuilderService $service,
        private PageCacheService $pageCache,
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────
    // UI
    // ─────────────────────────────────────────────────────────────────────

    public function index()
    {
        $pages = Page::orderByDesc('updated_at')
            ->get(['id', 'name', 'slug', 'page_type', 'app_context_key', 'country_id', 'status', 'version']);

        $countries = Country::orderBy('name_en')->get(['id', 'name_en', 'site_code']);

        $blockTypes = BlockType::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('group');

        // Preview of live slider blocks (hero_slider) with resolved image URLs, so admins
        // can verify slide images render before publishing. Note: 'cart_banner' is not a
        // page block type — it's served by the separate Banner/BannerService system.
        $sliderPreviewBlocks = PageBlock::where('block_type', 'hero_slider')
            ->where('is_visible', true)
            ->with(['slides.desktopFile', 'slides.mobileFile', 'page:id,name,slug'])
            ->get();

        return view('admin.page-builder.index', compact('pages', 'countries', 'blockTypes', 'sliderPreviewBlocks'));
    }

    public function loadPage(Request $request)
    {
        $request->validate(['page_id' => 'required|uuid']);
        return response()->json($this->service->getPageWithBlocks($request->string('page_id')));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Pages
    // ─────────────────────────────────────────────────────────────────────

    public function createPage(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'page_type' => 'required|string|in:home,category,brand,vendor,custom_page',
            'country_id' => 'required|uuid|exists:countries,id',
            'reference_id' => match ($request->input('page_type')) {
                'category' => ['required', 'uuid', 'exists:categories,id'],
                'brand' => ['required', 'uuid', 'exists:brands,id'],
                'vendor' => ['required', 'uuid', 'exists:vendors,id'],
                'custom_page' => ['required', 'uuid', 'exists:custom_pages,id'],
                default => ['prohibited'],
            },
        ]);

        $page = $this->service->createPage($data, $this->admin());

        return response()->json(['page' => $page]);
    }

    public function updatePage(Request $request, Page $page)
    {
        $this->authorizeManage();

        $effectiveType = $request->input('page_type', $page->page_type);

        $data = $request->validate([
            'name' => 'sometimes|string|max:150',
            'page_type' => 'sometimes|string|in:home,category,brand,vendor,custom_page',
            'country_id' => 'sometimes|uuid|exists:countries,id',
            'reference_id' => match ($effectiveType) {
                'category' => ['nullable', 'uuid', 'exists:categories,id'],
                'brand' => ['nullable', 'uuid', 'exists:brands,id'],
                'vendor' => ['nullable', 'uuid', 'exists:vendors,id'],
                'custom_page' => ['nullable', 'uuid', 'exists:custom_pages,id'],
                default => ['nullable'],
            },
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:180',
                Rule::unique('pages', 'slug')
                    ->where('country_id', $request->input('country_id', $page->country_id))
                    ->ignore($page->id),
            ],
            'app_context_key' => 'nullable|string|max:50|exists:app_contexts,key',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'og_image_url' => 'nullable|string|max:500',
            'publish_at' => 'nullable|date',
            'unpublish_at' => 'nullable|date|after_or_equal:publish_at',
        ]);

        if ($effectiveType === 'home') {
            $data['reference_id'] = null;
        }

        if (empty($data['slug'])) {
            unset($data['slug']);
        }

        $page->update($data + ['last_edited_by_admin_id' => $this->admin()->id]);

        return response()->json(['success' => true, 'message' => 'Page updated.']);
    }

    public function deletePage(Page $page)
    {
        $this->authorizeManage();
        abort_if(
            $page->status === PageStatus::Published && !$this->admin()->hasPermissionTo('pages.delete_published'),
            403,
            'Cannot delete a published page without the pages.delete_published permission.'
        );
        $page->delete();
        return response()->json(['success' => true, 'message' => 'Page deleted.']);
    }

    public function duplicatePage(Page $page)
    {
        $this->authorizeManage();
        $newPage = $this->service->duplicatePage($page, $this->admin());
        return response()->json(['success' => true, 'data' => ['page' => $newPage], 'message' => 'Page duplicated.']);
    }

    public function publishPage(Request $request, Page $page)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $this->service->publishPage($page, $this->admin(), $data['reason'] ?? '');
        $page->refresh();
        $this->pageCache->bustPage($page);

        return response()->json([
            'success' => true,
            'data' => [
                'version' => $page->version,
                'published_at' => $page->published_at?->format('M d, Y H:i'),
            ],
            'message' => 'Page published successfully.',
        ]);
    }

    public function clearPageCache(Page $page): \Illuminate\Http\JsonResponse
    {
        $this->authorizeManage();
        $this->pageCache->bustPage($page);
        return response()->json(['success' => true, 'message' => 'Page cache cleared.']);
    }

    public function getPageRevisions(Page $page)
    {
        $revisions = PageRevision::where('page_id', $page->id)
            ->with('publishedByAdmin:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'version', 'published_by_admin_id', 'publish_reason', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $revisions->map(fn($r) => [
                'id' => $r->id,
                'version' => $r->version,
                'published_by' => $r->publishedByAdmin?->name,
                'reason' => $r->publish_reason,
                'created_at' => $r->created_at?->format('M d, Y H:i'),
            ])
        ]);
    }

    public function restorePageRevision(PageRevision $revision)
    {
        $this->authorizeManage();
        $this->service->restoreRevision($revision, $this->admin());
        return response()->json(['success' => true, 'message' => 'Page restored to version ' . $revision->version . '.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Sections
    // ─────────────────────────────────────────────────────────────────────

    public function addSection(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'page_id'         => ['required', 'uuid', 'exists:pages,id'],
            'name'            => ['sometimes', 'nullable', 'string', 'max:150'],
            'position'        => ['required', 'integer', 'min:0'],
            'layout'          => ['nullable', 'in:stack,columns'],
            'columns_config'  => ['nullable', 'string', 'max:100'],
            'background_image_url' => ['nullable', 'string', 'max:500'],
            'background_image_type' => ['nullable', 'in:section,header'],
            'background_color' => ['nullable', 'string', 'max:20'],
            'max_width'       => ['nullable', 'string', 'max:20'],
            'padding_top'     => ['nullable', 'integer', 'min:0', 'max:200'],
            'padding_bottom'  => ['nullable', 'integer', 'min:0', 'max:200'],
            'is_visible'      => ['sometimes', 'boolean'],
        ]);

        if (!empty($data['columns_config']) && is_string($data['columns_config'])) {
            $decoded = json_decode($data['columns_config'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['columns_config'] = $decoded;
            }
        }

        $data['name']           = $data['name']           ?? 'New Section';
        $data['padding_top']    = $data['padding_top']    ?? 0;
        $data['padding_bottom'] = $data['padding_bottom'] ?? 0;
        $data['layout']         = $data['layout']         ?? 'stack';
        $data['is_visible']     = $data['is_visible']     ?? true;

        $section = PageSection::create($data);

        return response()->json(['success' => true, 'data' => ['section' => $section]]);
    }

    public function updateSection(Request $request, PageSection $section): \Illuminate\Http\JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'name'              => ['sometimes', 'string', 'max:150'],
            'is_visible'        => ['sometimes', 'boolean'],
            'background_color'  => ['nullable', 'string', 'max:20'],
            'background_image_url' => ['nullable', 'string', 'max:500'],
            'background_image_type' => ['nullable', 'in:section,header'],
            'padding_top'       => ['nullable', 'integer', 'min:0'],
            'padding_bottom'    => ['nullable', 'integer', 'min:0'],
            'max_width'         => ['nullable', 'string', 'max:20'],
            'layout'            => ['nullable', 'in:stack,columns'],
            'columns_config'    => ['nullable', 'string', 'max:100'],
        ]);

        if (array_key_exists('columns_config', $data) && is_string($data['columns_config'])) {
            $decoded = json_decode($data['columns_config'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['columns_config'] = $decoded;
            }
        }

        $section->update($data);

        $page = $section->page;
        if ($page) {
            $this->pageCache->bustPage($page);
        }

        return response()->json(['success' => true, 'data' => ['section' => $section]]);
    }

    public function deleteSection(PageSection $section): \Illuminate\Http\JsonResponse
    {
        $this->authorizeManage();

        $page = $section->page;

        // Don't cascade-delete the blocks — just detach them so they fall back
        // to the "ungrouped" area instead of being lost.
        PageBlock::where('section_id', $section->id)->update(['section_id' => null]);

        $section->delete();

        if ($page) {
            $this->pageCache->bustPage($page);
        }

        return response()->json(['success' => true]);
    }

    public function reorderSections(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'page_id' => 'nullable|uuid',
            'sections' => 'required|array|min:1',
            'sections.*.id' => 'required|uuid',
            'sections.*.position' => 'required|integer|min:0',
        ]);

        $this->service->reorderSections($data['sections']);

        $page = isset($data['page_id']) ? Page::find($data['page_id']) : null;
        if ($page) {
            $this->pageCache->bustPage($page);
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Blocks
    // ─────────────────────────────────────────────────────────────────────

    public function assignBlockColumn(Request $request, PageBlock $block): \Illuminate\Http\JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'column_index' => ['required', 'integer', 'min:0', 'max:10'],
        ]);

        $block->update(['column_index' => $data['column_index']]);
        $this->pageCache->bustBlock($block);

        return response()->json(['success' => true]);
    }

    public function addBlock(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'page_id' => 'required|uuid|exists:pages,id',
            'block_type_code' => 'required|string',
            'position' => 'required|integer|min:0',
            'section_id' => 'nullable|uuid|exists:page_sections,id',
            'column_index' => 'nullable|integer|min:0',
        ]);

        $page = Page::findOrFail($data['page_id']);
        $block = $this->service->addBlock(
            $page,
            $data['block_type_code'],
            (int) $data['position'],
            $this->admin(),
            $data['section_id'] ?? null,
            $data['column_index'] ?? null,
        );
        $page->load('blocks');
        $this->pageCache->bustPage($block->page ?? $page);

        return response()->json([
            'block_id' => $block->id,
            'block_type' => $block->block_type,
            'default_config' => $block->config,
            'label_en' => optional($block->blockType)->label_en,
            'icon' => optional($block->blockType)->icon,
            'preview_text' => $block->getPreviewText(),
        ]);
    }

    public function getBlockConfig(PageBlock $block)
    {
        return response()->json([
            'id' => $block->id,
            'block_type' => $block->block_type,
            'config' => $block->config ?? [],
            'is_visible' => (bool) $block->is_visible,
            'visible_from' => optional($block->visible_from)->format('Y-m-d H:i'),
            'visible_until' => optional($block->visible_until)->format('Y-m-d H:i'),
            'device_target' => $block->device_target,
            'audience' => $block->audience,
        ]);
    }

    // Read-only, admin-only aggregate view — never expose user_id/session_id/ip_address from block_click_events.
    public function blockAnalytics(Request $request, PageBlock $block)
    {
        $data = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $dateTo = isset($data['date_to']) ? Carbon::parse($data['date_to'])->startOfDay() : today();
        $dateFrom = isset($data['date_from']) ? Carbon::parse($data['date_from'])->startOfDay() : $dateTo->copy()->subDays(30);

        $cacheKey = "block_analytics:{$block->id}:{$dateFrom->toDateString()}:{$dateTo->toDateString()}";

        $payload = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($block, $dateFrom, $dateTo) {
            $rows = BlockAnalytic::where('page_block_id', $block->id)
                ->whereBetween('date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->orderBy('date')
                ->get();

            $totals = [
                'impressions' => (int) $rows->sum('impressions'),
                'clicks' => (int) $rows->sum('clicks'),
                'unique_visitors' => (int) $rows->sum('unique_visitors'),
                'add_to_cart_count' => (int) $rows->sum('add_to_cart_count'),
                'orders_attributed' => (int) $rows->sum('orders_attributed'),
                'revenue_attributed' => (int) $rows->sum('revenue_attributed'),
            ];
            $totals['ctr'] = $totals['impressions'] > 0
                ? round($totals['clicks'] / $totals['impressions'], 4)
                : 0.0;

            $byDate = $rows->keyBy(fn($row) => $row->date->toDateString());
            $chart = [];
            for ($day = $dateFrom->copy(); $day->lte($dateTo); $day->addDay()) {
                $key = $day->toDateString();
                $row = $byDate->get($key);
                $impressions = $row->impressions ?? 0;
                $clicks = $row->clicks ?? 0;
                $chart[] = [
                    'date' => $key,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0.0,
                ];
            }

            $topClickTargets = BlockClickEvent::query()
                ->selectRaw('click_target, click_target_type, COUNT(*) as count')
                ->where('page_block_id', $block->id)
                ->whereBetween('clicked_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
                ->groupBy('click_target', 'click_target_type')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->map(fn($row) => [
                    'click_target' => $row->click_target,
                    'click_target_type' => $row->click_target_type,
                    'count' => (int) $row->count,
                ])
                ->values();

            return [
                'totals' => $totals,
                'chart' => $chart,
                'top_click_targets' => $topClickTargets,
            ];
        });

        return response()->json($payload);
    }

    public function updateBlockConfig(Request $request, PageBlock $block)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'config' => 'required|array',
            'change_type' => 'nullable|string|in:created,config_updated,moved,visibility_changed,deleted',
        ]);

        // Capture original source BEFORE saving (getOriginal works before save)
        $oldSource = is_array($block->config)
            ? ($block->config['source'] ?? null)
            : null;

        $revisionNumber = $this->service->updateBlockConfig(
            $block,
            $data['config'],
            $data['change_type'] ?? 'config_updated',
            $this->admin()
        );

        $this->pageCache->bustBlock($block);

        // Clean up orphaned manual product rows when source changes away from manual
        $newSource = $data['config']['source'] ?? null;
        if ($newSource !== null && $newSource !== 'manual' && $oldSource === 'manual') {
            PageBlockProduct::where('page_block_id', $block->id)->delete();
        }

        return response()->json([
            'success' => true,
            'revision_number' => $revisionNumber,
            'preview_text' => $block->fresh()->getPreviewText(),
        ]);
    }

    public function updateBlockVisibility(Request $request, PageBlock $block)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'is_visible' => 'required|in:true,false,1,0',
            'visible_from' => 'nullable|date',
            'visible_until' => 'nullable|date|after_or_equal:visible_from',
            'device_target' => 'nullable|in:all,desktop,mobile,app',
            'audience' => 'nullable|in:all,guest,logged_in,vip',
        ]);

        $revisionNumber = $this->service->updateBlockVisibility($block, $data, $this->admin());
        $this->pageCache->bustBlock($block);

        return response()->json(['success' => true, 'revision_number' => $revisionNumber]);
    }

    public function removeBlock(PageBlock $block)
    {
        $this->authorizeManage();
        $page = $block->page;
        $this->service->removeBlock($block);
        if ($page) {
            $this->pageCache->bustPage($page);
        }
        return response()->json(['success' => true]);
    }

    public function reorderBlocks(Request $request)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'page_id' => 'nullable|uuid',
            'blocks' => 'required|array|min:1',
            'blocks.*.id' => 'required|uuid',
            'blocks.*.position' => 'required|integer|min:0',
            'blocks.*.section_id' => 'nullable|uuid|exists:page_sections,id',
        ]);

        $this->service->reorderBlocks($data['blocks']);

        $page = isset($data['page_id']) ? Page::find($data['page_id']) : null;
        if ($page) {
            $this->pageCache->bustPage($page);
        }

        return response()->json(['success' => true]);
    }

    public function getRevisions(PageBlock $block)
    {
        $revisions = $block->revisions()
            ->with('changedByAdmin:id,name')
            ->limit(50)
            ->get(['id', 'page_block_id', 'revision_number', 'change_type', 'change_reason', 'changed_by_admin_id', 'created_at']);

        return response()->json(['revisions' => $revisions]);
    }

    public function restoreBlockRevision(string $revisionId)
    {
        $this->authorizeManage();
        $revision = PageBlockRevision::findOrFail($revisionId);
        $this->service->restoreBlockRevision($revision, $this->admin());
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Config form partial
    // ─────────────────────────────────────────────────────────────────────

    public function configFormPartial(Request $request)
    {
        $request->validate([
            'block_type_code' => 'required|string',
            'block_id' => 'nullable|uuid',
        ]);

        $blockType = BlockType::where('code', $request->string('block_type_code'))->firstOrFail();
        $block = null;
        $config = (array) ($blockType->default_config ?? []);

        if ($id = $request->string('block_id')->toString()) {
            $block = PageBlock::find($id);
            if ($block) {
                $config = $block->config ?? $config;
            }
        }

        $view = 'admin.page-builder.config-forms.' . str_replace('_', '-', $blockType->code);

        if (!View::exists($view)) {
            $view = 'admin.page-builder.config-forms.generic';
        }

        $extra = [];
        if ($blockType->code === 'product_row') {
            $extra['categories'] = Category::orderBy('name_en')->get(['id', 'name_en']);
            $extra['flashSales'] = FlashSale::whereIn('status', ['submission_open', 'approved', 'live'])
                ->orWhere('id', $config['flash_sale_id'] ?? null)
                ->orderBy('name_en')->get(['id', 'name_en']);

            // Resolve human-readable labels so the async-select pre-populates
            // with the name instead of the raw UUID.
            if (!empty($config['category_id']) && empty($config['category_label'])) {
                $config['category_label'] = Category::where('id', $config['category_id'])
                    ->value('name_en');
            }
            if (!empty($config['flash_sale_id']) && empty($config['flash_sale_label'])) {
                $config['flash_sale_label'] = FlashSale::where('id', $config['flash_sale_id'])
                    ->value('name_en');
            }
        }
        if (in_array($blockType->code, ['flash_sale', 'deal_of_day'])) {
            $extra['flashSales'] = FlashSale::whereIn('status', ['submission_open', 'approved', 'live'])
                ->orWhere('id', $config['flash_sale_id'] ?? null)
                ->orderBy('name_en')->get(['id', 'name_en']);
        }
        if ($blockType->code === 'deal_of_day') {
            if (!empty($config['vendor_listing_id']) && empty($config['vendor_listing_label'])) {
                $vl = \App\Models\VendorListing::with(['productVariant.product:id,name_en', 'vendor:id,store_name'])
                    ->find($config['vendor_listing_id']);
                if ($vl) {
                    $config['vendor_listing_label'] = trim(
                        optional(optional($vl->productVariant)->product)->name_en
                        . ' — ' . optional($vl->vendor)->store_name
                        . ' — ' . number_format((float) $vl->price, 2) . ' ' . $vl->currency,
                        ' —'
                    );
                }
            }
            if (!empty($config['admin_listing_id']) && empty($config['admin_listing_label'])) {
                $al = \App\Models\AdminListing::query()
                    ->join('product_variants as pv', 'pv.id', '=', 'admin_listings.product_variant_id')
                    ->join('products as p', 'p.id', '=', 'pv.product_id')
                    ->where('admin_listings.id', $config['admin_listing_id'])
                    ->first(['admin_listings.id', 'admin_listings.price', 'admin_listings.currency', 'p.name_en']);
                if ($al) {
                    $config['admin_listing_label'] = $al->name_en
                        . ' (Platform) — ' . number_format((float) $al->price, 2) . ' ' . $al->currency;
                }
            }
        }
        if ($blockType->code === 'full_banner') {
            $extra['banners'] = Banner::orderBy('name')->get(['id', 'name']);
        }
        return response()->view($view, array_merge([
            'blockType' => $blockType,
            'block' => $block,
            'config' => $config,
        ], $extra));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Slides
    // ─────────────────────────────────────────────────────────────────────

    public function getSlides(PageBlock $block)
    {
        $slides = $block->slides()
            ->with(['desktopFile', 'mobileFile'])
            ->orderBy('position')
            ->get()
            ->map(function ($slide) {
                $arr = $slide->toArray();
                $arr['desktop_file_url'] = $slide->desktop_url;
                $arr['mobile_file_url'] = $slide->mobile_url;
                $arr['visible_from'] = $slide->visible_from?->format('Y-m-d H:i');
                $arr['visible_until'] = $slide->visible_until?->format('Y-m-d H:i');
                return $arr;
            });

        return response()->json(['slides' => $slides]);
    }

    public function saveSlide(Request $request, PageBlock $block)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'id' => 'nullable|uuid',
            'position' => 'nullable|integer|min:0',
            'desktop_file_id' => 'nullable|integer|exists:files,id',
            'mobile_file_id' => 'nullable|integer|exists:files,id',
            'title_en' => 'nullable|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'subtitle_en' => 'nullable|string|max:500',
            'subtitle_ar' => 'nullable|string|max:500',
            'cta_label_en' => 'nullable|string|max:100',
            'cta_label_ar' => 'nullable|string|max:100',
            'cta_url' => 'nullable|string|max:500',
            'cta_open_new_tab' => 'nullable|boolean',
            'text_color' => 'nullable|string|max:7',
            'text_position' => 'nullable|in:left,center,right',
            'overlay_opacity' => 'nullable|numeric|between:0,1',
            'link_type' => 'nullable|string|max:20',
            'link_reference_id' => 'nullable|uuid',
            'is_active' => 'nullable|boolean',
            'is_paid' => 'nullable|boolean',
            'visible_from' => 'nullable|date',
            'visible_until' => 'nullable|date',
        ]);

        $slide = $this->service->saveSlide($block, $data['id'] ?? null, $data);
        $this->pageCache->bustBlock($block);
        return response()->json(['slide' => $slide]);
    }

    public function deleteSlide(SliderSlide $slide)
    {
        $this->authorizeManage();
        $block = $slide->block;
        $slide->delete();
        if ($block) {
            $this->pageCache->bustBlock($block);
        }
        return response()->json(['success' => true]);
    }

    public function reorderSlides(Request $request, PageBlock $block)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'slides' => 'required|array',
            'slides.*.id' => 'required|uuid',
            'slides.*.position' => 'required|integer|min:0',
        ]);

        $this->service->reorderSlides($data['slides']);
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Ad images
    // ─────────────────────────────────────────────────────────────────────

    public function getAdImagesManagerPanel(PageBlock $block)
    {
        return view('admin.page-builder.config-forms.partials.ad-images-manager', ['block' => $block]);
    }

    public function getAdImages(PageBlock $block)
    {
        $items = $block->adImageItems()->orderBy('position')->get();
        return response()->json(['items' => $items]);
    }

    public function saveAdImage(Request $request, PageBlock $block)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'id' => 'nullable|uuid',
            'position' => 'nullable|integer|min:0',
            'file_id' => 'nullable|integer|exists:files,id',
            'title_en' => 'nullable|string|max:255',
            'title_ar' => 'nullable|string|max:255',
            'link_url' => 'nullable|string|max:500',
            'link_open_new_tab' => 'nullable|boolean',
            'alt_text_en' => 'nullable|string|max:255',
            'alt_text_ar' => 'nullable|string|max:255',
            'show_title_overlay' => 'nullable|boolean',
            'aspect_ratio' => 'nullable|string|max:10',
            'is_active' => 'nullable|boolean',
            'is_paid' => 'nullable|boolean',
        ]);

        $item = $this->service->saveAdImage($block, $data['id'] ?? null, $data);
        return response()->json(['item' => $item]);
    }

    public function deleteAdImage(AdImageItem $adImage)
    {
        $this->authorizeManage();
        $adImage->delete();
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Search (for picker selects)
    // ─────────────────────────────────────────────────────────────────────

    public function searchProducts(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $rows = ProductVariant::query()
            ->with('product:id,name_en')
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('product', fn($p) => $p->where('name_en', 'like', "%{$q}%"))
                    ->orWhere('sku', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get(['id', 'product_id', 'sku']);

        return response()->json([
            'results' => $rows->map(fn($v) => [
                'id' => $v->id,
                'text' => trim(optional($v->product)->name_en . ' — ' . $v->sku, ' —'),
            ])->values(),
        ]);
    }

    public function searchCategories(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $rows = Category::query()
            ->when($q !== '', fn($query) => $query->where('name_en', 'like', "%{$q}%"))
            ->limit(20)
            ->get(['id', 'name_en']);

        return response()->json([
            'results' => $rows->map(fn($c) => ['id' => $c->id, 'text' => $c->name_en])->values(),
        ]);
    }

    public function searchCustomPages(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $rows = \App\Models\CustomPage::query()
            ->where('is_active', true)
            ->when($q !== '', fn ($query) => $query->where('name_en', 'like', "%{$q}%"))
            ->limit(20)
            ->get(['id', 'name_en']);

        return response()->json([
            'results' => $rows->map(fn ($p) => ['id' => $p->id, 'text' => $p->name_en])->values(),
        ]);
    }

    public function searchBrands(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $brands = Brand::where('is_active', true)
            ->whereNull('deleted_at')
            ->when($q !== '', fn ($query) =>
                $query->where('name_en', 'like', "%{$q}%")
                      ->orWhere('name_ar', 'like', "%{$q}%")
            )
            ->orderBy('name_en')
            ->limit(20)
            ->get(['id', 'name_en', 'name_ar', 'logo_media_id']);

        return response()->json([
            'results' => $brands->map(fn ($b) => [
                'id'   => $b->id,
                'text' => $b->name_en . ($b->name_ar ? ' — ' . $b->name_ar : ''),
            ]),
        ]);
    }

    public function searchVendors(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $rows = Vendor::query()
            ->when($q !== '', fn($query) => $query->where('store_name', 'like', "%{$q}%"))
            ->limit(20)
            ->get(['id', 'store_name', 'store_slug']);

        return response()->json([
            'results' => $rows->map(fn($v) => [
                'id' => $v->id,
                'text' => $v->store_name,
                'store_slug' => $v->store_slug,
            ])->values(),
        ]);
    }

    public function searchFlashSales(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $rows = FlashSale::query()
            ->whereIn('status', ['submission_open', 'approved', 'live'])
            ->when($q !== '', fn($query) => $query->where('name_en', 'like', "%{$q}%"))
            ->limit(20)
            ->get(['id', 'name_en', 'name_ar', 'sale_starts_at', 'status']);

        return response()->json([
            'results' => $rows->map(fn($fs) => [
                'id' => $fs->id,
                'text' => $fs->name_en,
                'name_ar' => $fs->name_ar,
                'sale_starts_at' => optional($fs->sale_starts_at)->format('M d, Y'),
                'status' => $fs->status?->value,
            ])->values(),
        ]);
    }

    public function searchVendorListings(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $rows = \App\Models\VendorListing::query()
            ->with(['vendor:id,store_name', 'productVariant:id,product_id,sku', 'productVariant.product:id,name_en'])
            ->where('status', \App\Enums\VendorListingStatus::Active)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->whereHas('productVariant.product', fn($p) => $p->where('name_en', 'like', "%{$q}%"))
                        ->orWhereHas('vendor', fn($v) => $v->where('store_name', 'like', "%{$q}%"))
                        ->orWhereHas('productVariant', fn($pv) => $pv->where('sku', 'like', "%{$q}%"));
                });
            })
            ->limit(20)
            ->get();

        return response()->json([
            'results' => $rows->map(fn($vl) => [
                'id' => $vl->id,
                'text' => trim(
                    optional(optional($vl->productVariant)->product)->name_en
                    . ' — ' . optional($vl->vendor)->store_name
                    . ' — ' . number_format((float) $vl->price, 2) . ' ' . $vl->currency,
                    ' —'
                ),
            ])->values(),
        ]);
    }

    public function searchAdminListings(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $rows = \App\Models\AdminListing::query()
            ->join('product_variants as pv', 'pv.id', '=', 'admin_listings.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('admin_listings.status', 'active')
            ->whereNull('admin_listings.deleted_at')
            ->when($q !== '', fn($query) => $query->where(function ($inner) use ($q) {
                $inner->where('p.name_en', 'like', "%{$q}%")
                      ->orWhere('pv.sku', 'like', "%{$q}%");
            }))
            ->limit(20)
            ->get([
                'admin_listings.id',
                'admin_listings.price',
                'admin_listings.currency',
                'p.name_en',
            ]);

        return response()->json([
            'results' => $rows->map(fn($al) => [
                'id'   => $al->id,
                'text' => $al->name_en . ' (Platform) — ' . number_format((float) $al->price, 2) . ' ' . $al->currency,
            ])->values(),
        ]);
    }

    public function reorderAdImages(Request $request, PageBlock $block)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|uuid',
            'items.*.position' => 'required|integer|min:0',
        ]);

        $this->service->reorderAdImages($data['items']);
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Block products
    // ─────────────────────────────────────────────────────────────────────

    public function getBlockProducts(Request $request, PageBlock $block)
    {
        $tabIndex = $request->query('tab_index');

        $items = $block->blockProducts()
            ->when($tabIndex !== null, fn ($q) => $q->where('tab_index', (int) $tabIndex))
            ->orderBy('position')
            ->with('productVariant.product:id,name_en')
            ->get();

        return response()->json([
            'results' => $items->map(fn($item) => [
                'id' => $item->id,
                'product_variant_id' => $item->product_variant_id,
                'position' => $item->position,
                'text' => trim(optional(optional($item->productVariant)->product)->name_en . ' — ' . optional($item->productVariant)->sku, ' —'),
            ])->values(),
        ]);
    }

    public function addBlockProduct(Request $request, PageBlock $block)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'product_variant_id' => 'required|uuid|exists:product_variants,id',
            'tab_index' => 'nullable|integer|min:0',
        ]);

        $item = $this->service->addBlockProduct($block, $data['product_variant_id'], $this->admin(), (int) ($data['tab_index'] ?? 0));
        return response()->json(['item' => $item]);
    }

    public function removeBlockProduct(PageBlockProduct $blockProduct)
    {
        $this->authorizeManage();
        $blockProduct->delete();
        return response()->json(['success' => true]);
    }

    public function reorderBlockProducts(Request $request, PageBlock $block)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|uuid',
            'products.*.position' => 'required|integer|min:0',
        ]);

        $this->service->reorderBlockProducts($data['products']);
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Block categories (category_pills)
    // ─────────────────────────────────────────────────────────────────────

    public function getBlockCategories(PageBlock $block)
    {
        $items = $block->blockCategories()->with('category:id,name_en')->get();

        return response()->json([
            'results' => $items->map(fn($item) => [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'position' => $item->position,
                'text' => optional($item->category)->name_en,
            ])->values(),
        ]);
    }

    public function addBlockCategory(Request $request, PageBlock $block)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'category_id' => 'required|uuid|exists:categories,id',
        ]);

        $item = $this->service->addBlockCategory($block, $data['category_id']);
        return response()->json(['item' => $item]);
    }

    public function removeBlockCategory(\App\Models\PageBlockCategory $blockCategory)
    {
        $this->authorizeManage();
        $blockCategory->delete();
        return response()->json(['success' => true]);
    }

    public function reorderBlockCategories(Request $request, PageBlock $block)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|uuid',
            'categories.*.position' => 'required|integer|min:0',
        ]);

        $this->service->reorderBlockCategories($data['categories']);
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Block sellers (brand_strip)
    // ─────────────────────────────────────────────────────────────────────

    public function getBlockSellers(PageBlock $block)
    {
        $items = $block->blockSellers()->with('seller:id,store_name')->get();

        return response()->json([
            'results' => $items->map(fn($item) => [
                'id' => $item->id,
                'seller_id' => $item->seller_id,
                'position' => $item->position,
                'text' => optional($item->seller)->store_name,
            ])->values(),
        ]);
    }

    public function addBlockSeller(Request $request, PageBlock $block)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'seller_id' => 'required|uuid|exists:vendors,id',
        ]);

        $item = $this->service->addBlockSeller($block, $data['seller_id']);
        return response()->json(['item' => $item]);
    }

    public function removeBlockSeller(\App\Models\PageBlockSeller $blockSeller)
    {
        $this->authorizeManage();
        $blockSeller->delete();
        return response()->json(['success' => true]);
    }

    public function reorderBlockSellers(Request $request, PageBlock $block)
    {
        $this->authorizeManage();

        $data = $request->validate([
            'sellers' => 'required|array',
            'sellers.*.id' => 'required|uuid',
            'sellers.*.position' => 'required|integer|min:0',
        ]);

        $this->service->reorderBlockSellers($data['sellers']);
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Brand Strip — Brands
    // ─────────────────────────────────────────────────────────────────────

    public function loadBlockBrands(PageBlock $block): \Illuminate\Http\JsonResponse
    {
        $this->authorizeManage();

        $items = PageBlockBrand::where('page_block_id', $block->id)
            ->orderBy('position')
            ->with('brand')
            ->get()
            ->map(fn ($pb) => [
                'id'   => $pb->id,
                'text' => $pb->brand?->name_en . ($pb->brand?->name_ar ? ' — ' . $pb->brand?->name_ar : ''),
            ]);

        return response()->json(['results' => $items]);
    }

    public function addBlockBrand(Request $request, PageBlock $block): \Illuminate\Http\JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'brand_id' => ['required', 'uuid', 'exists:brands,id'],
        ]);

        $exists = PageBlockBrand::where('page_block_id', $block->id)
            ->where('brand_id', $data['brand_id'])
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Brand already added.'], 422);
        }

        $maxPos = PageBlockBrand::where('page_block_id', $block->id)->max('position') ?? -1;

        $item = PageBlockBrand::create([
            'page_block_id' => $block->id,
            'brand_id'      => $data['brand_id'],
            'position'      => $maxPos + 1,
        ]);

        $brand = Brand::find($data['brand_id']);

        return response()->json([
            'item' => [
                'id'       => $item->id,
                'brand_id' => $brand->id,
                'name'     => $brand->name_en,
                'name_ar'  => $brand->name_ar,
                'logo_url' => $brand->logo_url,
                'position' => $item->position,
            ],
        ]);
    }

    public function removeBlockBrand(PageBlockBrand $blockBrand): \Illuminate\Http\JsonResponse
    {
        $this->authorizeManage();
        $blockBrand->delete();
        return response()->json(['success' => true]);
    }

    public function reorderBlockBrands(Request $request, PageBlock $block): \Illuminate\Http\JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'brands'            => ['required', 'array'],
            'brands.*.id'       => ['required', 'uuid'],
            'brands.*.position' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['brands'] as $item) {
            PageBlockBrand::where('id', $item['id'])
                ->where('page_block_id', $block->id)
                ->update(['position' => $item['position']]);
        }

        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    public function uploadSlideImage(Request $request)
    {
        $this->authorizeManage();

        $request->validate([
            'image' => ['required', 'image', 'max:8192'],
            'slot' => ['required', 'in:desktop,mobile'],
        ]);

        $uploaded = $request->file('image');
        $slot = $request->input('slot');
        $ext = $uploaded->getClientOriginalExtension() ?: $uploaded->guessExtension();
        $path = $uploaded->storeAs(
            'page-builder/slides',
            Str::random(16) . '_' . $slot . '.' . $ext,
            'public'
        );

        $file = File::create([
            'key' => 'page-builder/slides/' . basename($path),
            'path' => $path,
            'storage_type' => 'public',
            'file_type' => 'slide_' . $slot,
            'mime_type' => $uploaded->getMimeType(),
            'extension' => $ext,
            'size' => $uploaded->getSize(),
        ]);

        return response()->json([
            'file_id' => $file->id,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function uploadSectionBackgroundImage(Request $request)
    {
        $this->authorizeManage();

        $request->validate([
            'image' => ['required', 'image', 'max:8192'],
        ]);

        $uploaded = $request->file('image');
        $ext = $uploaded->getClientOriginalExtension() ?: $uploaded->guessExtension();
        $path = $uploaded->storeAs(
            'page-builder/section-backgrounds',
            Str::random(16) . '.' . $ext,
            'public'
        );

        $file = File::create([
            'key' => 'page-builder/section-backgrounds/' . basename($path),
            'path' => $path,
            'storage_type' => 'public',
            'file_type' => 'section_background',
            'mime_type' => $uploaded->getMimeType(),
            'extension' => $ext,
            'size' => $uploaded->getSize(),
        ]);

        return response()->json([
            'file_id' => $file->id,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function uploadPromoTileImage(Request $request)
    {
        $this->authorizeManage();

        $request->validate([
            'image' => ['required', 'image', 'max:8192'],
        ]);

        $uploaded = $request->file('image');
        $ext = $uploaded->getClientOriginalExtension() ?: $uploaded->guessExtension();
        $path = $uploaded->storeAs(
            'page-builder/promo-tiles',
            Str::random(16) . '.' . $ext,
            'public'
        );

        $file = File::create([
            'key' => 'page-builder/promo-tiles/' . basename($path),
            'path' => $path,
            'storage_type' => 'public',
            'file_type' => 'promo_tile',
            'mime_type' => $uploaded->getMimeType(),
            'extension' => $ext,
            'size' => $uploaded->getSize(),
        ]);

        return response()->json([
            'file_id' => $file->id,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function uploadAdImage(Request $request)
    {
        $this->authorizeManage();

        $request->validate([
            'image' => ['required', 'image', 'max:8192'],
        ]);

        $uploaded = $request->file('image');
        $ext = $uploaded->getClientOriginalExtension() ?: $uploaded->guessExtension();
        $path = $uploaded->storeAs(
            'page-builder/ad-images',
            Str::random(16) . '.' . $ext,
            'public'
        );

        $file = File::create([
            'key' => 'page-builder/ad-images/' . basename($path),
            'path' => $path,
            'storage_type' => 'public',
            'file_type' => 'ad_image',
            'mime_type' => $uploaded->getMimeType(),
            'extension' => $ext,
            'size' => $uploaded->getSize(),
        ]);

        return response()->json([
            'file_id' => $file->id,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function admin()
    {
        return auth('admin')->user();
    }

    private function authorizeManage(): void
    {
        $admin = $this->admin();
        abort_unless($admin && $admin->hasPermissionTo('pages.manage'), 403, 'You do not have permission to manage pages.');
    }
}
