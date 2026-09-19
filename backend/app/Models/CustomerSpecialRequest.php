<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSpecialRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id',
        'category_id',
        'city_id',
        'title_en',
        'title_ar',
        'description_en',
        'description_ar',
        'budget',
        'budget_currency',
        'status',
        'brokers_notified',
    ];

    protected $casts = [
        'budget' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** Broker action: open -> in_progress. Returns false if not open. */
    public function startProgress(): bool
    {
        return static::where('id', $this->id)->where('status', 'open')->update(['status' => 'in_progress']) === 1;
    }

    /**
     * Open requests a given broker should see. Single source of truth for matching.
     */
    public function scopeMatchingBroker(Builder $q, MarketerProfile $profile): Builder
    {
        $q->where('status', 'open');

        if (! $profile->broker_category_id) {
            return $q->whereRaw('1 = 0');
        }

        return $q->where('category_id', $profile->broker_category_id)
            ->where(function (Builder $w) use ($profile) {
                $w->whereNull('city_id');
                if ($profile->broker_serves_all_cities) {
                    $w->orWhereNotNull('city_id');
                } elseif ($profile->broker_city_id) {
                    $w->orWhere('city_id', $profile->broker_city_id);
                }
            });
    }
}
