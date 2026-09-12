<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSpecialRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'category_id',
        'city_id',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'budget',
        'budget_currency',
        'status',
        'brokers_notified',
    ];

    protected $casts = [
        'budget' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
