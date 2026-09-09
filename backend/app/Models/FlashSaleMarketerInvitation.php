<?php

namespace App\Models;

use App\Enums\FlashSaleMarketerInvitationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSaleMarketerInvitation extends Model
{
    use HasUuids;

    protected $fillable = [
        'flash_sale_id',
        'marketer_id',
        'status',
        'extra_commission_rate',
        'invited_by_admin_id',
        'responded_at',
    ];

    protected $casts = [
        'status' => FlashSaleMarketerInvitationStatus::class,
        'extra_commission_rate' => 'decimal:2',
        'responded_at' => 'datetime',
    ];

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }

    public function invitedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'invited_by_admin_id');
    }
}
