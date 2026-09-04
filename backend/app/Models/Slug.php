<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Slug extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug_url',
        'sluggable_type',
        'sluggable_id',
    ];

    public function sluggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether $slugUrl is already owned by a different model than
     * ($exceptType, $exceptId) — the cross-type uniqueness check shared by
     * category and custom-page admin forms.
     */
    public static function isTaken(string $slugUrl, ?string $exceptType = null, ?string $exceptId = null): bool
    {
        return static::where('slug_url', $slugUrl)
            ->when($exceptType && $exceptId, fn ($q) => $q->where(function ($q2) use ($exceptType, $exceptId) {
                $q2->where('sluggable_type', '!=', $exceptType)->orWhere('sluggable_id', '!=', $exceptId);
            }))
            ->exists();
    }

    /**
     * Reserve/update $slugUrl as the canonical slug for $model (Category or CustomPage).
     */
    public static function upsertFor(Model $model, string $slugUrl): self
    {
        return static::updateOrCreate(
            ['sluggable_type' => $model::class, 'sluggable_id' => $model->getKey()],
            ['slug_url' => $slugUrl],
        );
    }
}
