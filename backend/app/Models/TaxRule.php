<?php

namespace App\Models;

use App\Enums\TaxRuleAppliesTo;
use App\Enums\TaxRuleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRule extends Model
{
    protected $fillable = [
        'name',
        'country_id',
        'region',
        'tax_type',
        'rate_pct',
        'applies_to',
        'category_id',
        'price_includes_tax',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'tax_type' => TaxRuleType::class,
            'applies_to' => TaxRuleAppliesTo::class,
            'price_includes_tax' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
