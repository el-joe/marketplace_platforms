<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleVendorInvitition extends Model
{
    use HasUuids;

    // NOTE: table name preserves the intentional typo from the migration
    protected $table = 'flash_sale_vendor_invititions';

    protected $fillable = [
        'flash_sale_id',
        'vendor_id',
        'invitation_type',
        'status',
        'invited_at',
        'notified_at',
        'responded_at',
        'decline_reason',
        'slots_allocated',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'notified_at' => 'datetime',
            'responded_at' => 'datetime',
            'status' => \App\Enums\FlashSaleVendorInvitationStatus::class,
        ];
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
