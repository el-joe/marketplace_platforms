<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use App\Enums\CommissionVertical;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Variable campaign fee of the classified / travel verticals, per country: fixed_amount + budget x percentage / 100. */
class MarketingFeeVerticalSetting extends Model
{
    use HasUuids;

    /** Verticals that are configured here (product uses the category settings). */
    public const VERTICALS = [CommissionVertical::Classified, CommissionVertical::Travel];

    protected $fillable = ['country_id', 'vertical', 'fixed_amount', 'percentage', 'updated_by_admin_id'];

    protected $casts = [
        'fixed_amount' => DecimalMoney::class,
        'percentage' => 'decimal:4',
    ];

    public static function forVertical(?string $countryId, CommissionVertical $vertical): ?self
    {
        return $countryId
            ? static::where('country_id', $countryId)->where('vertical', $vertical->value)->first()
            : null;
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
