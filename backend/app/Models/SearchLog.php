<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLog extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $fillable = [
        'customer_id',
        'session_id',
        'query',
        'query_normalized',
        'filters_json',
        'results_count',
        'clicked_product_id',
        'clicked_position',
        'converted_order_id',
        'language',
        'country_id',
        'created_at',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function clickedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'clicked_product_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
