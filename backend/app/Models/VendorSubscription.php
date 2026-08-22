<?php

namespace App\Models;

use App\Enums\VendorSubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorSubscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'vendor_id',
        'plan_id',
        'status',
        'started_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'cancellation_reason',
        'auto_renew',
        'listings_used',
        'approved_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'cancelled_at' => 'datetime',
            'auto_renew' => 'boolean',
            'listings_used' => 'integer',
            'status' => VendorSubscriptionStatus::class,
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(VendorSubscriptionInvoice::class, 'subscription_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForVendor($query, string $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    // ── Accessors / Helpers ───────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === VendorSubscriptionStatus::Active
            && $this->current_period_end->isFuture();
    }

    public function daysRemaining(): int
    {
        if (!$this->current_period_end) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($this->current_period_end, false));
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            VendorSubscriptionStatus::Active => 'success',
            VendorSubscriptionStatus::Cancelled => 'danger',
            VendorSubscriptionStatus::Expired => 'secondary',
            VendorSubscriptionStatus::PastDue => 'warning',
            VendorSubscriptionStatus::Trialing => 'primary',
            default => 'secondary',
        };
    }

    public function listingsRemaining(): ?int
    {
        if (is_null($this->plan->max_listings)) {
            return null; // unlimited
        }

        return max(0, $this->plan->max_listings - $this->listings_used);
    }
}
