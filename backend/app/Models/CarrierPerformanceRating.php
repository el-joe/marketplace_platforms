<?php

namespace App\Models;

use App\Enums\CarrierPerformanceRatedByType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrierPerformanceRating extends Model
{
    use HasUuids;

    protected $fillable = [
        'shipping_company_id',
        'delivery_agent_id',
        'sub_order_id',
        'rated_by_type',
        'rated_by_id',
        'rating',
        'on_time',
        'comment',
        'visible_to_customer', // always forced to false in service layer
    ];

    protected function casts(): array
    {
        return [
            'rating'              => 'integer',
            'on_time'             => 'boolean',
            'visible_to_customer' => 'boolean',
            'rated_by_type'       => CarrierPerformanceRatedByType::class,
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class);
    }

    public function deliveryAgent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /**
     * Never expose carrier identity on customer-facing queries.
     * Strip shipping_company and delivery_agent from the result set.
     */
    public function scopeForCustomer($query)
    {
        return $query->select(
            'id', 'sub_order_id', 'rated_by_type', 'rated_by_id',
            'rating', 'on_time', 'comment', 'created_at'
            // shipping_company_id and delivery_agent_id intentionally omitted
        );
    }
}
