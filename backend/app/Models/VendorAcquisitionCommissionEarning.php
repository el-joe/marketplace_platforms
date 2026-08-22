<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorAcquisitionCommissionEarning extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'order_count_in_month' => 'integer',
            'amount' => 'integer',
        ];
    }

    protected $fillable = [
        'commission_id',
        'sub_order_id',
        'month',
        'order_count_in_month',
        'amount',
        'currency',
        'status',
    ];

    public function commission(): BelongsTo
    {
        return $this->belongsTo(VendorAcquisitionCommission::class, 'commission_id');
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }
}
