<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppContextCountry extends Model
{
    use HasUuids;

    protected $fillable = [
        'app_context_id',
        'country_id',
        'home_page_id',
        'is_active',
    ];

    protected $casts = [
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

    public function homePage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'home_page_id');
    }
}
