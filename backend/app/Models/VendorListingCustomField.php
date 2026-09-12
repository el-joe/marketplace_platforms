<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorListingCustomField extends Model
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
        'label_en',
        'label_ar',
        'field_type',
        'placeholder_en',
        'placeholder_ar',
        'unit',
        'is_required',
        'position',
    ];

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }
}
