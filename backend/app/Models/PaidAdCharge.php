<?php

namespace App\Models;

use App\Enums\PaidAdAdvertiserType;
use App\Enums\PaidAdChargeType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class PaidAdCharge extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'advertiser_type' => PaidAdAdvertiserType::class,
        'type' => PaidAdChargeType::class,
        'amount' => 'integer',
        'tax_amount' => 'integer',
        'settled_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected $fillable = [
        'paid_ad_booking_id',
        'advertiser_type',
        'vendor_id',
        'marketer_id',
        'country_id',
        'currency',
        'type',
        'amount',
        'tax_amount',
        'settlement',
        'wallet_transaction_id',
        'payout_id',
        'settled_at',
        'note',
        'created_by_admin_id',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $charge) {
            $onlySettlementFields = collect($charge->getDirty())
                ->keys()
                ->diff(['payout_id', 'settled_at'])
                ->isEmpty();

            if (! $onlySettlementFields) {
                throw new RuntimeException('paid_ad_charges is append-only — only payout_id/settled_at may be updated.');
            }
        });

        static::deleting(function () {
            throw new RuntimeException('paid_ad_charges is append-only — rows cannot be deleted.');
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(PaidAdBooking::class, 'paid_ad_booking_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
