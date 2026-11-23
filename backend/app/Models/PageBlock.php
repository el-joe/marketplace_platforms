<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PageBlock extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'page_id',
        'section_id',
        'block_type',
        'app_context_key',
        'position',
        'column_index',
        'config',
        'is_visible',
        'visible_from',
        'visible_until',
        'device_target',
        'audience',
        'country_override',
        'ab_test_id',
        'ab_variant',
        'cache_ttl_seconds',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'config' => 'array',
        'is_visible' => 'boolean',
        'visible_from' => 'datetime',
        'visible_until' => 'datetime',
        'cache_ttl_seconds' => 'integer',
        'position' => 'integer',
        'column_index' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(PageSection::class, 'section_id');
    }

    public function abTest(): BelongsTo
    {
        return $this->belongsTo(AbTest::class);
    }

    public function countryOverride(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_override');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageBlockRevision::class)->orderByDesc('revision_number');
    }

    public function slides(): HasMany
    {
        return $this->hasMany(SliderSlide::class)->orderBy('position');
    }

    public function adImageItems(): HasMany
    {
        return $this->hasMany(AdImageItem::class)->orderBy('position');
    }

    public function blockProducts(): HasMany
    {
        return $this->hasMany(PageBlockProduct::class)->orderBy('position');
    }

    public function blockSellers(): HasMany
    {
        return $this->hasMany(PageBlockSeller::class)->orderBy('position');
    }

    public function blockCategories(): HasMany
    {
        return $this->hasMany(PageBlockCategory::class)->orderBy('position');
    }

    public function blockBrands(): HasMany
    {
        return $this->hasMany(PageBlockBrand::class)->orderBy('position');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(BlockAnalytic::class)->orderByDesc('date');
    }

    public function blockType(): BelongsTo
    {
        return $this->belongsTo(BlockType::class, 'block_type', 'code');
    }

    /**
     * Human-readable preview text for the block card in the builder.
     */
    /**
     * One-line summary under the block name in the page-builder canvas (panel locale).
     */
    public function getPreviewText(): string
    {
        $cfg = $this->config ?? [];
        $title = fn (string $fallbackKey): string => (string) (localized($cfg, 'title') ?: __('admin.page_builder.block_preview.'.$fallbackKey));

        switch ($this->block_type) {
            case 'hero_slider':
                return trans_choice('admin.page_builder.block_preview.slides', $this->slides()->count());

            case 'product_row':
                return __('admin.page_builder.block_preview.products_source', ['source' => $cfg['source'] ?? 'best_sellers']);

            case 'flash_sale':
                return __('admin.page_builder.block_preview.flash_sale');

            case 'text_block':
                $text = strip_tags((string) localized($cfg, 'content_html'));

                return $text === '' ? __('admin.page_builder.block_preview.text_block') : Str::limit($text, 50);

            case 'ad_images_2col':
            case 'ad_images_4col':
                return trans_choice('admin.page_builder.block_preview.ad_images', $this->adImageItems()->count());

            case 'full_banner':
                return $this->adImageItems()->count()
                    ? __('admin.page_builder.block_preview.banner_one_image')
                    : __('admin.page_builder.block_preview.banner_no_image');

            case 'category_pills':
                return $title('category_pills');

            case 'brand_strip':
                return $title('brand_strip');

            case 'countdown_deal':
            case 'countdown_timer':
                return $title('countdown');

            case 'newsletter_signup':
                return $title('newsletter_signup');

            case 'divider':
                return __('admin.page_builder.block_preview.divider');

            default:
                return localized($this->blockType, 'label') ?: $this->block_type;
        }
    }
}
