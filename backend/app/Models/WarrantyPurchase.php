<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class WarrantyPurchase extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'plan_snapshot' => 'array',
            'coverage_starts_at' => 'date',
            'coverage_ends_at' => 'date',
            'price_paid' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(WarrantyPlan::class, 'warranty_plan_id');
    }

    /**
     * FIX-6: the live product being covered (mirrors WarrantyClaim::product(),
     * which also belongsTo Product directly). Backed by `product_id`, set at
     * purchase time from orderItem->productVariant->product and backfilled
     * for pre-existing rows, so the purchases API can show current
     * name/image/slug/price even if orderItem->product_snapshot is stale or
     * incomplete.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeForCustomer(Builder $query, string $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * enhancement.md P-09 task 2/4: the single source of truth for coverage
     * dates, shared by both activation paths:
     *  - SubOrderObserver (checkout-purchased warranty, activated when the
     *    sub-order transitions to `delivered`);
     *  - the post-purchase "buy after delivery" flow (task 4), which
     *    activates immediately since the item is already delivered.
     *
     * `coverage_starts_at` begins after the brand/vendor warranty ends
     * (`delivered_at + vendors.warranty_months`), or at delivery if the
     * vendor has none. `coverage_ends_at` runs for the plan's own duration
     * from that start date.
     *
     * @return array{starts: \Illuminate\Support\Carbon, ends: \Illuminate\Support\Carbon}
     */
    public static function coverageDatesFor(
        \Illuminate\Support\Carbon $deliveredAt,
        ?int $vendorWarrantyMonths,
        int $planDurationMonths,
    ): array {
        $starts = $vendorWarrantyMonths
            ? $deliveredAt->copy()->addMonths($vendorWarrantyMonths)
            : $deliveredAt->copy();

        return [
            'starts' => $starts,
            'ends' => $starts->copy()->addMonths($planDurationMonths),
        ];
    }
}
