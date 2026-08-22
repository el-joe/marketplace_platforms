<?php

namespace App\Models;

use App\Enums\VendorStrikeReason;
use App\Enums\VendorStrikeSeverity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorStrike extends Model
{
    use HasUuids;

    protected $fillable = [
        'vendor_id',
        'reason',
        'severity',
        'description',
        'issued_by_admin_id',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'reason' => VendorStrikeReason::class,
            'severity' => VendorStrikeSeverity::class,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn(Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function issuedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'issued_by_admin_id');
    }
}
