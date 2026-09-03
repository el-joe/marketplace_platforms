<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory, NodeTrait, SoftDeletes;

    // Override kalnoy/nestedset defaults (_lft/_rgt) to match actual DB columns
    public function getLftName(): string
    {
        return 'lft';
    }
    public function getRgtName(): string
    {
        return 'rgt';
    }
    public function getDepthName(): string
    {
        return 'depth';
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushNavCaches());
        static::deleted(fn () => static::flushNavCaches());
    }

    private static function flushNavCaches(): void
    {
        \App\Services\Customer\CategoryService::flushCache();
        \App\Services\Customer\UnifiedCategoryService::flushCache();
    }

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'parent_id',
        'name_ar',
        'name_en',
        'slug',
        'description_ar',
        'description_en',
        'commission_rate',
        'commission_fbp_pct',
        'commission_fbp_fixed',
        'commission_fbn_pct',
        'commission_fbn_fixed',
        'sort_order',
        'product_count',
        'is_active',
        'is_visible',
        'is_featured',
        'has_filters',
        'influencer_sample_qty',
        'affiliate_sample_qty',
        'platform_sample_qty',
        'min_stock_for_campaign',
        'seo_title_ar',
        'seo_title_en',
        'seo_description_ar',
        'seo_description_en',
    ];

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'commission_rate' => 'decimal:2',
        'commission_fbp_pct' => 'decimal:2',
        'commission_fbp_fixed' => 'integer',
        'commission_fbn_pct' => 'decimal:2',
        'commission_fbn_fixed' => 'integer',
        'sort_order' => 'integer',
        'product_count' => 'integer',
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
        'is_featured' => 'boolean',
        'has_filters' => 'boolean',
        'influencer_sample_qty' => 'integer',
        'affiliate_sample_qty' => 'integer',
        'platform_sample_qty' => 'integer',
        'min_stock_for_campaign' => 'integer',
    ];

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        return $this->{'name_' . $locale} ?? $this->name_en ?? '';
    }

    /**
     * Categories have no image column; the image is the primary (or first) File
     * attached via the polymorphic files() relation.
     */
    public function getImageUrlAttribute(): ?string
    {
        $file = $this->relationLoaded('primaryImage')
            ? $this->primaryImage
            : $this->primaryImage()->first();

        $file ??= $this->relationLoaded('files')
            ? $this->files->sortBy('position')->first()
            : $this->files()->orderBy('position')->first();

        return $file?->full_path;
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->with('children')->orderBy('sort_order');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'category_attributes')
            ->withPivot(['is_required', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    public function categoryAttributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    public function primaryImage(): MorphOne
    {
        return $this->morphOne(File::class, 'model')
            ->where('is_primary', true)
            ->orderBy('position');
    }

    /**
     * Brands with active products in this category (indirect, via products.brand_id).
     */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'products', 'category_id', 'brand_id')
            ->where('products.status', 'active')
            ->where('brands.is_active', true)
            ->distinct();
    }

    /**
     * Brands with active products in this category or any of its descendants,
     * via the nested-set lft/rgt range.
     */
    /**
     * IDs of this category's descendants, recursively, via nested-set lft/rgt range.
     *
     * @return list<string>
     */
    public function descendantIds(): array
    {
        return $this->descendants()->pluck('id')->all();
    }

    public function brandsInSubtree()
    {
        return Brand::query()
            ->whereHas('categories', fn($q) => $q->where('categories.is_active', true)->whereIn('categories.id', array_merge([$this->id], $this->descendantIds())));
    }

    public function shippingMethods(): BelongsToMany
    {
        return $this->belongsToMany(ShippingMethod::class, 'category_shipping_methods')
            ->withPivot(['is_default', 'is_available_for_express_fbn', 'is_available_for_merchant_fbp']);
    }

    public function defaultShippingMethod(): BelongsToMany
    {
        return $this->belongsToMany(ShippingMethod::class, 'category_shipping_methods')
            ->withPivot(['is_default'])
            ->wherePivot('is_default', true);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function breadcrumbPath(): array
    {
        $path = [];
        foreach ($this->ancestors()->get() as $ancestor) {
            $path[] = ['id' => $ancestor->id, 'name' => $ancestor->name_en];
        }
        $path[] = ['id' => $this->id, 'name' => $this->name_en];
        return $path;
    }
}
