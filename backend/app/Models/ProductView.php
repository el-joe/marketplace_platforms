<?php

namespace App\Models;

use App\Enums\ProductViewSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductView extends Model
{
    use HasUuids;
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'customer_id',
        'session_id',
        'source',
        'referrer_url',
        'created_at',
    ];

    protected $casts = [
        'source' => ProductViewSource::class,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
