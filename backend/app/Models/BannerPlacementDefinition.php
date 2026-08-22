<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BannerPlacementDefinition extends Model
{
    use HasUuids;
    protected $fillable = [
        'code',
        'name',
        'description',
        'width_px',
        'height_px',
        'max_file_size_kb',
        'allowed_formats',
        'device_restriction',
        'max_simultaneous',
        'supports_vendor_ads',
        'base_rate_weekly',
        'is_active',
        'sort_order',
    ];

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'allowed_formats' => 'array',
        'supports_vendor_ads' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function slots(): HasMany
    {
        return $this->hasMany(PaidAdSlot::class, 'placement_definition_id');
    }
}
