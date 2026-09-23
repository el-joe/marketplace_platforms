<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Configurable free-storage-period tier by chargeable weight (grams).
 * Used by GenerateFbnStorageFeesJob to determine how many days of FBN
 * storage are free before fees start accruing, based on the listing's
 * chargeable (actual vs. volumetric) weight.
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
    ];

    protected function casts(): array
    {
        return [
            'min_weight_grams' => 'integer',
            'max_weight_grams' => 'integer',
            'free_days' => 'integer',
        ];
    }

    /**
     * Find the free-days tier applicable to a given chargeable weight (grams).
     */
    public static function freeDaysFor(int $chargeableWeightGrams): int
    {
        $rule = static::query()
            ->where('min_weight_grams', '<=', $chargeableWeightGrams)
            ->where(function ($q) use ($chargeableWeightGrams) {
                $q->whereNull('max_weight_grams')
                    ->orWhere('max_weight_grams', '>=', $chargeableWeightGrams);
            })
            ->orderBy('min_weight_grams', 'desc')
            ->first();

        return $rule?->free_days ?? 0;
    }
}
