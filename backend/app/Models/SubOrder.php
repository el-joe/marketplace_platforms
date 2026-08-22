<?php

namespace App\Models;

use App\Enums\SubOrderStatus;
use App\Traits\HasStateMachine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\DeliveryAgentCodSettlement;

class SubOrder extends Model
{
    use HasUuids, HasStateMachine;

    // ── State machine ─────────────────────────────────────────────────────────

    public const STATUS_TRANSITIONS = [
        'placed'           => ['confirmed', 'cancelled'],
        'confirmed'        => ['processing', 'cancelled'],
        'processing'       => ['packed', 'cancelled'],
        'packed'           => ['shipped', 'cancelled'],
        'shipped'          => ['out_for_delivery', 'delivered'],
        'out_for_delivery' => ['delivered'],
        'delivered'        => ['completed', 'returned'],
        'completed'        => [],
        'cancelled'        => [],
        'returned'         => ['refunded'],
        'refunded'         => [],
    ];

    public const STATUS_LABELS = [
        'placed'           => 'Placed',
        'confirmed'        => 'Confirmed',
        'processing'       => 'Processing',
        'packed'           => 'Packed',
        'shipped'          => 'Shipped',
        'out_for_delivery' => 'Out for Delivery',
        'delivered'        => 'Delivered',
        'completed'        => 'Completed',
        'cancelled'        => 'Cancelled',
        'returned'         => 'Returned',
        'refunded'         => 'Refunded',
    ];

    /** Sub-orders in these statuses block force-cancel unless overridden. */
    public const BLOCK_CANCEL_STATUSES = [
        'shipped',
        'out_for_delivery',
        'delivered',
        'completed',
        'returned',
        'refunded',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'estimated_delivery_date' => 'date',
            'sla_ship_deadline' => 'datetime',
            'sla_breached' => 'boolean',
            'gateway_fee_rate' => 'decimal:6',
            'cod_remittance_confirmed' => 'boolean',
            'cod_remittance_confirmed_at' => 'datetime',
            'status' => SubOrderStatus::class,
            'admin_subsidy_amount' => 'integer',
            'vendor_contribution_amount' => 'integer',
            'carrier_shipping_cost' => 'integer',
            'shipping_gap' => 'integer',
            'billable_weight_grams' => 'integer',
            'subsidy_ledgered' => 'boolean',
        ];
    }

    protected $fillable = [
        'id',
        'order_id',
        'sub_order_number',
        'vendor_id',
        'warehouse_id',
        'status',
        'fulfillment_model',
        'subtotal',
        'shipping',
        'carrier_shipping_cost',
        'shipping_gap',
        'exceptional_zone_subsidy_id',
        'admin_subsidy_amount',
        'vendor_contribution_amount',
        'billable_weight_grams',
        'subsidy_ledgered',
        'tax',
        'platform_commission',
        'gateway_fee',
        'gateway_fee_rate',
        'vendor_payout',
        'shipping_method_id',
        'carrier_id',
        'tracking_number',
        'estimated_delivery_date',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
        'sla_ship_deadline',
        'sla_breached',
        'cod_remittance_confirmed',
        'cod_remittance_confirmed_at',
        'cod_settlement_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(ShippingCarrier::class, 'carrier_id');
    }

    public function exceptionalZoneSubsidy(): BelongsTo
    {
        return $this->belongsTo(PlatformShippingSubsidy::class, 'exceptional_zone_subsidy_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function deliveryAssignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class);
    }

    public function payoutItems(): HasMany
    {
        return $this->hasMany(PayoutItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function codSettlement(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgentCodSettlement::class, 'cod_settlement_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }
}
