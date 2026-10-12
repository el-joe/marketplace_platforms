<?php

namespace App\ValueObjects;

/**
 * All money fields are DECIMAL(19,4) base currency units — never divide or
 * multiply by 100 here. All weight fields are grams.
 */
readonly class ShippingBreakdown
{
    public function __construct(
        public int|float $customerPays,
        public int|float $carrierCost,
        public int|float $gap,
        public int|float $vendorContribution,
        public int|float $adminSubsidy,
        public ?string $subsidyRuleId,
        public bool $isExceptional,
        public int $billableWeightGrams,
    ) {}

    public static function notServiced(): self
    {
        return new self(0, 0, 0, 0, 0, null, false, 0);
    }

    public function toSubOrderColumns(): array
    {
        return [
            'shipping' => $this->customerPays,
            'carrier_shipping_cost' => $this->carrierCost,
            'shipping_gap' => $this->gap,
            'admin_subsidy_amount' => $this->adminSubsidy,
            'vendor_contribution_amount' => $this->vendorContribution,
            'exceptional_zone_subsidy_id' => $this->subsidyRuleId,
            'billable_weight_grams' => $this->billableWeightGrams,
        ];
    }
}
