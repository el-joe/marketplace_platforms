<?php

namespace App\Models;

use App\Enums\DeliveryInstruction;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'shipping_address_snapshot' => 'array',
            'billing_address_snapshot' => 'array',
            'placed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => OrderStatus::class,
            'payment_status' => OrderPaymentStatus::class,
            'delivery_instruction' => DeliveryInstruction::class,
            'loyalty_discount' => 'integer',
            'loyalty_points_used' => 'decimal:2',
            'loyalty_points_earned' => 'decimal:2',
        ];
    }

    protected $fillable = [
        'id',
        'order_number',
        'customer_id',
        'country_id',
        'status',
        'currency',
        'subtotal',
        'discount',
        'shipping',
        'tax',
        'cod_fee',
        'warranty_total',
        'total',
        'wallet_amount_used',
        'coupon_id',
        'coupon_code_used',
        'payment_method',
        'payment_status',
        'shipping_address_snapshot',
        'billing_address_snapshot',
        'customer_notes',
        'delivery_instruction',
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'risk_score',
        'placed_at',
        'completed_at',
        'cancelled_at',
        'loyalty_discount',
        'loyalty_points_used',
        'loyalty_points_earned',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'model');
    }

    /**
     * Sync the parent order's status based on all its sub-orders' current statuses.
     * Called after any sub-order status change regardless of who made it.
     */
    public function syncStatusFromSubOrders(?string $changedByAdminId = null): void
    {
        $this->loadMissing('subOrders');

        $statuses = $this->subOrders->pluck('status')->map(
            fn ($s) => $s instanceof \BackedEnum ? $s->value : $s
        );

        $newStatus = null;

        if ($statuses->every(fn ($s) => $s === 'completed')) {
            $newStatus = 'completed';
        } elseif ($statuses->every(fn ($s) => in_array($s, ['cancelled', 'refunded'], true))) {
            $newStatus = 'cancelled';
        } elseif ($statuses->every(fn ($s) => $s === 'delivered')) {
            $newStatus = 'delivered';
        } elseif ($statuses->contains('delivered')) {
            $newStatus = 'partially_delivered';
        } elseif ($statuses->every(fn ($s) => in_array($s, ['shipped', 'out_for_delivery', 'delivered', 'completed'], true))) {
            $newStatus = 'shipped';
        } elseif ($statuses->contains(fn ($s) => in_array($s, ['shipped', 'out_for_delivery'], true))) {
            $newStatus = 'partially_shipped';
        }

        $currentStatus = $this->status instanceof \BackedEnum
            ? $this->status->value
            : $this->status;

        if ($newStatus && $newStatus !== $currentStatus) {
            $old = $currentStatus;

            $this->update([
                'status' => $newStatus,
                'completed_at' => $newStatus === 'completed' ? now() : $this->completed_at,
                'cancelled_at' => $newStatus === 'cancelled' ? now() : $this->cancelled_at,
            ]);

            OrderStatusHistory::create([
                'order_id' => $this->id,
                'sub_order_id' => null,
                'from_status' => $old,
                'to_status' => $newStatus,
                'changed_by_admin_id' => $changedByAdminId,
                'reason' => '[Auto] Order status synced from sub-order changes.',
            ]);
        }
    }
}
