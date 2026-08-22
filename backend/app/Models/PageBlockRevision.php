<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageBlockRevision extends Model
{
    use HasUuids;

    public const UPDATED_AT = null; // append-only

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new \RuntimeException('PageBlockRevision is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('PageBlockRevision is append-only and cannot be deleted.');
        });
    }

    protected $fillable = [
        'page_block_id',
        'page_id',
        'revision_number',
        'config_snapshot',
        'is_visible_snapshot',
        'position_snapshot',
        'changed_by_admin_id',
        'change_reason',
        'change_type',
    ];

    protected $casts = [
        'config_snapshot' => 'array',
        'is_visible_snapshot' => 'boolean',
        'revision_number' => 'integer',
        'position_snapshot' => 'integer',
    ];

    public function pageBlock(): BelongsTo
    {
        return $this->belongsTo(PageBlock::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function changedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'changed_by_admin_id');
    }
}
