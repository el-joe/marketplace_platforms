<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassifiedCategoryAttributeMap extends Model
{
    use HasUuids;

    protected $table = 'classified_category_attribute_map';

    protected $fillable = [
        'classified_category_id',
        'classified_attribute_definition_id',
        'is_required',
        'is_shown_on_card',
        'is_filterable',
        'sort_order',
    ];

    protected $casts = [
        'is_required'      => 'boolean',
        'is_shown_on_card' => 'boolean',
        'is_filterable'    => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class, 'classified_category_id');
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ClassifiedAttributeDefinition::class, 'classified_attribute_definition_id');
    }
}
