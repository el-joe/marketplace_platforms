<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorAdSubscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'vendor_listing_id',
        'vendor_id',
        'ad_package_id',
        'status',
        'starts_at',
        'ends_at',
        'amount_paid',
        'currency',
        'popup_title_en',
        'popup_title_ar',
        'popup_body_en',
        'popup_body_ar',
        'popup_image_url',
        'popup_cta_url',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'amount_paid' => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function adPackage(): BelongsTo
    {
        return $this->belongsTo(AdPackage::class);
    }

    // ── Accessors / Helpers ───────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at->isFuture();
    }

    public function amountFormatted(): string
    {
        $decimals = Currency::find($this->currency)?->decimal_places ?? 2;

        return number_format($this->amount_paid / (10 ** $decimals), $decimals) . ' ' . $this->currency;
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('ends_at', '>', now());
    }

    public function scopeForVendor($query, string $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }
}
