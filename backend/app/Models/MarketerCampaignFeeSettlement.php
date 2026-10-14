<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketerCampaignFeeSettlement extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const KIND_CHARGE = 'charge';

    /** P7: a refund-after-settlement credit to the vendor (amount is positive, it is money back). */
    public const KIND_REFUND = 'refund';

    protected $fillable = ['campaign_id', 'vendor_id', 'currency', 'amount', 'status', 'paid_via', 'paid_at', 'trigger', 'kind'];

    public function isRefund(): bool
    {
        return $this->kind === self::KIND_REFUND;
    }

    protected $casts = [
        'amount' => DecimalMoney::class,
        'paid_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class, 'campaign_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(MarketerCampaignFeeAccrual::class, 'settlement_id');
    }

    public function payoutItems(): HasMany
    {
        return $this->hasMany(PayoutItem::class, 'affiliate_fee_settlement_id');
    }
}
