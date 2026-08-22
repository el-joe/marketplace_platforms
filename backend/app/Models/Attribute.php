<?php

namespace App\Models;

use App\Enums\AttributeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name_ar',
        'name_en',
        'code',
        'type',
        'unit',
        'is_variant_attribute',
        'is_filterable',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_variant_attribute' => 'boolean',
        'is_filterable' => 'boolean',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
        'type' => AttributeType::class,
    ];

    public function requiresValues(): bool
    {
        return in_array($this->type, [AttributeType::Select, AttributeType::MultiSelect, AttributeType::Color], true);
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_attributes')
            ->withPivot(['is_required', 'sort_order']);
    }

    public function categoryAttributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class);
    }
}
