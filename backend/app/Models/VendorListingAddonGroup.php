<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorListingAddonGroup extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'is_required' => 'boolean',
        'position' => 'integer',
    ];

    protected $fillable = [
        'id',
        'vendor_listing_id',
        'name_en',
        'name_ar',
        'selection_type',
        'is_required',
        'position',
    ];

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(VendorListingAddonOption::class, 'addon_group_id')->orderBy('position');
    }
}
