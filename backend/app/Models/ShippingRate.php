<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    use HasUuids;

    protected $keyType = "string";
    public $incrementing = false;

    protected $fillable = [
        "origin_zone_id",
        "destination_zone_id",
        "shipping_method_id",
        "carrier_id",
        "base_fee",
        "carrier_rate",
        "carrier_rate_per_kg",
        "rate_per_kg",
        "min_weight_grams",
        "volumetric_divisor",
        "free_shipping_threshold",
        "cod_extra_fee",
        "is_active",
    ];

    protected $casts = [
        "is_active" => "boolean",
        "base_fee" => \App\Casts\DecimalMoney::class,
        "carrier_rate" => \App\Casts\DecimalMoney::class,
        "carrier_rate_per_kg" => \App\Casts\DecimalMoney::class,
        "rate_per_kg" => \App\Casts\DecimalMoney::class,
        "min_weight_grams" => "integer",
        "volumetric_divisor" => "integer",
        "free_shipping_threshold" => \App\Casts\DecimalMoney::class,
        "cod_extra_fee" => \App\Casts\DecimalMoney::class,
    ];

    public function originZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, "origin_zone_id");
    }

    public function destinationZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, "destination_zone_id");
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, "carrier_id");
    }

    public function getBaseFeeFormattedAttribute(): string
    {
        return number_format($this->base_fee, 2);
    }

    public function getRatePerKgFormattedAttribute(): string
    {
        return number_format($this->rate_per_kg, 2);
    }

    public function getCodExtraFeeFormattedAttribute(): string
    {
        return number_format($this->cod_extra_fee, 2);
    }

    public function getFreeThresholdFormattedAttribute(): ?string
    {
        return $this->free_shipping_threshold
            ? number_format($this->free_shipping_threshold, 2)
            : null;
    }

    public function getGapAttribute(): int|float
    {
        return Money::number(max(0, round($this->carrier_rate - $this->base_fee, 2)));
    }

    public function hasGap(): bool
    {
        return $this->gap > 0;
    }

    public function calculateCost(int $weightGrams, bool $isCod = false): int|float
    {
        $base = $this->base_fee;
        $weightKg = $weightGrams / 1000;
        $weightCost = round($weightKg * $this->rate_per_kg, 2);
        $total = $base + $weightCost;
        if ($isCod) {
            $total += $this->cod_extra_fee;
        }
        return Money::number(round($total, 2));
    }

    public function isFreeShipping(int|float $orderValueCents): bool
    {
        return $this->free_shipping_threshold !== null
            && $orderValueCents >= $this->free_shipping_threshold;
    }

    public function calculateVolumetricWeight(int $lengthCm, int $widthCm, int $heightCm): float
    {
        return ($lengthCm * $widthCm * $heightCm) / $this->volumetric_divisor;
    }
}
