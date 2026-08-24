<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageSection extends Model
{
    use HasUuids;

    protected $fillable = [
        'page_id',
        'name',
        'position',
        'is_visible',
        'background_color',
        'background_image_url',
        'background_image_type',
        'padding_top',
        'padding_bottom',
        'max_width',
        'layout',
        'columns_config',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'position' => 'integer',
        'padding_top' => 'integer',
        'padding_bottom' => 'integer',
        'columns_config' => 'json',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class, 'section_id')->orderBy('position');
    }
}
