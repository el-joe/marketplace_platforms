<?php

namespace App\Models;

use App\Enums\TravelAgencyBankAccountVerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelAgencyBankAccount extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'verification_status' => TravelAgencyBankAccountVerificationStatus::class,
        ];
    }

    protected $fillable = [
        'travel_agency_id',
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

    public function travelAgency(): BelongsTo
    {
        return $this->belongsTo(TravelAgency::class);
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'verified_by_admin_id');
    }
}
