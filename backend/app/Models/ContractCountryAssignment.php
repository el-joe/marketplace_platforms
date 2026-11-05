<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which contract template a product category, classified category or single product uses for one country.
 * Exactly one of the three subject columns is set.
 */
class ContractCountryAssignment extends Model
{
    use HasUuids;

    protected $fillable = ['country_id', 'contract_template_id', 'product_category_id', 'classified_category_id', 'product_id'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(ClassifiedContractTemplate::class);
    }
}
