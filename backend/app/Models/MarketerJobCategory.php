<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerJobCategory extends Model
{
    use HasUuids;

    protected $fillable = [
        'marketer_job_id',
        'category_type',
        'category_id',
    ];

    public function marketerJob(): BelongsTo
    {
        return $this->belongsTo(MarketerJob::class);
    }

    /**
     * Resolve the actual category model this whitelist row points to, based on category_type.
     */
    public function category(): ?Model
    {
        return match ($this->category_type) {
            'product' => Category::find($this->category_id),
            'classified' => ClassifiedCategory::find($this->category_id),
            'travel' => TravelCategory::find($this->category_id),
            default => null,
        };
    }
}
