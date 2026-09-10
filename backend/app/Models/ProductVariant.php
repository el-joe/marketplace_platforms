<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;
    protected $fillable = [
        'product_id',
        'sku',
        'slug',
        'barcode',
        'variant_name',
        'variant_name_ar',
        'weight_grams',
        'length_cm',
        'width_cm',
        'height_cm',
        'is_default',
        'is_active',
        'position',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        $generateSlug = function (ProductVariant $variant): void {
            if (! empty($variant->slug)) {
                return;
            }

            $base = '';

            if ($variant->exists) {
                $values = $variant->variantAttributeValues()
                    ->with('attribute')
                    ->get()
                    ->sortBy(fn (AttributeValue $value) => $value->attribute?->sort_order ?? 0)
                    ->map(fn (AttributeValue $value) => $value->slug ?: Str::slug($value->value_en ?? ''))
                    ->filter()
                    ->values();

                if ($values->isNotEmpty()) {
                    $base = $values->implode('-');
                }
            }

            if ($base === '') {
                $base = Str::slug($variant->variant_name ?? $variant->sku);
            }

            $slug = $base;
            $counter = 1;

            while (
                static::where('product_id', $variant->product_id)
                    ->where('slug', $slug)
                    ->when($variant->exists, fn ($query) => $query->where('id', '!=', $variant->id))
                    ->exists()
            ) {
                $slug = "{$base}-{$counter}";
                $counter++;
            }

            $variant->slug = $slug;
        };

        static::creating($generateSlug);
        static::updating($generateSlug);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variantAttributes(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'product_variant_attributes',
            'product_variant_id',
            'attribute_value_id'
        );
    }

    public function variantAttributeValues(): HasManyThrough
    {
        return $this->hasManyThrough(
            AttributeValue::class,
            ProductVariantAttribute::class,
            'product_variant_id',
            'id',
            'id',
            'attribute_value_id'
        )->whereHas('attribute', fn ($query) => $query->where('is_variant_attribute', true));
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_variant_id')->orderBy('position');
    }

    public function vendorListings(): HasMany
    {
        return $this->hasMany(VendorListing::class);
    }

    public function adminListings(): HasMany
    {
        return $this->hasMany(AdminListing::class);
    }

    /**
     * Human-readable summary of variant attribute values, e.g. "Space Black / 256GB".
     */
    public function attributeSummary(): string
    {
        return $this->variantAttributeValues()
            ->with('attribute')
            ->get()
            ->sortBy(fn (AttributeValue $value) => $value->attribute?->sort_order ?? 0)
            ->map(fn (AttributeValue $value) => $value->value_en)
            ->filter()
            ->implode(' / ');
    }

    /**
     * Locale-aware customer-facing variant name: the product's name followed by this
     * variant's distinguishing details, e.g. "Samsung Galaxy Book4 Pro 16 Moon Gray / 512GB".
     * Falls back to the auto-generated attribute summary for variants saved before variant_name existed.
     */
    public function displayName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $product = $this->relationLoaded('product') ? $this->product : $this->product()->first();

        $productName = $locale === 'ar' ? $product?->name_ar : $product?->name_en;

        $detail = $locale === 'ar'
            ? ($this->variant_name_ar ?: ($this->variant_name ?: $this->attributeSummary()))
            : ($this->variant_name ?: $this->attributeSummary());

        return trim(collect([$productName, $detail])->filter()->implode(' '));
    }
}
