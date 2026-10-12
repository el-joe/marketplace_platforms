<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerCategoryCommission extends Model
{
    use HasUuids;

    protected $fillable = [
        'marketer_id',
        'category_id',
        'commission_mode',
        'commission_rate',
        'commission_flat_amount',
        'commission_min_amount',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'commission_flat_amount' => DecimalMoney::class,
        'commission_min_amount' => DecimalMoney::class,
    ];

    /**
     * Resolve the commission amount owed on a given base amount, according
     * to this rule's commission_mode (fixed | percentage | both).
     */
    public function resolveAmount(int|float|string $baseAmount): float
    {
        $percent = round((float) $baseAmount * ((float) $this->commission_rate / 100), 2);

        return match ($this->commission_mode) {
            'fixed' => (float) ($this->commission_flat_amount ?? 0),
            'both' => round((float) ($this->commission_flat_amount ?? 0) + $percent, 2),
            default => $percent,
        };
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }
}
