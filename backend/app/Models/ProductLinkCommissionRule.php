<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WS12 #24: per-country discount on the platform commission of a vendor's item when the customer arrived through
 * that vendor's own product share link / QR code (see ProductShareLinkService). One rule per country.
 *
 * Discount on one order line (never more than the line's platform commission):
 *   percent : commission x discount_percent / 100
 *   fixed   : discount_fixed x quantity
 *   both    : both of the above, added
 *   zero    : the whole commission (the platform takes nothing on that line)
 */
class ProductLinkCommissionRule extends Model
{
    use HasUuids;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const TYPE_BOTH = 'both';

    public const TYPE_ZERO = 'zero';

    public const TYPES = [self::TYPE_PERCENT, self::TYPE_FIXED, self::TYPE_BOTH, self::TYPE_ZERO];

    /** Sale models the rule can target: the product fulfilment models (classifieds have no checkout commission). */
    public const FULFILLMENT_MODELS = ['fbm', 'fbn', 'cross_dock'];

    public const DEFAULT_WINDOW_DAYS = 7;

    public const MAX_WINDOW_DAYS = 90;

    protected $fillable = [
        'country_id', 'fulfillment_models', 'discount_type', 'discount_percent', 'discount_fixed',
        'attribution_window_days', 'is_active', 'updated_by_admin_id',
    ];

    protected $casts = [
        'fulfillment_models' => 'array',
        'discount_percent' => 'decimal:4',
        'discount_fixed' => DecimalMoney::class,
        'attribution_window_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function appliesTo(string $fulfillmentModel): bool
    {
        return $this->is_active && in_array($fulfillmentModel, $this->fulfillment_models ?? [], true);
    }

    /** Platform commission waived on one line, capped at that line's commission. */
    public function discountOn(int|float $commission, int $quantity): int|float
    {
        $commission = max(0.0, (float) $commission);
        $percentPart = $commission * (float) $this->discount_percent / 100;
        $fixedPart = (float) $this->discount_fixed * max(1, $quantity);

        $discount = match ($this->discount_type) {
            self::TYPE_ZERO => $commission,
            self::TYPE_PERCENT => $percentPart,
            self::TYPE_FIXED => $fixedPart,
            self::TYPE_BOTH => $percentPart + $fixedPart,
            default => 0.0,
        };

        return Money::number(min($commission, max(0.0, round($discount, 2))));
    }

    /** Human description of the discount, e.g. "50% off the platform commission". */
    public function describe(?string $currency = null): string
    {
        return __('product_link.describe.'.$this->discount_type, [
            'percent' => rtrim(rtrim(number_format((float) $this->discount_percent, 4, '.', ''), '0'), '.'),
            'amount' => Money::format($this->discount_fixed),
            'currency' => $currency ?? $this->country?->currency_code ?? '',
        ]);
    }

    /** @return array{discount_type: string, discount_percent: float, discount_fixed: float, attribution_window_days: int} */
    public function snapshot(): array
    {
        return [
            'discount_type' => $this->discount_type,
            'discount_percent' => (float) $this->discount_percent,
            'discount_fixed' => (float) $this->discount_fixed,
            'attribution_window_days' => (int) $this->attribution_window_days,
        ];
    }
}
