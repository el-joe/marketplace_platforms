<?php

namespace App\Models;

use App\Casts\DecimalMoney;
use App\Support\Money;
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
        'affiliate_promo_code_id',
        'subtotal',
        'discount',
        'wallet_amount_to_use',
        'estimated_shipping',
        'estimated_tax',
        'estimated_total',
        'expires_at',
    ];

    protected $casts = [
        'subtotal' => DecimalMoney::class,
        'estimated_shipping' => DecimalMoney::class,
        'estimated_tax' => DecimalMoney::class,
        'estimated_total' => DecimalMoney::class,
        'wallet_amount_to_use' => DecimalMoney::class,
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

    public function affiliatePromoCode(): BelongsTo
    {
        return $this->belongsTo(AffiliatePromoCode::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function inventoryLocks(): HasMany
    {
        return $this->hasMany(CartInventoryLock::class);
    }

    /**
     * Extended-warranty amount for the cart, priced the way checkout charges it
     * (CheckoutPricingEngine): one plan price per cart line, not per unit, and
     * only for plans that are still active.
     */
    public function warrantyTotal(): int|float
    {
        $this->loadMissing('items.warrantyPlan');

        $total = $this->items->sum(
            fn (CartItem $item): int|float => $item->warrantyPlan?->is_active
                ? $item->warrantyPlan->resolvePrice($item->unit_price)
                : 0,
        );

        return Money::number(round($total, 2));
    }
}
