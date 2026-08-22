<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'path',
        'disk',
        'mime_type',
        'size_bytes',
        'alt_text_en',
        'alt_text_ar',
        'position',
        'is_primary',
    ];

    protected $casts = [
        'position' => 'integer',
        'size_bytes' => 'integer',
        'is_primary' => 'boolean',
    ];

    protected $appends = ['url'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function hash(): HasOne
    {
        return $this->hasOne(ProductImageHash::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk ?? 'public')->url($this->path);
    }
}
