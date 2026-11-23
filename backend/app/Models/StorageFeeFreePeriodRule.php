<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Configurable free-storage-period tier by chargeable weight (grams).
 * Used by GenerateFbnStorageFeesJob to determine how many days of FBN
 * storage are free before fees start accruing, based on the listing's
 * chargeable (actual vs. volumetric) weight.
 *
 * Weight ranges are closed intervals [min_weight_grams, max_weight_grams]
 * in whole grams; a NULL max means "and above" (+infinity). Two rules
 * overlap when they share at least one gram, so 0–999 and 1000–∞ are
 * adjacent (allowed) while 0–1000 and 1000–∞ share 1000 g (rejected).
 *
 * `effective_from` limits a rule to storage that started on/after that
 * date (NULL = all storage, the pre-#11 behaviour).
 */
class StorageFeeFreePeriodRule extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'min_weight_grams',
        'max_weight_grams',
        'free_days',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'min_weight_grams' => 'integer',
            'max_weight_grams' => 'integer',
            'free_days' => 'integer',
            'effective_from' => 'date',
        ];
    }

    /**
     * Rules whose weight range shares at least one gram with [$min, $max]
     * ($max NULL = +infinity): existing.min <= new.max AND new.min <= existing.max.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlappingWeightRange(Builder $query, int $min, ?int $max): void
    {
        if ($max !== null) {
            $query->where('min_weight_grams', '<=', $max);
        }

        $query->where(fn (Builder $q) => $q->whereNull('max_weight_grams')->orWhere('max_weight_grams', '>=', $min));
    }

    /**
     * Rules that apply to storage which started on $storedSince.
     *
     * @param  Builder<self>  $query
     */
    public function scopeEffectiveFor(Builder $query, CarbonInterface $storedSince): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('effective_from')
            ->orWhereDate('effective_from', '<=', $storedSince->toDateString()));
    }

    /**
     * Free days for a chargeable weight (grams). When $storedSince is given,
     * only rules effective for storage starting that day are considered.
     */
    public static function freeDaysFor(int $chargeableWeightGrams, ?CarbonInterface $storedSince = null): int
    {
        $rule = static::query()
            ->overlappingWeightRange($chargeableWeightGrams, $chargeableWeightGrams)
            ->when($storedSince, fn (Builder $q) => $q->effectiveFor($storedSince))
            ->orderByDesc('min_weight_grams')
            ->first();

        return $rule?->free_days ?? 0;
    }

    /**
     * Human readable range, e.g. "0 – 999 g" or "1,000 g +".
     */
    public function weightRangeLabel(): string
    {
        return $this->max_weight_grams === null
            ? __('admin.fbn_section.weight_range_open', ['min' => number_format($this->min_weight_grams)])
            : __('admin.fbn_section.weight_range', [
                'min' => number_format($this->min_weight_grams),
                'max' => number_format($this->max_weight_grams),
            ]);
    }
}
