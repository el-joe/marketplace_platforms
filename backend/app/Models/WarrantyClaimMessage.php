<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaimMessage extends Model
{
    use HasUuids;

    public const SENDER_ROLE_CUSTOMER = 'customer';
    public const SENDER_ROLE_VENDOR = 'vendor';
    public const SENDER_ROLE_ADMIN = 'admin';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_internal_note' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected $fillable = [
        'id',
        'warranty_claim_id',
        'sender_user_id',
        'sender_role',
        'message',
        'is_internal_note',
        'created_at',
    ];

    public function warrantyClaim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class);
    }
}
