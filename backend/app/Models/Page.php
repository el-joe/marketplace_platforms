<?php

namespace App\Models;

use App\Enums\PageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'country_id',
        'page_type',
        'app_context_key',
        'reference_id',
        'name',
        'slug',
        'status',
        'publish_at',
        'published_at',
        'unpublish_at',
        'published_by_admin_id',
        'last_edited_by_admin_id',
        'version',
        'is_default',
        'seo_title',
        'seo_description',
        'og_image_url',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'version' => 'integer',
        'publish_at' => 'datetime',
        'published_at' => 'datetime',
        'unpublish_at' => 'datetime',
        'status' => PageStatus::class,
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function appContext(): BelongsTo
    {
        return $this->belongsTo(AppContext::class, 'app_context_key', 'key');
    }

    public function homeFor(): HasMany
    {
        return $this->hasMany(AppContextCountry::class, 'home_page_id');
    }

    public function publishedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'published_by_admin_id');
    }

    public function lastEditedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'last_edited_by_admin_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('position');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class)->orderBy('position');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->orderByDesc('version');
    }

    public function abTests(): HasMany
    {
        return $this->hasMany(AbTest::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────

    public function scopeDraft($query)
    {
        return $query->where('status', PageStatus::Draft);
    }

    public function scopePublished($query)
    {
        return $query->where('status', PageStatus::Published);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->status === PageStatus::Draft;
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            PageStatus::Published => 'success',
            PageStatus::Scheduled => 'warning',
            PageStatus::Archived => 'danger',
            default => 'gray',
        };
    }
}
