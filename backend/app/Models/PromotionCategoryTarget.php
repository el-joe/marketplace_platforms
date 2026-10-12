<?php

namespace App\Models;

use App\Enums\PromotionCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionCategoryTarget extends Model
{
    protected $fillable = ['promotion_category', 'country_id', 'monthly_minimum'];

    protected $casts = [
        'promotion_category' => PromotionCategory::class,
        'monthly_minimum' => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
