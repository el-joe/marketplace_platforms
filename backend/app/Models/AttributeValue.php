<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttributeValue extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'attribute_id',
        'value_ar',
        'value_en',
        'slug',
        'color_hex',
        'sort_order',
        'swatch_image_path',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AttributeValue $attributeValue): void {
            if (! empty($attributeValue->slug)) {
                return;
            }

            $base = Str::slug($attributeValue->value_en);
            $slug = $base;
            $counter = 1;

            while (static::where('slug', $slug)->exists()) {
                $slug = "{$base}-{$counter}";
                $counter++;
            }

            $attributeValue->slug = $slug;
        });
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function productVariantAttributes(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class, 'attribute_value_id');
    }

    public function getSwatchImageUrlAttribute(): ?string
    {
        if (!$this->swatch_image_path) {
            return null;
        }

        return str_contains($this->swatch_image_path, '/') ? Storage::url($this->swatch_image_path) : $this->swatch_image_path;
    }
}
