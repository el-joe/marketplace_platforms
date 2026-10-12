<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money snapshot of one sub-order sold BY a marketer (seller_type = 'marketer'), credited to the owner's wallet.
 * See App\Services\Marketer\MarketerSaleSettlementService for the formula.
 */
class MarketerSaleSettlement extends Model
{
    use HasUuids;

    public const VIA_DIRECT = 'direct';

    public const VIA_PEER = 'peer';

    protected $fillable = [
        'marketer_id', 'sub_order_id', 'order_id', 'currency', 'sold_via',
        'gross_amount', 'coupon_cost_amount', 'platform_commission_amount', 'commission_vat_rate', 'commission_vat_amount',
        'gateway_fee_amount', 'peer_commission_amount', 'other_deduction_amount', 'net_amount',
        'credited_at', 'released_at',
    ];

    protected $casts = [
        'gross_amount' => DecimalMoney::class,
        'coupon_cost_amount' => DecimalMoney::class,
        'platform_commission_amount' => DecimalMoney::class,
        'commission_vat_amount' => DecimalMoney::class,
        'gateway_fee_amount' => DecimalMoney::class,
        'peer_commission_amount' => DecimalMoney::class,
        'other_deduction_amount' => DecimalMoney::class,
        'net_amount' => DecimalMoney::class,
        'commission_vat_rate' => 'decimal:2',
        'credited_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
