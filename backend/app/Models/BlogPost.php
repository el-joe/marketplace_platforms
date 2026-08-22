<?php

namespace App\Models;

use App\Enums\BlogPostStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'blog_category_id',
        'country_id',
        'author_admin_id',
        'title_en',
        'title_ar',
        'slug',
        'excerpt_en',
        'excerpt_ar',
        'body_en',
        'body_ar',
        'featured_image_path',
        'featured_image_alt_en',
        'featured_image_alt_ar',
        'status',
        'published_at',
        'scheduled_for',
        'archived_at',
        'published_by_admin_id',
        'seo_title_en',
        'seo_title_ar',
        'seo_description_en',
        'seo_description_ar',
        'og_image_path',
        'views_count',
        'allow_comments',
        'is_featured',
        'reading_time_minutes',
        'tags',
    ];

    protected $casts = [
        'published_at'   => 'datetime',
        'scheduled_for'  => 'datetime',
        'archived_at'    => 'datetime',
        'allow_comments' => 'boolean',
        'is_featured'    => 'boolean',
        'status'         => BlogPostStatus::class,
        'tags'           => 'array',
        'views_count'    => 'integer',
        'reading_time_minutes' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'author_admin_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'published_by_admin_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(File::class, 'model')
            ->where('file_type', 'blog_attachment')
            ->orderBy('position');
    }

    public function getReadingTimeAttribute(): int
    {
        return (int) ceil(str_word_count(strip_tags($this->body_en)) / 200);
    }

    public function isVisibleInCountry(Country $country): bool
    {
        return $this->country_id === null || $this->country_id === $country->id;
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }

    public function scopeForCountry($query, ?Country $country)
    {
        if ($country === null) {
            return $query;
        }

        return $query->where(function ($q) use ($country) {
            $q->whereNull('country_id')->orWhere('country_id', $country->id);
        });
    }
}
