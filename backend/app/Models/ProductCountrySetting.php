<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCountrySetting extends Model
{
    use HasUuids;

    protected $table = 'product_country_settings';

    protected $fillable = [
        'product_id',
        'country_id',
        'is_available',
        'unavailable_reason',
        'name_override_en',
        'name_override_ar',
        'requires_local_cert',
        'seo_title',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'requires_local_cert' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
