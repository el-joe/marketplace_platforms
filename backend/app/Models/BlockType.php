<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlockType extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'label_en',
        'label_ar',
        'group',
        'icon',
        'description_en',
        'description_ar',
        'config_schema',
        'default_config',
        'is_active',
        'requires_permission',
        'max_per_page',
        'sort_order',
    ];

    protected $casts = [
        'config_schema' => 'array',
        'default_config' => 'array',
        'is_active' => 'boolean',
        'max_per_page' => 'integer',
        'sort_order' => 'integer',
    ];

    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class, 'block_type', 'code');
    }
}
