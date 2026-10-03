<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorApiToken extends Model
{
    protected $fillable = [
        'vendor_admin_id',
        'name',
        'token',
        'token_prefix',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public function vendorAdmin(): BelongsTo
    {
        return $this->belongsTo(VendorAdmin::class);
    }
}
