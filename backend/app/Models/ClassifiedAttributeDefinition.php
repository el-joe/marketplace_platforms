<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassifiedAttributeDefinition extends Model
{
    use HasUuids;

    protected $fillable = [
        'code', 'label_en', 'label_ar',
        'input_type', 'options',
        'unit_en', 'unit_ar',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'options'   => 'array',
        'is_active' => 'boolean',
    ];

    public function categoryMaps(): HasMany
    {
        return $this->hasMany(ClassifiedCategoryAttributeMap::class, 'classified_attribute_definition_id');
    }
}
