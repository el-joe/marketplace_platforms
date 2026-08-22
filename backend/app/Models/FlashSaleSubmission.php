<?php

namespace App\Models;

use App\Enums\FlashSaleSubmissionStatus;
use App\Traits\HasStateMachine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSaleSubmission extends Model
{
    use HasUuids, HasStateMachine;

    // ── State machine ─────────────────────────────────────────────────────────

    // ⚠ quantity_remaining is GENERATED VIRTUAL in MySQL — NEVER in fillable

    public const STATUS_TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['under_review', 'rejected', 'withdrawn'],
        'under_review' => ['approved', 'rejected'],
        'approved' => ['live', 'rejected'],
        'live' => ['sold_out', 'ended'],
        'sold_out' => [],
        'rejected' => ['submitted'],  // resubmit allowed (max 2x)
        'withdrawn' => [],
        'ended' => [],
    ];

    public const STATUS_LABELS = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'live' => 'Live',
        'sold_out' => 'Sold Out',
        'rejected' => 'Rejected',
        'withdrawn' => 'Withdrawn',
        'ended' => 'Ended',
    ];

    // ── Fillable ──────────────────────────────────────────────────────────────
    // quantity_remaining is intentionally ABSENT — it is a MySQL GENERATED VIRTUAL column

    protected $fillable = [
        'flash_sale_id',
        'vendor_listing_id',
        'admin_listing_id',
        'vendor_id',
        'status',
        'flash_price',
        'original_price',
        'calculated_discount_pct',
        'reference_price_30d',
        'flash_price_currency',
        'max_quantity_total',
        'max_quantity_per_customer',
        'quantity_sold',
        'rejection_reason',
        'rejection_code',
        'submitted_at',
        'reviewed_at',
        'reviewed_by_admin_id',
        'approved_at',
        'sold_out_at',
        'admin_notes',
        'vendor_notes',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'flash_price' => 'integer',
            'original_price' => 'integer',
            'reference_price_30d' => 'integer',
            'calculated_discount_pct' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'sold_out_at' => 'datetime',
            'status' => FlashSaleSubmissionStatus::class,
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class, 'vendor_listing_id');
    }

    public function adminListing(): BelongsTo
    {
        return $this->belongsTo(AdminListing::class, 'admin_listing_id');
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->reviewedByAdmin();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(FlashSaleSubmissionHistory::class, 'flash_sale_submission_id');
    }

    public function flashSaleOrders(): HasMany
    {
        return $this->hasMany(FlashSaleOrder::class);
    }

    // \u2500\u2500 Helpers \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500

    public function getFlashPriceFormattedAttribute(): string
    {
        return number_format($this->flash_price / 100, 2) . ' ' . ($this->flash_price_currency ?? '');
    }

    public function getOriginalPriceFormattedAttribute(): string
    {
        return number_format($this->original_price / 100, 2) . ' ' . ($this->flash_price_currency ?? '');
    }

    public function getDiscountSavingsAttribute(): int
    {
        return max(0, (int) $this->original_price - (int) $this->flash_price);
    }

    public function getRevenueAttribute(): int
    {
        return (int) $this->flash_price * (int) $this->quantity_sold;
    }

    public function getRevenueFormattedAttribute(): string
    {
        return number_format($this->getRevenueAttribute() / 100, 2) . ' ' . ($this->flash_price_currency ?? '');
    }

    /** Returns whichever listing FK is set (vendor_listing_id XOR admin_listing_id). */
    public function listing(): VendorListing|AdminListing|null
    {
        return $this->vendor_listing_id !== null
            ? $this->vendorListing
            : $this->adminListing;
    }

    public function getProductNameAttribute(): string
    {
        return $this->listing()?->productVariant?->product?->name_en ?? 'Unknown';
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        return $this->listing()?->productVariant?->product?->images
                ?->where('is_primary', true)->first()?->url;
    }

    public function isFakeDiscountSuspected(): bool
    {
        return (bool) cache()->remember(
            "flash_sale_submission_suspect_{$this->id}",
            now()->addMinutes(15),
            fn() => app(\App\Services\FakeDiscountDetectionService::class)->analyze($this)['is_suspect'] ?? false
        );
    }
}
