<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WishlistGroup extends Model
{
    use HasUuids;

    protected $fillable = [
        'customer_id', 'name', 'is_default', 'is_public', 'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_public' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class, 'wishlist_group_id');
    }
}
