<?php

namespace App\Models;

use App\Enums\PaidAdAdvertiserType;
use App\Enums\PaidAdBookingStatus;
use App\Enums\PaidAdPaymentMethod;
use App\Enums\PaidAdPaymentStatus;
use DomainException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaidAdBooking extends Model
{
    use HasUuids;

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'status' => PaidAdBookingStatus::class,
        'payment_status' => PaidAdPaymentStatus::class,
        'payment_method' => PaidAdPaymentMethod::class,
        'advertiser_type' => PaidAdAdvertiserType::class,
        'booked_from' => 'date',
        'booked_until' => 'date',
        'offline_proof_uploaded_at' => 'datetime',
        'invoiced_at' => 'datetime',
        'paid_at' => 'datetime',
        'payment_due_at' => 'datetime',
        'submitted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'paused_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'approved_at' => 'datetime',
        'agreed_rate' => 'integer',
        'unit_rate' => 'integer',
        'quoted_amount' => 'integer',
        'tax_amount' => 'integer',
        'budget_amount' => 'integer',
        'total_charged' => 'integer',
        'subscription_charged' => 'integer',
        'pricing_units' => 'integer',
        'impressions_delivered' => 'integer',
        'clicks_delivered' => 'integer',
        'cpm_impressions_billed' => 'integer',
    ];

    protected $fillable = [
        'booking_reference',
        'paid_ad_slot_id',
        'advertiser_type',
        'vendor_id',
        'marketer_id',
        'country_id',
        'pricing_model',
        'pricing_units',
        'unit_rate',
        'quoted_amount',
        'tax_amount',
        'budget_amount',
        'booked_from',
        'booked_until',
        'agreed_rate',
        'currency',
        'status',
        'rejection_reason',
        'payment_status',
        'payment_method',
        'payment_transaction_id',
        'offline_proof_file_path',
        'offline_proof_uploaded_at',
        'invoiced_at',
        'paid_at',
        'payment_due_at',
        'impressions_delivered',
        'clicks_delivered',
        'cpm_impressions_billed',
        'total_charged',
        'subscription_charged',
        'submitted_at',
        'started_at',
        'completed_at',
        'paused_at',
        'approved_by_admin_id',
        'approved_at',
        'rejected_by_admin_id',
        'rejected_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'created_by_admin_id',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $booking) {
            if ($booking->advertiser_type === PaidAdAdvertiserType::Vendor) {
                if (! $booking->vendor_id || $booking->marketer_id) {
                    throw new DomainException('advertiser_type=vendor requires vendor_id set and marketer_id null.');
                }
            } elseif ($booking->advertiser_type === PaidAdAdvertiserType::Marketer) {
                if (! $booking->marketer_id || $booking->vendor_id) {
                    throw new DomainException('advertiser_type=marketer requires marketer_id set and vendor_id null.');
                }
            }
        });
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(PaidAdSlot::class, 'paid_ad_slot_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }

    public function rejectedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'rejected_by_admin_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(PaidAdCreative::class, 'paid_ad_booking_id');
    }

    public function currentCreative(): HasOne
    {
        return $this->hasOne(PaidAdCreative::class, 'paid_ad_booking_id')->where('is_current', true);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(PaidAdCharge::class, 'paid_ad_booking_id');
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(PaidAdDailyStat::class, 'paid_ad_booking_id');
    }

    public function advertiser(): Vendor|Marketer|null
    {
        return $this->advertiser_type === PaidAdAdvertiserType::Vendor
            ? $this->vendor
            : $this->marketer;
    }

    public function advertiserName(): string
    {
        return $this->advertiser_type === PaidAdAdvertiserType::Vendor
            ? (string) $this->vendor?->store_name
            : (string) $this->marketer?->name;
    }

    /** Sum of ledger entries for this booking. Detail pages only — avoid N+1 in lists. */
    public function getNetChargedAttribute(): int
    {
        return (int) $this->charges()->sum('amount');
    }

    /** Subscription fee (ex-tax) plus usage spend, base currency. */
    protected function totalSpend(): Attribute
    {
        return Attribute::get(fn () => (int) $this->subscription_charged + (int) $this->total_charged);
    }

    /** Total spend per click; null when no clicks. */
    protected function effectiveCpc(): Attribute
    {
        return Attribute::get(fn () => $this->clicks_delivered > 0
            ? (int) round($this->total_spend / $this->clicks_delivered)
            : null);
    }

    /** Total spend per 1,000 impressions; null when no impressions. */
    protected function effectiveCpm(): Attribute
    {
        return Attribute::get(fn () => $this->impressions_delivered > 0
            ? (int) round($this->total_spend / $this->impressions_delivered * 1000)
            : null);
    }
}
