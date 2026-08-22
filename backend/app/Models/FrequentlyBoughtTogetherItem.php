<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrequentlyBoughtTogetherItem extends Model
{
    protected $table = 'frequently_bought_together_items';

    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'product_id',
        'related_product_id',
        'position',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function relatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }
}
