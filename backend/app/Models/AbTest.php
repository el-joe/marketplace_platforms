<?php

namespace App\Models;

use App\Enums\AbTestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbTest extends Model
{
    use HasUuids;

    protected $table = 'ab_tests';

    protected $fillable = [
        'page_id',
        'name',
        'hypothesis',
        'status',
        'traffic_split_pct',
        'winner',
        'primary_metric',
        'min_sample_size',
        'started_at',
        'ended_at',
        'result_notes',
        'created_by_admin_id',
    ];

    protected $casts = [
        'traffic_split_pct' => 'integer',
        'min_sample_size' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'status' => AbTestStatus::class,
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(AbTestResult::class)->orderByDesc('date');
    }
}
