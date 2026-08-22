<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerInfluencerFeeCountrySetting extends Model
{
    use HasUuids;

    protected $fillable = ['country_id', 'fee_per_influencer', 'currency', 'updated_by_admin_id'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
