<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerMarketerJobCategory extends Model
{
    use HasUuids;

    protected $table = 'marketer_marketer_job_category';

    protected $fillable = [
        'marketer_marketer_job_id',
        'category_type',
        'category_id',
    ];

    public function marketerMarketerJob(): BelongsTo
    {
        return $this->belongsTo(MarketerMarketerJob::class, 'marketer_marketer_job_id');
    }

    /**
     * Resolve the actual category model this scope points to, based on category_type.
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
