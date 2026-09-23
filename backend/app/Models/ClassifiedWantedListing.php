<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassifiedWantedListing extends Model
{
    use HasUuids;

    protected $fillable = [
        'listing_number', 'marketer_id', 'classified_category_id', 'country_id', 'city_id',
        'title_ar', 'title_en', 'description_ar', 'budget_min', 'budget_max',
        'currency', 'status', 'expires_at',
    ];

    protected $casts = [
        'budget_min' => 'integer',
        'budget_max' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function classifiedCategory(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class, 'classified_category_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
