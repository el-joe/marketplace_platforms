<?php

namespace App\Models;

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
        "base_fee" => "integer",
        "carrier_rate" => "integer",
        "carrier_rate_per_kg" => "integer",
        "rate_per_kg" => "integer",
        "min_weight_grams" => "integer",
        "volumetric_divisor" => "integer",
        "free_shipping_threshold" => "integer",
        "cod_extra_fee" => "integer",
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

    public function getGapAttribute(): int
    {
        return max(0, $this->carrier_rate - $this->base_fee);
    }

    public function hasGap(): bool
    {
        return $this->gap > 0;
    }

    public function calculateCost(int $weightGrams, bool $isCod = false): int
    {
        $base = $this->base_fee;
        $weightKg = $weightGrams / 1000;
        $weightCost = (int) ($weightKg * $this->rate_per_kg);
        $total = $base + $weightCost;
        if ($isCod) {
            $total += $this->cod_extra_fee;
        }
        return $total;
    }

    public function isFreeShipping(int $orderValueCents): bool
    {
        return $this->free_shipping_threshold !== null
            && $orderValueCents >= $this->free_shipping_threshold;
    }

    public function calculateVolumetricWeight(int $lengthCm, int $widthCm, int $heightCm): float
    {
        return ($lengthCm * $widthCm * $heightCm) / $this->volumetric_divisor;
    }
}
