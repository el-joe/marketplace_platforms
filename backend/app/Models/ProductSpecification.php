<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSpecification extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'key_en',
        'key_ar',
        'value_en',
        'value_ar',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
