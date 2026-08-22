<?php

namespace App\Models;

use App\Enums\FlashSaleStatus;
use App\Traits\HasStateMachine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSale extends Model
{
    use HasUuids, HasStateMachine;

    // ── Status constants ──────────────────────────────────────────────────────

    public const STATUS_TRANSITIONS = [
        'draft' => ['submission_open', 'cancelled'],
        'submission_open' => ['submission_closed', 'cancelled'],
        'submission_closed' => ['under_review', 'cancelled'],
        'under_review' => ['approved', 'cancelled'],
        'approved' => ['live', 'cancelled'],
        'live' => ['ended', 'cancelled'],
        'ended' => [],
        'cancelled' => [],
    ];

    public const STATUS_LABELS = [
        'draft' => 'Draft',
        'submission_open' => 'Accepting Submissions',
        'submission_closed' => 'Submissions Closed',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'live' => 'Live',
        'ended' => 'Ended',
        'cancelled' => 'Cancelled',
    ];

    // ── Fillable ──────────────────────────────────────────────────────────────
    // approved_slots_count intentionally omitted — managed by service only

    protected $fillable = [
        'country_id',
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'status',
        'submission_opens_at',
        'submission_closes_at',
        'review_deadline_at',
        'sale_starts_at',
        'sale_ends_at',
        'min_discount_pct',
        'max_products_per_seller',
        'eligible_categories',
        'eligible_seller_tiers',
        'min_seller_rating',
        'commission_override_pct',
        'is_featured',
        'is_exclusive',
        'price_drop_required',
        'max_total_slots',
        'created_by_admin_id',
        'updated_by_admin_id',
        'cancelled_at',
        'cancellation_reason',
    ];

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'eligible_categories' => 'array',
            'eligible_seller_tiers' => 'array',
            'is_featured' => 'boolean',
            'is_exclusive' => 'boolean',
            'price_drop_required' => 'boolean',
            'submission_opens_at' => 'datetime',
            'submission_closes_at' => 'datetime',
            'review_deadline_at' => 'datetime',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'min_discount_pct' => 'decimal:2',
            'commission_override_pct' => 'decimal:2',
            'status' => FlashSaleStatus::class,
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->createdBy();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->updatedBy();
    }

    public function invititions(): HasMany
    {
        return $this->hasMany(FlashSaleVendorInvitition::class);
    }

    /** @deprecated use invititions() – typo preserved for DB table compatibility */
    public function vendorInvitations(): HasMany
    {
        return $this->invititions();
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FlashSaleSubmission::class);
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(FlashSaleAnalytic::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(FlashSaleOrder::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query): void
    {
        $query->whereIn('status', ['submission_open', 'live', 'under_review', 'approved']);
    }

    public function scopeLive($query): void
    {
        $query->where('status', 'live');
    }

    public function scopeByCountry($query, string $countryId): void
    {
        $query->where('country_id', $countryId);
    }

    public function scopeUpcoming($query): void
    {
        $query->whereIn('status', ['approved', 'submission_open']);
    }

    public function scopeEnded($query): void
    {
        $query->whereIn('status', ['ended', 'cancelled']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isLive(): bool
    {
        return $this->status === FlashSaleStatus::Live;
    }

    public function isAcceptingSubmissions(): bool
    {
        return $this->status === FlashSaleStatus::SubmissionOpen
            && $this->submission_opens_at
            && $this->submission_closes_at
            && now()->between($this->submission_opens_at, $this->submission_closes_at);
    }

    public function canBeApproved(): bool
    {
        return $this->canTransitionTo('approved');
    }

    public static function getAllowedTransitions(): array
    {
        return static::STATUS_TRANSITIONS;
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            FlashSaleStatus::Draft => 'gray',
            FlashSaleStatus::SubmissionOpen => 'primary',
            FlashSaleStatus::SubmissionClosed => 'warning',
            FlashSaleStatus::UnderReview => 'info',
            FlashSaleStatus::Approved => 'success',
            FlashSaleStatus::Live => 'success',
            FlashSaleStatus::Ended => 'gray',
            FlashSaleStatus::Cancelled => 'danger',
            default => 'gray',
        };
    }

    public function getSaleDurationHoursAttribute(): float
    {
        if (!$this->sale_starts_at || !$this->sale_ends_at)
            return 0.0;
        return round($this->sale_ends_at->diffInMinutes($this->sale_starts_at) / 60, 1);
    }

    public function getSubmissionsCountAttribute(): int
    {
        return $this->submissions()->count();
    }

    public function getApprovedSubmissionsCountAttribute(): int
    {
        return $this->submissions()->where('status', 'approved')->count();
    }

    public function getTimeRemainingSecondsAttribute(): int
    {
        if (!$this->isLive() || !$this->sale_ends_at)
            return 0;
        return max(0, now()->diffInSeconds($this->sale_ends_at, false));
    }

    /** @deprecated use getSaleDurationHoursAttribute */
    public function getDurationHours(): float
    {
        return $this->getSaleDurationHoursAttribute();
    }

    /** @deprecated use getSubmissionsCountAttribute */
    public function getSubmissionCountAttribute(): int
    {
        return $this->getSubmissionsCountAttribute();
    }

    /** @deprecated use getApprovedSubmissionsCountAttribute */
    public function getApprovedCountAttribute(): int
    {
        return $this->getApprovedSubmissionsCountAttribute();
    }
}
