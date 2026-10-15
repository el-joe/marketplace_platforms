<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use App\Enums\CommissionVertical;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Platform default marketer commission per country x vertical (optionally one category of that vertical):
 * the influencer's fixed amount per campaign and the commission fee (pct + fixed) taken from every marketer commission.
 */
class MarketerDefaultCommission extends Model
{
    use HasUuids;

    protected $fillable = [
        'rule_key', 'country_id', 'category_type', 'category_id',
        'influencer_fixed_amount', 'commission_fee_percentage', 'commission_fee_fixed',
        'updated_by_admin_id',
    ];

    protected $casts = [
        'category_type' => CommissionVertical::class,
        'influencer_fixed_amount' => DecimalMoney::class,
        'commission_fee_percentage' => 'decimal:4',
        'commission_fee_fixed' => DecimalMoney::class,
    ];

    protected static function booted(): void
    {
        // A composite unique index treats NULL category_id as distinct, so a deterministic key carries uniqueness.
        static::saving(function (self $row) {
            $row->rule_key = self::makeRuleKey($row->country_id, $row->category_type, $row->category_id);
        });
    }

    public static function makeRuleKey(string $countryId, CommissionVertical|string $categoryType, ?string $categoryId): string
    {
        $type = $categoryType instanceof CommissionVertical ? $categoryType->value : $categoryType;

        return sha1(implode('|', [$countryId, $type, $categoryId ?? 'default']));
    }

    /**
     * Default for the country + vertical: the category-specific row when given and present, else the vertical-wide row.
     */
    public static function forVertical(string $countryId, CommissionVertical|string $categoryType, ?string $categoryId = null): ?self
    {
        $type = $categoryType instanceof CommissionVertical ? $categoryType->value : $categoryType;
        $query = static::query()->where('country_id', $countryId)->where('category_type', $type);

        if ($categoryId !== null) {
            $specific = (clone $query)->where('category_id', $categoryId)->first();
            if ($specific) {
                return $specific;
            }
        }

        return $query->whereNull('category_id')->first();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }
}
