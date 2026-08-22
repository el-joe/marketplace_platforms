<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppBottomNavItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'app_context_id',
        'country_id',
        'position',
        'nav_type',
        'label_en',
        'label_ar',
        'icon_name',
        'deep_link',
        'is_center_featured',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_center_featured' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function appContext(): BelongsTo
    {
        return $this->belongsTo(AppContext::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
