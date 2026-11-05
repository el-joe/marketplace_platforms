<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which customer purchase contract a classified category uses in one country (country_id NULL = every country that has
 * no contract of its own). Independent of ContractCountryAssignment, which holds the seller contracts.
 */
class CustomerContractAssignment extends Model
{
    use HasUuids;

    protected $fillable = ['classified_category_id', 'country_id', 'contract_template_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ClassifiedCategory::class, 'classified_category_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(ClassifiedContractTemplate::class);
    }
}
