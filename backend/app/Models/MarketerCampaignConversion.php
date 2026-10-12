<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerCampaignConversion extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id', 'invitation_id', 'order_id', 'order_item_id',
        'referral_clicked_at', 'commission_amount', 'platform_commission_amount', 'currency',
        'commissioned', 'paid_at', 'sale_number_in_campaign', 'tiered_rule_id',
        'flash_sale_id', 'flash_sale_bonus_amount',
        'status', 'approved_at', 'reversed_at', 'wallet_credited_at', 'wallet_released_at',
        'payout_id',
        'gross_commission_amount', 'platform_cut_percentage', 'platform_cut_base_amount', 'platform_cut_discount_amount',
        'platform_cut_amount', 'platform_cut_vat_rate', 'platform_cut_vat_amount', 'net_commission_amount',
    ];

    protected $casts = [
        'referral_clicked_at' => 'datetime',
        'paid_at' => 'datetime',
        'commissioned' => 'boolean',
        'flash_sale_bonus_amount' => DecimalMoney::class,
        'commission_amount' => DecimalMoney::class,
        'platform_commission_amount' => DecimalMoney::class,
        'gross_commission_amount' => DecimalMoney::class,
        'platform_cut_base_amount' => DecimalMoney::class,
        'platform_cut_discount_amount' => DecimalMoney::class,
        'platform_cut_amount' => DecimalMoney::class,
        'platform_cut_vat_amount' => DecimalMoney::class,
        'net_commission_amount' => DecimalMoney::class,
        'approved_at' => 'datetime',
        'reversed_at' => 'datetime',
        'wallet_credited_at' => 'datetime',
        'wallet_released_at' => 'datetime',
    ];

    /** Total commission this conversion is worth (base rule + any flash-sale bonus). */
    public function getTotalCommissionAttribute(): float
    {
        return round((float) $this->commission_amount + (float) ($this->flash_sale_bonus_amount ?? 0), 2);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaign::class);
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignInvitation::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function tieredRule(): BelongsTo
    {
        return $this->belongsTo(MarketerCampaignTieredRule::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
