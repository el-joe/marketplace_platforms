<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassifiedContractTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'classified_category_id', 'name', 'version',
        'content_en', 'content_ar', 'is_active', 'created_by_admin_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class, 'classified_category_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(ClassifiedListing::class, 'contract_template_id');
    }

    public function getContentAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->content_ar : $this->content_en;
    }
}
