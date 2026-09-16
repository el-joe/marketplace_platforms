<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FooterLink extends Model
{
    use HasUuids;

    protected $fillable = [
        'group',
        'platform',
        'label_en',
        'label_ar',
        'url',
        'icon_path',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    protected static function booted(): void
    {
        static::saved(fn () => \App\Services\FooterService::flushCache());
        static::deleted(fn () => \App\Services\FooterService::flushCache());
    }
}
