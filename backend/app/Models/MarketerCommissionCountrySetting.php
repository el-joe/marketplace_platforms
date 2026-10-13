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
        'influencer_commission_amount', 'affiliate_commission_amount',
        'currency', 'updated_by_admin_id',
    ];

    protected $casts = [
        'influencer_commission_amount' => DecimalMoney::class,
        'affiliate_commission_amount' => DecimalMoney::class,
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
