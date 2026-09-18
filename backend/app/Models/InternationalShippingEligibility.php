<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternationalShippingEligibility extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'vendor_listing_id',
        'admin_listing_id',
        'destination_country_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function adminListing(): BelongsTo
    {
        return $this->belongsTo(AdminListing::class);
    }

    public function destinationCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'destination_country_id');
    }
}
