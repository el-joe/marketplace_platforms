<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorListingAddonOption extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'extra_price' => 'integer',
        'is_default' => 'boolean',
        'position' => 'integer',
    ];

    protected $fillable = [
        'id',
        'addon_group_id',
        'name_en',
        'name_ar',
        'extra_price',
        'is_default',
        'position',
    ];

    public function addonGroup(): BelongsTo
    {
        return $this->belongsTo(VendorListingAddonGroup::class, 'addon_group_id');
    }
}
