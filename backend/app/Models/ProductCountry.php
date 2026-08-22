<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCountry extends Model
{
    protected $fillable = [
        'product_id',
        'country_id',
        'is_available',
        'unavailable_reason',
        'name_override_en',
        'name_override_ar',
        'description_override_en',
        'description_override_ar',
        'requires_local_cert',
        'certification_body',
        'certification_notes',
        'is_age_restricted',
        'min_age',
        'seo_title_en',
        'seo_title_ar',
        'seo_description_en',
        'seo_description_ar',
        'made_available_at',
        'made_unavailable_at',
        'updated_by_admin_id',
    ];

    protected $table = 'product_countries';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }
}
