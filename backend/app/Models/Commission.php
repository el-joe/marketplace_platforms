<?php

namespace App\Models;

use App\Enums\CommissionRateType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'rate_type'       => CommissionRateType::class,
            'rate_pct'        => 'decimal:2',
            'effective_from'  => 'date',
            'effective_until' => 'date',
        ];
    }

    protected $fillable = [
        'vendor_id',
        'category_id',
        'rate_pct',
        'rate_type',
        'min_commission',
        'max_commission',
        'effective_from',
        'effective_until',
        'priority',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
