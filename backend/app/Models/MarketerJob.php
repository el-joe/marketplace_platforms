<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketerJob extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'name_ar',
        'name_en',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function categories(): HasMany
    {
        return $this->hasMany(MarketerJobCategory::class);
    }

    public function marketers(): BelongsToMany
    {
        return $this->belongsToMany(Marketer::class, 'marketer_marketer_job');
    }

    /**
     * Categories this job is allowed to work with for a given source (product/classified/travel).
     *
     * If no marketer_job_categories rows exist for this job+type, the job doesn't support
     * that source at all (empty collection). If a row with a null category_id exists, the
     * job supports ALL categories of that source. Otherwise the job is restricted to the
     * whitelist of specific category_id rows.
     */
    public function eligibleCategories(string $categoryType): Collection
    {
        $rows = $this->categories->where('category_type', $categoryType);

        if ($rows->isEmpty()) {
            return match ($categoryType) {
                'product' => new Collection,
                'classified' => new Collection,
                'travel' => new Collection,
                default => new Collection,
            };
        }

        if ($rows->contains(fn ($row) => $row->category_id === null)) {
            return match ($categoryType) {
                'product' => Category::all(),
                'classified' => ClassifiedCategory::all(),
                'travel' => TravelCategory::all(),
                default => new Collection,
            };
        }

        $ids = $rows->pluck('category_id');

        return match ($categoryType) {
            'product' => Category::whereIn('id', $ids)->get(),
            'classified' => ClassifiedCategory::whereIn('id', $ids)->get(),
            'travel' => TravelCategory::whereIn('id', $ids)->get(),
            default => new Collection,
        };
    }
}
