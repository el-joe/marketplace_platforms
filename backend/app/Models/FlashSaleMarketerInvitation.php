<?php

namespace App\Models;

use App\Enums\FlashSaleMarketerInvitationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleMarketerInvitation extends Model
{
    use HasUuids;

    protected $fillable = [
        'flash_sale_id',
        'marketer_id',
        'status',
        'extra_commission_rate',
        'extra_commission_mode',
        'extra_commission_flat_amount',
        'invited_by_admin_id',
        'responded_at',
    ];

    protected $casts = [
        'status' => FlashSaleMarketerInvitationStatus::class,
        'extra_commission_rate' => 'decimal:2',
        'extra_commission_flat_amount' => 'integer',
        'responded_at' => 'datetime',
    ];

    /** Bonus for one order line: pct of line_total and/or flat per unit. */
    public function calculateBonus(int $lineTotal, int $quantity): int
    {
        $mode = $this->extra_commission_mode ?: 'percentage';
        $bonus = 0;
        if ($mode !== 'fixed' && (float) $this->extra_commission_rate > 0) {
            $bonus += (int) round($lineTotal * ((float) $this->extra_commission_rate / 100));
        }
        if ($mode !== 'percentage' && (int) $this->extra_commission_flat_amount > 0) {
            $bonus += (int) $this->extra_commission_flat_amount * max(0, $quantity);
        }

        return $bonus;
    }

    public function hasExtraCommission(): bool
    {
        return (float) $this->extra_commission_rate > 0 || (int) $this->extra_commission_flat_amount > 0;
    }

    /** e.g. "2% + 5 AED". */
    public function commissionLabel(?string $currency = null): string
    {
        $currency ??= $this->marketer?->country?->currency_code ?? 'AED';
        $parts = [];
        $mode = $this->extra_commission_mode ?: 'percentage';
        if ($mode !== 'fixed' && (float) $this->extra_commission_rate > 0) {
            $parts[] = rtrim(rtrim(number_format((float) $this->extra_commission_rate, 2, '.', ''), '0'), '.') . '%';
        }
        if ($mode !== 'percentage' && (int) $this->extra_commission_flat_amount > 0) {
            $parts[] = $this->extra_commission_flat_amount . ' ' . $currency;
        }

        return implode(' + ', $parts);
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function invitedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'invited_by_admin_id');
    }
}
