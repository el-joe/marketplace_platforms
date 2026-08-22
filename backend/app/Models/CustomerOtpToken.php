<?php

namespace App\Models;

use App\Enums\CustomerOtpTokenType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOtpToken extends Model
{
    protected $fillable = [
        'customer_id',
        'token',
        'type',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerOtpTokenType::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
