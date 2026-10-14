<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingFeeCountrySetting extends Model
{
    use HasUuids;

    protected $fillable = ['country_id', 'fixed_amount', 'fixed_percentage', 'updated_by_admin_id'];

    protected $casts = [
        'fixed_amount' => DecimalMoney::class,
        'fixed_percentage' => 'decimal:4',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }
}
