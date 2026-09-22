<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenMarketCategoryCommission extends Model
{
    use HasUuids;

    protected $fillable = [
        'marketer_id',
        'classified_category_id',
        'commission_mode',
        'commission_rate',
        'commission_flat_amount',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'commission_flat_amount' => 'integer',
    ];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function classifiedCategory(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    /**
     * Resolve the commission amount owed on a given base amount, according
     * to this rule's commission_mode (fixed | percentage | both).
     */
    public function resolveAmount(int $baseAmount): int
    {
        return match ($this->commission_mode) {
            'fixed' => (int) ($this->commission_flat_amount ?? 0),
            'both' => (int) ($this->commission_flat_amount ?? 0)
                + (int) round($baseAmount * ((float) $this->commission_rate / 100)),
            default => (int) round($baseAmount * ((float) $this->commission_rate / 100)),
        };
    }
}
