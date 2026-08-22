<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorProductCertification extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'product_id',
        'country_id',
        'file_path',
        'original_filename',
        'cert_name',
        'status',
        'rejection_reason',
        'expires_at',
        'reviewed_by_admin_id',
        'reviewed_at',
    ];

    protected $casts = [
        'expires_at'    => 'datetime',
        'reviewed_at'   => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function reviewedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->status === 'approved'
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }
}
