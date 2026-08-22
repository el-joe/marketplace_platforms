<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorChangeRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'vendor_id',
        'requested_by_vendor_admin_id',
        'section',
        'request_type',
        'current_data',
        'requested_data',
        'vendor_note',
        'status',
        'reviewed_by_admin_id',
        'reviewed_at',
        'admin_note',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'current_data' => 'array',
            'requested_data' => 'array',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(VendorAdmin::class, 'requested_by_vendor_admin_id');
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function scopeForVendor(Builder $q, string $vendorId): Builder
    {
        return $q->where('vendor_id', $vendorId);
    }

    public function scopeForSection(Builder $q, string $section): Builder
    {
        return $q->where('section', $section);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
