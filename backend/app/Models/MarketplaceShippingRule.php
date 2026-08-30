<?php

namespace App\Models;

use App\Enums\MarketplaceShippingRuleCommissionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceShippingRule extends Model
{
    use HasUuids;

    protected $table = 'marketplace_shipping_rules';

    protected $fillable = [
        'vendor_listing_id',
        'admin_listing_id',
        'requires_special_vehicle',
        'requires_refrigeration',
        'max_weight_kg',
        'max_dimensions_cm',
        'special_handling_notes',
        'commission_type',
        'commission_value',
        'extra_delivery_fee',
    ];

    /** @var int Base currency unit (BIGINT) for money fields renamed in this model */
    protected $casts = [
        'commission_type' => MarketplaceShippingRuleCommissionType::class,
        'requires_special_vehicle' => 'boolean',
        'requires_refrigeration' => 'boolean',
        'max_weight_kg' => 'decimal:2',
        'commission_value' => 'decimal:2',
        'extra_delivery_fee' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function vendorListing(): BelongsTo
    {
        return $this->belongsTo(VendorListing::class);
    }

    public function adminListing(): BelongsTo
    {
        return $this->belongsTo(AdminListing::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function commissionLabel(): string
    {
        return match ($this->commission_type) {
            MarketplaceShippingRuleCommissionType::Fixed => number_format($this->commission_value, 2) . ' EGP',
            MarketplaceShippingRuleCommissionType::Percentage => $this->commission_value . '%',
            MarketplaceShippingRuleCommissionType::Mixed => $this->commission_value . '% + fees',
            default => (string) $this->commission_value,
        };
    }

    public function extraFeeFormatted(): string
    {
        return number_format($this->extra_delivery_fee, 2) . ' EGP';
    }

    public function hasSpecialRequirements(): bool
    {
        return $this->requires_special_vehicle
            || $this->requires_refrigeration
            || $this->max_weight_kg !== null;
    }
}
