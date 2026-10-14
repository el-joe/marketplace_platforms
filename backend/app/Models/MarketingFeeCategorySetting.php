<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use App\Enums\MarketingFeeType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingFeeCategorySetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'country_id', 'category_id', 'fulfillment_type', 'fixed_amount', 'percentage', 'updated_by_admin_id',
    ];

    protected $casts = [
        'fulfillment_type' => MarketingFeeType::class,
        'fixed_amount' => DecimalMoney::class,
        'percentage' => 'decimal:4',
    ];

    /**
     * Setting for the category and type, else the nearest ancestor category that has one
     * (a rule on a parent category also covers its sub-categories).
     */
    public static function forCategory(?string $countryId, ?string $categoryId, MarketingFeeType $type): ?self
    {
        $seen = [];
        while ($categoryId && ! isset($seen[$categoryId])) {
            $seen[$categoryId] = true;
            $setting = static::where('country_id', $countryId)
                ->where('category_id', $categoryId)
                ->where('fulfillment_type', $type->value)
                ->first();
            if ($setting) {
                return $setting;
            }
            $categoryId = Category::whereKey($categoryId)->value('parent_id');
        }

        return null;
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }
}
