<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorDocumentType extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'accepted_file_types',
        'max_file_size_kb',
        'requires_expiry_date',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'accepted_file_types'  => 'array',
            'requires_expiry_date' => 'boolean',
            'is_active'            => 'boolean',
        ];
    }

    public function countryRequirements(): HasMany
    {
        return $this->hasMany(VendorDocumentCountryRequirement::class);
    }

    public function vendorDocuments(): HasMany
    {
        return $this->hasMany(VendorDocument::class);
    }
}
