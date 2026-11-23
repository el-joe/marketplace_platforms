<?php

namespace App\Models\Concerns;

use App\Support\Bilingual;

/**
 * `$model->localized_name`: name_{panel locale} with fallback to the other
 * language, for models following the name_en / name_ar column convention.
 */
trait HasLocalizedName
{
    public function getLocalizedNameAttribute(): string
    {
        return Bilingual::localized($this, 'name');
    }
}
