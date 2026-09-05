<?php

namespace App\Services;

use App\Models\AdImageItem;
use App\Models\Admin;
use App\Models\AdminListing;
use App\Models\BlockType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Country;
use App\Models\CustomPage;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageBlockCategory;
use App\Models\PageBlockProduct;
use App\Models\PageBlockRevision;
use App\Models\PageBlockSeller;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\SliderSlide;
use App\Models\Vendor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PageBuilderService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Pages
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Page + ordered blocks (with block_type details) as plain arrays.
     */
    public function getPageWithBlocks(string $pageId): array
    {
        $page = Page::with(['country', 'appContext', 'homeFor.appContext', 'homeFor.country'])->findOrFail($pageId);

        $blocks = PageBlock::with('blockType')
            ->where('page_id', $pageId)
            ->orderBy('position')
            ->get()
            ->map(fn(PageBlock $b) => $this->serializeBlock($b))
            ->all();

        $sections = PageSection::where('page_id', $pageId)
            ->orderBy('position')
            ->get()
            ->map(fn(PageSection $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'name_en' => $s->name_en,
                'name_ar' => $s->name_ar,
                'position' => (int) $s->position,
                'is_visible' => (bool) $s->is_visible,
                'background_color' => $s->background_color,
                'background_image_url' => $s->background_image_url,
                'background_image_url_en' => $s->background_image_url_en,
                'background_image_url_ar' => $s->background_image_url_ar,
                'padding_top' => $s->padding_top !== null ? (int) $s->padding_top : null,
                'padding_bottom' => $s->padding_bottom !== null ? (int) $s->padding_bottom : null,
                'max_width' => $s->max_width,
                'layout' => $s->layout,
                'columns_config' => $s->columns_config,
            ])
            ->all();

        return [
            'page' => [
                'id' => $page->id,
                'name' => $page->name,
                'slug' => $page->slug,
                'page_type' => $page->page_type,
                'country_id' => $page->country_id,
                'country_code' => optional($page->country)->site_code,
                'country_name' => optional($page->country)->name_en,
                'reference_id' => $page->reference_id,
                'reference_name' => $this->resolveReferenceName($page->page_type, $page->reference_id),
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'status' => $page->status?->value,
                'version' => $page->version,
                'published_at' => optional($page->published_at)->toIso8601String(),
                'app_context_key' => $page->app_context_key,
                'app_context_name' => optional($page->appContext)->name_en,
                'app_context_color' => optional($page->appContext)->color_hex,
                'home_for' => $page->homeFor->map(fn($assignment) => [
                    'context_name' => optional($assignment->appContext)->name_en,
                    'country_name' => optional($assignment->country)->name_en,
                ])->values(),
            ],
            'blocks' => $blocks,
            'sections' => $sections,
        ];
    }

    /**
     * Display name for a page's reference entity (category/brand/vendor/custom page),
     * used to pre-fill the async-select in the admin edit-page modal.
     */
    private function resolveReferenceName(string $pageType, ?string $referenceId): ?string
    {
        if (!$referenceId) {
            return null;
        }

        return match ($pageType) {
            'category' => Category::whereKey($referenceId)->value('name_en'),
            'brand' => Brand::whereKey($referenceId)->value('name_en'),
            'vendor' => Vendor::whereKey($referenceId)->value('store_name'),
            'custom_page' => CustomPage::whereKey($referenceId)->value('name_en'),
            default => null,
        };
    }

    public function createPage(array $data, Admin $admin): Page
    {
        return Page::create([
            'country_id' => $data['country_id'] ?? null,
            'page_type' => $data['page_type'],
            'app_context_key' => $data['app_context_key'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'name' => $data['name'],
            'slug' => $this->generatePageSlug($data['page_type'], $data['reference_id'] ?? null, $data['country_id']),
            'status' => 'draft',
            'version' => 1,
            'is_default' => false,
            'last_edited_by_admin_id' => $admin->id,
        ]);
    }

    private function generatePageSlug(string $pageType, ?string $referenceId, string $countryId): string
    {
        $base = match ($pageType) {
            'home' => 'home',
            'category' => Category::whereKey($referenceId)->value('slug') ?? 'category',
            'brand' => Brand::whereKey($referenceId)->value('slug') ?? 'brand',
            'vendor' => Vendor::whereKey($referenceId)->value('store_slug') ?? 'vendor',
            'custom_page' => \App\Models\CustomPage::whereKey($referenceId)->first()?->slugRecord?->slug_url ?? 'custom-page',
            default => Str::slug($pageType),
        };

        $slug = $base;
        $suffix = 1;

        while (Page::where('country_id', $countryId)->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Blocks
    // ─────────────────────────────────────────────────────────────────────────

    public function addBlock(Page $page, string $blockTypeCode, int $position, Admin $admin, ?string $sectionId = null, ?int $columnIndex = null): PageBlock
    {
        $type = BlockType::where('code', $blockTypeCode)->where('is_active', true)->first();
        if (!$type) {
            throw ValidationException::withMessages(['block_type_code' => 'Unknown or inactive block type.']);
        }

        if ($type->requires_permission && !$admin->hasPermissionTo($type->requires_permission)) {
            throw ValidationException::withMessages(['block_type_code' => 'You do not have permission to add this block type.']);
        }

        $block = DB::transaction(function () use ($page, $type, $position, $admin, $sectionId, $columnIndex) {
            $block = PageBlock::create([
                'page_id' => $page->id,
                'block_type' => $type->code,
                'position' => $position,
                'section_id' => $sectionId,
                'column_index' => $columnIndex ?? 0,
                'config' => $type->default_config ?? [],
                'is_visible' => true,
                'device_target' => 'all',
                'audience' => 'all',
                'cache_ttl_seconds' => 300,
                'created_by_admin_id' => $admin->id,
            ]);

            $this->writeBlockRevision($block, $admin, 'created');
            $this->touchPage($page, $admin);

            return $block;
        });

        return $block->load('blockType');
    }

    public function updateBlockConfig(PageBlock $block, array $config, string $changeType, Admin $admin): int
    {
        return DB::transaction(function () use ($block, $config, $changeType, $admin) {
            $block->config = $config;
            $block->updated_by_admin_id = $admin->id;
            $block->save();

            $rev = $this->writeBlockRevision($block, $admin, $changeType ?: 'config_updated');
            $this->touchPage($block->page, $admin);

            return $rev->revision_number;
        });
    }

    public function updateBlockVisibility(PageBlock $block, array $data, Admin $admin): int
    {
        return DB::transaction(function () use ($block, $data, $admin) {
            $isVisible = in_array($data['is_visible'], ['true', '1'], true);
            $block->fill([
                'is_visible' => $isVisible ?? $block->is_visible,
                'visible_from' => $data['visible_from'] ?? null,
                'visible_until' => $data['visible_until'] ?? null,
                'device_target' => $data['device_target'] ?? $block->device_target,
                'audience' => $data['audience'] ?? $block->audience,
                'updated_by_admin_id' => $admin->id,
            ])->save();

            $rev = $this->writeBlockRevision($block, $admin, 'visibility_changed');
            $this->touchPage($block->page, $admin);

            return $rev->revision_number;
        });
    }

    public function removeBlock(PageBlock $block): void
    {
        $block->delete();
    }

    public function reorderBlocks(array $orderedBlocks): void
    {
        DB::transaction(function () use ($orderedBlocks) {
            foreach ($orderedBlocks as $item) {
                if (!isset($item['id'], $item['position'])) {
                    continue;
                }
                $update = ['position' => (int) $item['position']];
                if (array_key_exists('section_id', $item)) {
                    $update['section_id'] = $item['section_id'] ?: null;
                }
                PageBlock::whereKey($item['id'])->update($update);
            }
        });
    }

    public function reorderSections(array $orderedSections): void
    {
        DB::transaction(function () use ($orderedSections) {
            foreach ($orderedSections as $item) {
                if (!isset($item['id'], $item['position'])) {
                    continue;
                }
                PageSection::whereKey($item['id'])->update(['position' => (int) $item['position']]);
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Publish / revisions
    // ─────────────────────────────────────────────────────────────────────────

    public function publishPage(Page $page, Admin $admin, string $reason = ''): void
    {
        DB::transaction(function () use ($page, $admin, $reason) {
            $blocks = PageBlock::with(['blockType', 'slides', 'adImageItems', 'blockProducts', 'blockCategories'])
                ->where('page_id', $page->id)
                ->orderBy('position')
                ->get();

            if ($blocks->isEmpty()) {
                throw ValidationException::withMessages(['page' => 'Cannot publish an empty page. Add at least one block first.']);
            }

            $page->status = 'published';
            $page->published_at = now();
            $page->published_by_admin_id = $admin->id;
            $page->version = (int) $page->version + 1;

            // Demote any other default page for the same (country, type, ref).
            Page::where('id', '!=', $page->id)
                ->where('country_id', $page->country_id)
                ->where('page_type', $page->page_type)
                ->where('reference_id', $page->reference_id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $page->is_default = true;
            $page->save();

            $sections = PageSection::where('page_id', $page->id)->orderBy('position')->get();

            PageRevision::create([
                'page_id' => $page->id,
                'version' => $page->version,
                'blocks_snapshot' => $blocks->map(fn(PageBlock $b) => $this->serializeBlock($b, true))->all(),
                'sections_snapshot' => $sections->map(fn(PageSection $s) => $this->serializeSection($s))->all(),
                'published_by_admin_id' => $admin->id,
                'publish_reason' => $reason !== '' ? $reason : null,
            ]);

            $this->flushPageCache($page);
        });
    }

    public function restoreRevision(PageRevision $revision, Admin $admin): void
    {
        DB::transaction(function () use ($revision, $admin) {
            $page = $revision->page()->firstOrFail();

            // Soft-delete current sections and blocks.
            PageSection::where('page_id', $page->id)->delete();
            PageBlock::where('page_id', $page->id)->delete();

            $sectionsSnapshot = is_array($revision->sections_snapshot) ? $revision->sections_snapshot : [];
            $sectionIdMap = [];
            foreach ($sectionsSnapshot as $sectionData) {
                $newSection = PageSection::create([
                    'page_id' => $page->id,
                    'name' => $sectionData['name'] ?? null,
                    'name_en' => $sectionData['name_en'] ?? $sectionData['name'] ?? null,
                    'name_ar' => $sectionData['name_ar'] ?? $sectionData['name'] ?? null,
                    'position' => $sectionData['position'] ?? 0,
                    'is_visible' => $sectionData['is_visible'] ?? true,
                    'background_color' => $sectionData['background_color'] ?? null,
                    'background_image_url' => $sectionData['background_image_url'] ?? null,
                    'background_image_url_en' => $sectionData['background_image_url_en'] ?? $sectionData['background_image_url'] ?? null,
                    'background_image_url_ar' => $sectionData['background_image_url_ar'] ?? $sectionData['background_image_url'] ?? null,
                    'padding_top' => $sectionData['padding_top'] ?? 0,
                    'padding_bottom' => $sectionData['padding_bottom'] ?? 0,
                    'max_width' => $sectionData['max_width'] ?? null,
                    'layout' => $sectionData['layout'] ?? null,
                    'columns_config' => $sectionData['columns_config'] ?? null,
                ]);

                if (isset($sectionData['id'])) {
                    $sectionIdMap[$sectionData['id']] = $newSection->id;
                }
            }

            $snapshot = is_array($revision->blocks_snapshot) ? $revision->blocks_snapshot : [];
            foreach ($snapshot as $idx => $blockData) {
                $oldSectionId = $blockData['section_id'] ?? null;

                PageBlock::create([
                    'page_id' => $page->id,
                    'section_id' => $oldSectionId !== null ? ($sectionIdMap[$oldSectionId] ?? null) : null,
                    'block_type' => $blockData['block_type'] ?? 'text_block',
                    'position' => $blockData['position'] ?? $idx,
                    'config' => $blockData['config'] ?? [],
                    'is_visible' => $blockData['is_visible'] ?? true,
                    'visible_from' => $blockData['visible_from'] ?? null,
                    'visible_until' => $blockData['visible_until'] ?? null,
                    'device_target' => $blockData['device_target'] ?? 'all',
                    'audience' => $blockData['audience'] ?? 'all',
                    'cache_ttl_seconds' => $blockData['cache_ttl_seconds'] ?? 300,
                    'created_by_admin_id' => $admin->id,
                ]);
            }

            $page->version = (int) $page->version + 1;
            $page->last_edited_by_admin_id = $admin->id;
            $page->save();

            PageRevision::create([
                'page_id' => $page->id,
                'version' => $page->version,
                'blocks_snapshot' => $snapshot,
                'sections_snapshot' => $sectionsSnapshot,
                'published_by_admin_id' => $admin->id,
                'publish_reason' => 'Restored from version ' . $revision->version,
            ]);

            $this->flushPageCache($page);
        });
    }

    public function restoreBlockRevision(PageBlockRevision $revision, Admin $admin): void
    {
        DB::transaction(function () use ($revision, $admin) {
            $block = $revision->pageBlock()->firstOrFail();
            $block->config = $revision->config_snapshot ?? [];
            $block->is_visible = (bool) $revision->is_visible_snapshot;
            $block->position = (int) $revision->position_snapshot;
            $block->updated_by_admin_id = $admin->id;
            $block->save();

            $this->writeBlockRevision($block, $admin, 'config_updated', 'Restored from revision #' . $revision->revision_number);
            $this->touchPage($block->page, $admin);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────────────────────────────────────

    private function writeBlockRevision(PageBlock $block, Admin $admin, string $changeType, ?string $reason = null): PageBlockRevision
    {
        $next = (int) PageBlockRevision::where('page_block_id', $block->id)->max('revision_number') + 1;

        return PageBlockRevision::create([
            'page_block_id' => $block->id,
            'page_id' => $block->page_id,
            'revision_number' => $next,
            'config_snapshot' => $block->config ?? [],
            'is_visible_snapshot' => (bool) $block->is_visible,
            'position_snapshot' => (int) $block->position,
            'changed_by_admin_id' => $admin->id,
            'change_reason' => $reason,
            'change_type' => $changeType,
        ]);
    }

    private function touchPage(?Page $page, Admin $admin): void
    {
        if (!$page) {
            return;
        }
        $page->forceFill(['last_edited_by_admin_id' => $admin->id])->save();
    }

    private function serializeBlock(PageBlock $block, bool $forSnapshot = false): array
    {
        $base = [
            'id' => $block->id,
            'block_type' => $block->block_type,
            'section_id' => $block->section_id,
            'column_index' => $block->column_index !== null ? (int) $block->column_index : null,
            'position' => (int) $block->position,
            'config' => $block->config ?? [],
            'is_visible' => (bool) $block->is_visible,
            'visible_from' => optional($block->visible_from)->toIso8601String(),
            'visible_until' => optional($block->visible_until)->toIso8601String(),
            'device_target' => $block->device_target,
            'audience' => $block->audience,
            'cache_ttl_seconds' => (int) $block->cache_ttl_seconds,
        ];

        if (!$forSnapshot) {
            $base['label_en'] = optional($block->blockType)->label_en ?? $block->block_type;
            $base['icon'] = optional($block->blockType)->icon ?? 'cube';
            $base['preview_text'] = $block->getPreviewText();
        }

        return $base;
    }

    private function serializeSection(PageSection $section): array
    {
        return [
            'id' => $section->id,
            'name' => $section->name,
            'name_en' => $section->name_en,
            'name_ar' => $section->name_ar,
            'position' => (int) $section->position,
            'is_visible' => (bool) $section->is_visible,
            'background_color' => $section->background_color,
            'background_image_url' => $section->background_image_url,
            'background_image_url_en' => $section->background_image_url_en,
            'background_image_url_ar' => $section->background_image_url_ar,
            'padding_top' => (int) $section->padding_top,
            'padding_bottom' => (int) $section->padding_bottom,
            'max_width' => $section->max_width,
            'layout' => $section->layout,
            'columns_config' => $section->columns_config,
        ];
    }

    private function flushPageCache(Page $page): void
    {
        $countryCode = optional($page->country()->first())->site_code ?? 'all';

        try {
            Cache::tags(['pages', "page:{$countryCode}:{$page->slug}"])->flush();
        } catch (\Throwable $e) {
            // Cache store doesn't support tags (e.g. file driver) — fall back to no-op.
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Slides
    // ─────────────────────────────────────────────────────────────────────────

    public function saveSlide(PageBlock $block, ?string $slideId, array $data): SliderSlide
    {
        $payload = [
            'page_block_id' => $block->id,
            'position' => (int) ($data['position'] ?? SliderSlide::where('page_block_id', $block->id)->max('position') + 1),
            'desktop_file_id_en' => $data['desktop_file_id_en'] ?? null,
            'desktop_file_id_ar' => $data['desktop_file_id_ar'] ?? null,
            'mobile_file_id_en' => $data['mobile_file_id_en'] ?? null,
            'mobile_file_id_ar' => $data['mobile_file_id_ar'] ?? null,
            // Legacy device-only columns kept in sync with the EN slot for any code
            // not yet migrated to read the language-split columns.
            'desktop_file_id' => $data['desktop_file_id_en'] ?? null,
            'mobile_file_id' => $data['mobile_file_id_en'] ?? null,
            'title_en' => $data['title_en'] ?? null,
            'title_ar' => $data['title_ar'] ?? null,
            'subtitle_en' => $data['subtitle_en'] ?? null,
            'subtitle_ar' => $data['subtitle_ar'] ?? null,
            'cta_label_en' => $data['cta_label_en'] ?? null,
            'cta_label_ar' => $data['cta_label_ar'] ?? null,
            'cta_url' => $data['cta_url'] ?? null,
            'cta_open_new_tab' => (bool) ($data['cta_open_new_tab'] ?? false),
            'text_color' => $data['text_color'] ?? '#ffffff',
            'text_position' => $data['text_position'] ?? 'left',
            'overlay_opacity' => $data['overlay_opacity'] ?? 0.30,
            'link_type' => $data['link_type'] ?? null,
            'link_reference_id' => $data['link_reference_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_paid' => (bool) ($data['is_paid'] ?? false),
            'visible_from' => $data['visible_from'] ?? null,
            'visible_until' => $data['visible_until'] ?? null,
        ];

        if ($slideId) {
            $slide = SliderSlide::where('page_block_id', $block->id)->findOrFail($slideId);
            $slide->update($payload);
            return $slide;
        }

        return SliderSlide::create($payload);
    }

    public function reorderSlides(array $ordered): void
    {
        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $row) {
                SliderSlide::whereKey($row['id'])->update(['position' => (int) $row['position']]);
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ad images
    // ─────────────────────────────────────────────────────────────────────────

    public function saveAdImage(PageBlock $block, ?string $itemId, array $data): AdImageItem
    {
        $payload = [
            'page_block_id' => $block->id,
            'position' => (int) ($data['position'] ?? AdImageItem::where('page_block_id', $block->id)->max('position') + 1),
            'file_id_en' => $data['file_id_en'] ?? null,
            'file_id_ar' => $data['file_id_ar'] ?? null,
            // Legacy single-image column kept in sync with the EN slot.
            'file_id' => $data['file_id_en'] ?? $data['file_id_ar'] ?? null,
            'title_en' => $data['title_en'] ?? null,
            'title_ar' => $data['title_ar'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'link_open_new_tab' => (bool) ($data['link_open_new_tab'] ?? false),
            'alt_text_en' => $data['alt_text_en'] ?? null,
            'alt_text_ar' => $data['alt_text_ar'] ?? null,
            'show_title_overlay' => (bool) ($data['show_title_overlay'] ?? true),
            'aspect_ratio' => $data['aspect_ratio'] ?? '4:3',
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_paid' => (bool) ($data['is_paid'] ?? false),
        ];

        if ($itemId) {
            $item = AdImageItem::where('page_block_id', $block->id)->findOrFail($itemId);
            $item->update($payload);
            return $item;
        }

        return AdImageItem::create($payload);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Block products
    // ─────────────────────────────────────────────────────────────────────────

    public function addBlockProduct(PageBlock $block, string $productVariantId, Admin $admin, int $tabIndex = 0): PageBlockProduct
    {
        return PageBlockProduct::firstOrCreate(
            ['page_block_id' => $block->id, 'tab_index' => $tabIndex, 'product_variant_id' => $productVariantId],
            [
                'position' => (int) PageBlockProduct::where('page_block_id', $block->id)
                    ->where('tab_index', $tabIndex)->max('position') + 1,
                'added_by_admin_id' => $admin->id,
            ]
        );
    }

    public function reorderBlockProducts(array $ordered): void
    {
        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $row) {
                PageBlockProduct::whereKey($row['id'])->update(['position' => (int) $row['position']]);
            }
        });
    }

    public function addBlockCategory(PageBlock $block, string $categoryId): PageBlockCategory
    {
        return PageBlockCategory::firstOrCreate(
            ['page_block_id' => $block->id, 'category_id' => $categoryId],
            ['position' => (int) PageBlockCategory::where('page_block_id', $block->id)->max('position') + 1]
        );
    }

    public function reorderBlockCategories(array $ordered): void
    {
        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $row) {
                PageBlockCategory::whereKey($row['id'])->update(['position' => (int) $row['position']]);
            }
        });
    }

    public function addBlockSeller(PageBlock $block, string $sellerId): PageBlockSeller
    {
        return PageBlockSeller::firstOrCreate(
            ['page_block_id' => $block->id, 'seller_id' => $sellerId],
            ['position' => (int) PageBlockSeller::where('page_block_id', $block->id)->max('position') + 1]
        );
    }

    public function reorderBlockSellers(array $ordered): void
    {
        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $row) {
                PageBlockSeller::whereKey($row['id'])->update(['position' => (int) $row['position']]);
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Page revisions
    // ─────────────────────────────────────────────────────────────────────────

    public function getPageRevisions(string $pageId): array
    {
        return PageRevision::where('page_id', $pageId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->toArray();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Block revisions
    // ─────────────────────────────────────────────────────────────────────────

    public function getBlockRevisions(string $blockId): array
    {
        return PageBlockRevision::where('page_block_id', $blockId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->toArray();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Duplicate page
    // ─────────────────────────────────────────────────────────────────────────

    public function duplicatePage(Page $page, Admin $admin): Page
    {
        return DB::transaction(function () use ($page, $admin) {
            $newPage = $page->replicate();
            $newPage->name = $page->name . ' (Copy)';
            $newPage->slug = $page->slug . '-copy-' . now()->timestamp;
            $newPage->status = 'draft';
            $newPage->version = 1;
            $newPage->is_default = false;
            $newPage->last_edited_by_admin_id = $admin->id;
            $newPage->published_at = null;
            $newPage->save();

            $sectionIdMap = [];
            foreach (PageSection::where('page_id', $page->id)->orderBy('position')->get() as $section) {
                $newSection = $section->replicate();
                $newSection->page_id = $newPage->id;
                $newSection->save();
                $sectionIdMap[$section->id] = $newSection->id;
            }

            foreach ($page->blocks as $block) {
                $newBlock = $block->replicate();
                $newBlock->page_id = $newPage->id;
                $newBlock->section_id = $block->section_id !== null ? ($sectionIdMap[$block->section_id] ?? null) : null;
                $newBlock->save();

                // Duplicate slides
                foreach ($block->slides as $slide) {
                    $newSlide = $slide->replicate();
                    $newSlide->page_block_id = $newBlock->id;
                    $newSlide->save();
                }

                // Duplicate ad images
                foreach ($block->adImageItems as $item) {
                    $newItem = $item->replicate();
                    $newItem->page_block_id = $newBlock->id;
                    $newItem->save();
                }

                // Duplicate block products
                foreach ($block->blockProducts as $prod) {
                    $newProd = $prod->replicate();
                    $newProd->page_block_id = $newBlock->id;
                    $newProd->save();
                }
            }

            return $newPage->load('blocks');
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Delete slide / ad-image
    // ─────────────────────────────────────────────────────────────────────────

    public function deleteSlide(SliderSlide $slide): void
    {
        $slide->delete();
    }

    public function deleteAdImage(AdImageItem $item): void
    {
        $item->delete();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Storefront rendering
    // ─────────────────────────────────────────────────────────────────────────

    public function renderBlock(PageBlock $block, ?Country $country = null): \Illuminate\Contracts\View\View|string
    {
        switch ($block->block_type) {
            default:
                return '';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reorder ad images
    // ─────────────────────────────────────────────────────────────────────────

    public function reorderAdImages(array $ordered): void
    {
        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $row) {
                AdImageItem::whereKey($row['id'])->update(['position' => (int) $row['position']]);
            }
        });
    }
}

