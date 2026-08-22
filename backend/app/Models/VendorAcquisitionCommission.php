<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorAcquisitionCommission extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'commission_rate' => 'integer',
            'monthly_min_sales' => 'integer',
            'monthly_max_sales' => 'integer',
            'total_earned' => 'integer',
        ];
    }

    protected $fillable = [
        'vendor_id',
        'admin_id',
        'commission_rate',
        'monthly_min_sales',
        'monthly_max_sales',
        'valid_from',
        'valid_until',
        'status',
        'total_earned',
        'currency',
        'created_by_admin_id',
        'notes',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(VendorAcquisitionCommissionEarning::class, 'commission_id');
    }
}
