<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBestsellerRanking extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_id',
        'category_id',
        'country_id',
        'rank',
        'units_sold',
        'period',
        'calculated_at',
    ];

    protected $casts = [
        'rank' => 'integer',
        'units_sold' => 'integer',
        'calculated_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
