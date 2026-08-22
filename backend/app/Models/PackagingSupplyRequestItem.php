<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagingSupplyRequestItem extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'packaging_supply_id',
        'quantity',
        'unit_cost',
        'line_total',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity'        => 'integer',
            'unit_cost' => 'integer',
            'line_total'=> 'integer',
            'created_at'      => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PackagingSupplyRequest::class, 'request_id');
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(PackagingSupply::class, 'packaging_supply_id');
    }

    public function getLineTotalFormattedAttribute(): string
    {
        return $this->line_total === 0
            ? 'Free'
            : number_format($this->line_total);
    }
}
