<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSectionLock extends Model
{
    use HasUuids;

    public const SECTION_STORE_PROFILE = 'store_profile';
    public const SECTION_BUSINESS_INFO = 'business_info';
    public const SECTION_CONTACT_INFO = 'contact_info';
    public const SECTION_DOCUMENTS = 'documents';
    public const SECTION_BANK_ACCOUNTS = 'bank_accounts';

    protected $fillable = [
        'vendor_id',
        'section',
        'is_locked',
        'locked_reason',
        'locked_by_admin_id',
        'locked_at',
        'unlocked_by_admin_id',
        'unlocked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
            'unlocked_at' => 'datetime',
        ];
    }

    public static function sections(): array
    {
        return [
            self::SECTION_STORE_PROFILE,
            self::SECTION_BUSINESS_INFO,
            self::SECTION_CONTACT_INFO,
            self::SECTION_DOCUMENTS,
            self::SECTION_BANK_ACCOUNTS,
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function lockedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'locked_by_admin_id');
    }

    public function unlockedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'unlocked_by_admin_id');
    }
}
