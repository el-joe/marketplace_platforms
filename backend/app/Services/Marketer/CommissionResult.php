<?php

namespace App\Services\Marketer;

use App\Enums\CommissionVertical;
use App\Enums\MarketerType;

/**
 * Immutable outcome of MarketerCommissionCalculator::compute().
 *
 *   gross = the commission the marketer earned (what the campaign budget pays);
 *   fee   = the platform's commission fee taken from the gross (pct x gross + fixed, never above the gross);
 *   net   = gross - fee, credited to the marketer wallet (never below 0);
 *   basis = the sale amount the commission was computed on (per-vertical, see the calculator);
 *   ruleSource = invitation_snapshot | profile_rule | default | none.
 *
 * The extra fields (mode, rate, fixedAmount, quantity, fee rule, perCampaign) exist so the UI can explain every
 * number: "8% x 1,000 = 80".
 */
final class CommissionResult
{
    public const MODE_NONE = 'none';

    public const MODE_FIXED = 'fixed';

    public const MODE_PERCENTAGE = 'percentage';

    public const MODE_BOTH = 'both';

    public function __construct(
        public readonly float $gross,
        public readonly float $fee,
        public readonly float $net,
        public readonly float $basis,
        public readonly string $ruleSource,
        public readonly MarketerType $marketerType,
        public readonly CommissionVertical $vertical,
        public readonly string $mode = self::MODE_NONE,
        public readonly ?float $rate = null,
        public readonly ?float $fixedAmount = null,
        public readonly int $quantity = 1,
        public readonly float $feePercentage = 0.0,
        public readonly float $feeFixed = 0.0,
        public readonly bool $perCampaign = false,
    ) {}

    /** The same rule applied to a different gross (the budget cap shortens the last commission). */
    public function withGross(float $gross): self
    {
        $split = CommissionFeeService::split($gross, $this->feePercentage, $this->feeFixed);

        return new self(
            $split['gross'], $split['fee'], $split['net'], $this->basis, $this->ruleSource, $this->marketerType,
            $this->vertical, $this->mode, $this->rate, $this->fixedAmount, $this->quantity,
            $this->feePercentage, $this->feeFixed, $this->perCampaign,
        );
    }

    public function isEmpty(): bool
    {
        return $this->gross <= 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'gross' => $this->gross,
            'fee' => $this->fee,
            'net' => $this->net,
            'basis' => $this->basis,
            'rule_source' => $this->ruleSource,
            'marketer_type' => $this->marketerType->value,
            'vertical' => $this->vertical->value,
            'mode' => $this->mode,
            'rate' => $this->rate,
            'fixed_amount' => $this->fixedAmount,
            'quantity' => $this->quantity,
            'fee_percentage' => $this->feePercentage,
            'fee_fixed' => $this->feeFixed,
            'per_campaign' => $this->perCampaign,
        ];
    }
}
