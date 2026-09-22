<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExclusiveContract extends Model
{
    use HasUuids;

    protected $fillable = [
        'marketer_id',
        'classified_category_id',
        'classified_listing_id',
        'starts_at',
        'ends_at',
        'status',
        'contract_file_path',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function classifiedCategory(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class, 'classified_category_id');
    }

    public function classifiedListing(): BelongsTo
    {
        return $this->belongsTo(ClassifiedListing::class, 'classified_listing_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function isCurrentlyActive(): bool
    {
        return $this->status === 'active'
            && $this->starts_at !== null
            && $this->ends_at !== null
            && now()->between($this->starts_at, $this->ends_at);
    }
}
