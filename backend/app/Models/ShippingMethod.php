<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingMethod extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'code',
        'description',
        'min_delivery_days',
        'max_delivery_days',
        'is_active',
        'badge_label_en',
        'badge_label_ar',
        'badge_color_hex',
        'badge_text_color_hex',
        'delivery_label_en',
        'delivery_label_ar',
        'is_express_type',
        'show_estimated_price',
        'display_priority',
        'order_cutoff_time',
        'handling_time_hours',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'is_express_type'      => 'boolean',
        'show_estimated_price' => 'boolean',
        'min_delivery_days'    => 'integer',
        'max_delivery_days'    => 'integer',
        'display_priority'     => 'integer',
        'handling_time_hours'  => 'integer',
        'order_cutoff_time'    => 'string',
    ];

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function shippingRates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function countrySettings(): HasMany
    {
        return $this->hasMany(CountryShippingSetting::class);
    }

    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class);
    }

    public function categoryShippingMethods(): HasMany
    {
        return $this->hasMany(CategoryShippingMethod::class);
    }

    /**
     * Compute the latest estimated delivery date from now.
     * Uses max_delivery_days + handling_time_hours + order_cutoff_time.
     * Returns a Carbon date (not datetime) for storage in estimated_delivery_date.
     */
    public function computeEstimatedDeliveryDate(string $timezone = 'UTC'): Carbon
    {
        $now = Carbon::now($timezone);

        // Check if order cutoff has passed today
        $cutoffPassed = false;
        if ($this->order_cutoff_time) {
            $cutoff = Carbon::parse($now->toDateString() . ' ' . $this->order_cutoff_time, $timezone);
            $cutoffPassed = $now->greaterThan($cutoff);
        }

        $maxDays = (int) ($this->max_delivery_days ?? 3) + ($cutoffPassed ? 1 : 0);

        // Handling time brings us to when vendor hands over to carrier
        $readyAt = $now->copy()->addHours((int) ($this->handling_time_hours ?? 24));

        // Estimated delivery = ready time + transit days
        return $readyAt->copy()->addDays($maxDays)->startOfDay();
    }

    /**
     * Human-readable delivery window string for display in the modal.
     * e.g. "2–5 days · arrives by Tue, Aug 20"
     */
    public function deliveryWindowLabel(string $timezone = 'UTC'): string
    {
        $now = Carbon::now($timezone);

        $cutoffPassed = false;
        if ($this->order_cutoff_time) {
            $cutoff = Carbon::parse($now->toDateString() . ' ' . $this->order_cutoff_time, $timezone);
            $cutoffPassed = $now->greaterThan($cutoff);
        }

        $minDays = (int) ($this->min_delivery_days ?? 1) + ($cutoffPassed ? 1 : 0);
        $maxDays = (int) ($this->max_delivery_days ?? 3) + ($cutoffPassed ? 1 : 0);

        $readyAt  = $now->copy()->addHours((int) ($this->handling_time_hours ?? 24));
        $latestAt = $readyAt->copy()->addDays($maxDays)->startOfDay();

        if ($minDays === $maxDays) {
            return "{$maxDays} days · arrives by " . $latestAt->format('D, M j');
        }

        return "{$minDays}–{$maxDays} days · arrives by " . $latestAt->format('D, M j');
    }
}
