<?php

namespace App\Models;

use App\Enums\VendorDocumentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class VendorDocument extends Model
{
    use HasUuids;

    protected $fillable = [
        'vendor_id',
        'vendor_document_type_id',
        'file_path',
        'status',
        'verified_by_admin_id',
        'verified_at',
        'rejection_reason',
        'notes',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => VendorDocumentStatus::class,
        ];
    }

    public function isVerified(): bool
    {
        return $this->status === VendorDocumentStatus::Approved;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', VendorDocumentStatus::Pending->value);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(VendorDocumentType::class, 'vendor_document_type_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'verified_by_admin_id');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    public function getFullFilePathAttribute()
    {
        return asset('storage/' . $this->file_path);
    }
}
