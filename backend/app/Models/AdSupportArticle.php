<?php

namespace App\Models;

use App\Enums\AdSupportArticleStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class AdSupportArticle extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'ad_support_collection_id',
        'author_admin_id',
        'title',
        'title_en',
        'title_ar',
        'slug',
        'excerpt',
        'excerpt_en',
        'excerpt_ar',
        'body',
        'body_en',
        'body_ar',
        'status',
        'published_at',
        'is_featured',
        'related_article_ids',
        'views_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'related_article_ids' => 'array',
        'views_count' => 'integer',
        'status' => AdSupportArticleStatus::class,
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function localizedTitle(): string
    {
        return session('locale', 'ar') === 'ar'
            ? ($this->title_ar ?: $this->getRawOriginal('title'))
            : ($this->title_en ?: $this->getRawOriginal('title'));
    }

    public function localizedExcerpt(): ?string
    {
        return session('locale', 'ar') === 'ar'
            ? ($this->excerpt_ar ?: $this->getRawOriginal('excerpt'))
            : ($this->excerpt_en ?: $this->getRawOriginal('excerpt'));
    }

    public function localizedBody(): ?string
    {
        return session('locale', 'ar') === 'ar'
            ? ($this->body_ar ?: $this->getRawOriginal('body'))
            : ($this->body_en ?: $this->getRawOriginal('body'));
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(AdSupportCollection::class, 'ad_support_collection_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'author_admin_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', AdSupportArticleStatus::Published)->where('published_at', '<=', now());
    }

    /**
     * Table of contents parsed from <h1 id="..."> / <h2 id="..."> headings in the rendered body.
     *
     * @return array<int, array{id: string, label: string}>
     */
    public function getTableOfContentsAttribute(): array
    {
        if (!preg_match_all('/<h[12][^>]*\bid="([^"]+)"[^>]*>(.*?)<\/h[12]>/is', (string) $this->localizedBody(), $matches, PREG_SET_ORDER)) {
            return [];
        }

        return array_map(fn (array $m) => [
            'id' => $m[1],
            'label' => trim(strip_tags($m[2])),
        ], $matches);
    }

    public function resolvedRelatedArticles(): Collection
    {
        if (empty($this->related_article_ids)) {
            return collect();
        }

        return static::whereIn('id', $this->related_article_ids)
            ->published()
            ->get();
    }

    public function updatedLabel(): string
    {
        return ($this->published_at ?? $this->updated_at)->format('F j, Y');
    }
}
