<?php

namespace App\Models;

use App\Enums\VendorDocumentRequirementLevel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorDocumentCountryRequirement extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'requirement_level' => VendorDocumentRequirementLevel::class,
        ];
    }

    protected $fillable = [
        'vendor_document_type_id',
        'country_id',
        'requirement_level',
    ];

    public function vendorDocumentType(): BelongsTo
    {
        return $this->belongsTo(VendorDocumentType::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
