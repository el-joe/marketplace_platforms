<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasUuids;
    protected $fillable = [
        'country_id',
        'user_id',
        'session_token',
        'currency',
        'coupon_id',
        'subtotal',
        'discount',
        'wallet_amount_to_use',
        'estimated_shipping',
        'estimated_tax',
        'estimated_total',
        'expires_at',
    ];

    protected $casts = [
        'wallet_amount_to_use' => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function inventoryLocks(): HasMany
    {
        return $this->hasMany(CartInventoryLock::class);
    }
}
