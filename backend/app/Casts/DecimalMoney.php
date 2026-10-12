<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * DECIMAL(19,4) money column. Reads back as int when the amount is whole (so existing JSON
 * consumers keep seeing 5000, not 5000.0) and as float when it has a fraction (0.1, 2.35).
 *
 * @implements CastsAttributes<int|float|null, int|float|string|null>
 */
class DecimalMoney implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): int|float|null
    {
        if ($value === null) {
            return null;
        }

        $amount = round((float) $value, 4);

        return abs($amount - round($amount)) < 0.00005 ? (int) round($amount) : $amount;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): int|float|string|null
    {
        return $value === null || $value === '' ? null : round((float) $value, 4);
    }
}
