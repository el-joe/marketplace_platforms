<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageRevision extends Model
{
    use HasUuids;

    public const UPDATED_AT = null; // append-only

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new \RuntimeException('PageRevision is append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('PageRevision is append-only and cannot be deleted.');
        });
    }

    protected $fillable = [
        'page_id',
        'version',
        'blocks_snapshot',
        'published_by_admin_id',
        'publish_reason',
    ];

    protected $casts = [
        'blocks_snapshot' => 'array',
        'version' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function publishedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'published_by_admin_id');
    }
}
