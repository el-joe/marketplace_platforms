<?php

namespace App\Models;

use App\Enums\VendorBankAccountVerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorBankAccount extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'verification_status' => VendorBankAccountVerificationStatus::class,
        ];
    }

    protected $fillable = [
        'vendor_id',
        'account_holder_name',
        'bank_name',
        'branch',
        'iban',
        'account_number_encrypted',
        'swift_code',
        'currency',
        'is_primary',
        'verification_status',
        'verified_by_admin_id',
        'verified_at',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'verified_by_admin_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'bank_account_id');
    }
}
