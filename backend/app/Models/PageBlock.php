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

    public function paidBannerBookings(): HasMany
    {
        return $this->hasMany(PaidBannerBooking::class);
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
    public function getPreviewText(): string
    {
        $cfg = $this->config ?? [];

        switch ($this->block_type) {
            case 'hero_slider':
                $count = $this->slides()->count();
                return 'Slider — ' . $count . ' slide' . ($count === 1 ? '' : 's');

            case 'product_row':
                $source = $cfg['source'] ?? 'best_sellers';
                return 'Products — source: ' . $source;

            case 'flash_sale':
                return 'Flash sale block';

            case 'text_block':
                $text = strip_tags((string) ($cfg['content_html_en'] ?? ''));
                return $text === '' ? 'Text block' : Str::limit($text, 50);

            case 'ad_images_2col':
            case 'ad_images_4col':
                $count = $this->adImageItems()->count();
                return 'Ad images — ' . $count . ' image' . ($count === 1 ? '' : 's');

            case 'full_banner':
                $count = $this->adImageItems()->count();
                return $count ? 'Banner — 1 image' : 'Banner — no image yet';

            case 'category_pills':
                return $cfg['title_en'] ?? 'Category pills';

            case 'brand_strip':
                return $cfg['title_en'] ?? 'Brand strip';

            case 'countdown_deal':
            case 'countdown_timer':
                return $cfg['title_en'] ?? 'Countdown';

            case 'newsletter_signup':
                return $cfg['title_en'] ?? 'Newsletter signup';

            case 'divider':
                return 'Divider';

            default:
                return optional($this->blockType)->label_en ?? $this->block_type;
        }
    }
}
