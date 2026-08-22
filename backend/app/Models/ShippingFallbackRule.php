<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingFallbackRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'unserved_city_id',
        'fallback_shipping_company_id',
        'priority',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function unservedCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'unserved_city_id');
    }

    public function fallbackShippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class, 'fallback_shipping_company_id');
    }
}
