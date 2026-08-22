<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    use HasUuids;

    protected $fillable = [
        'code', 'type', 'name', 'name_ar', 'image',
        'required_fields', 'supports_webhook', 'supports_refund',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'required_fields'  => 'array',
        'supports_webhook' => 'boolean',
        'supports_refund'  => 'boolean',
        'is_active'        => 'boolean',
        'sort_order'       => 'integer',
    ];

    public function countryGateways(): HasMany
    {
        return $this->hasMany(CountryPaymentGateway::class, 'gateway_id');
    }

    /** Returns only the field keys that are marked as secret (stored encrypted). */
    public function secretFieldKeys(): array
    {
        return collect($this->required_fields ?? [])
            ->filter(fn($f) => !empty($f['secret']))
            ->pluck('key')
            ->all();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
