<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppContext extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'name_en',
        'name_ar',
        'icon_path',
        'color_hex',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function countries(): HasMany
    {
        return $this->hasMany(AppContextCountry::class);
    }

    public function navItems(): HasMany
    {
        return $this->hasMany(AppBottomNavItem::class)->orderBy('sort_order');
    }
}
