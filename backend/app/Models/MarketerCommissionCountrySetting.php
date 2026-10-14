<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerCommissionCountrySetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'category_id', 'country_id',
        'influencer_fee_share_pct', 'affiliate_commission_mode', 'affiliate_commission_rate', 'affiliate_commission_flat',
        'currency', 'updated_by_admin_id',
    ];

    protected $casts = [
        'influencer_fee_share_pct' => 'decimal:4',
        'affiliate_commission_rate' => 'decimal:4',
        'affiliate_commission_flat' => DecimalMoney::class,
    ];

    /**
     * Setting for the category, else the nearest ancestor that has one, so an amount set on a
     * parent category (e.g. Abaya) also applies to products listed under its sub-categories.
     */
    public static function forCategory(?string $countryId, ?string $categoryId): ?self
    {
        $seen = [];
        while ($categoryId && ! isset($seen[$categoryId])) {
            $seen[$categoryId] = true;
            $setting = static::where('country_id', $countryId)->where('category_id', $categoryId)->first();
            if ($setting) {
                return $setting;
            }
            $categoryId = Category::whereKey($categoryId)->value('parent_id');
        }

        return null;
    }

    /**
     * Affiliate default for ONE sale line: fixed = flat x qty; percentage = line_total x rate%; both = sum
     * (same maths as MarketerCommissionRule / MarketerCategoryCommission). 0 when nothing is configured.
     */
    public function affiliateAmountFor(int|float|string $lineTotal, int $quantity = 1): float
    {
        return (new MarketerCommissionRule([
            'commission_mode' => $this->affiliate_commission_mode ?: 'percentage',
            'commission_rate' => (float) $this->affiliate_commission_rate,
            'commission_flat_amount' => $this->affiliate_commission_flat,
        ]))->resolveAmount($lineTotal, $quantity, true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
