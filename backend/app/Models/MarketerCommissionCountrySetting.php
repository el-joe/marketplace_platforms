<?php

namespace App\Models;

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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
