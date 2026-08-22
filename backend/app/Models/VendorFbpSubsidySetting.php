<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class VendorFbpSubsidySetting extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'admin_subsidy_cents',
        'full_coverage_threshold_cents',
        'exceptional_zone_shipping_zone_ids',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'exceptional_zone_shipping_zone_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
