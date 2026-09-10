<?php

namespace App\Services\Ads;

use App\Enums\PaidAdSlotPricingModel;
use App\Models\PaidAdSlot;
use Carbon\Carbon;
use DomainException;

class AdSlotQuoteService
{
    /**
     * @return array{
     *     pricing_model: string, units: int, unit_rate: int, subtotal: int, tax_amount: int, total: int,
     *     budget_amount: ?int, currency: string, days: int, booked_from: string, booked_until: string
     * }
     */
    public function quote(PaidAdSlot $slot, Carbon $from, Carbon $to, ?int $budget = null): array
    {
        $country = $slot->country;
        $today = Carbon::now($country->timezone ?? 'UTC')->startOfDay();
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $earliestAllowed = $today->copy()->addDays($slot->lead_time_days);
        if ($from->lt($earliestAllowed)) {
            throw new DomainException(__('ads.errors.lead_time', ['days' => $slot->lead_time_days]));
        }

        $days = $from->diffInDays($to) + 1;

        if ($days < $slot->min_booking_days) {
            throw new DomainException(__('ads.errors.min_days', ['days' => $slot->min_booking_days]));
        }
        if ($slot->max_booking_days !== null && $days > $slot->max_booking_days) {
            throw new DomainException(__('ads.errors.max_days', ['days' => $slot->max_booking_days]));
        }

        $pricingModel = $slot->pricing_model instanceof PaidAdSlotPricingModel
            ? $slot->pricing_model
            : PaidAdSlotPricingModel::from($slot->pricing_model);

        $units = 0;
        $subtotal = 0;
        $budgetAmount = null;

        switch ($pricingModel) {
            case PaidAdSlotPricingModel::FixedDaily:
                $units = $days;
                $subtotal = $units * $slot->base_rate;
                break;

            case PaidAdSlotPricingModel::FixedWeekly:
                if ($days % 7 !== 0) {
                    throw new DomainException(__('ads.errors.weekly_multiple'));
                }
                $units = intdiv($days, 7);
                $subtotal = $units * $slot->base_rate;
                break;

            case PaidAdSlotPricingModel::FixedMonthly:
                if ($days % 30 !== 0) {
                    throw new DomainException(__('ads.errors.monthly_multiple'));
                }
                $units = intdiv($days, 30);
                $subtotal = $units * $slot->base_rate;
                break;

            case PaidAdSlotPricingModel::Cpm:
            case PaidAdSlotPricingModel::Cpc:
                if ($budget === null) {
                    throw new DomainException(__('ads.errors.budget_required'));
                }
                if ($slot->min_budget !== null && $budget < $slot->min_budget) {
                    throw new DomainException(__('ads.errors.budget_below_min', [
                        'amount' => $slot->min_budget,
                        'currency' => $slot->country->currency_code,
                    ]));
                }
                $units = 0;
                $subtotal = $budget;
                $budgetAmount = $budget;
                break;
        }

        // VAT: mirrors the platform-wide checkout rounding formula (CheckoutCalculationService).
        // Vendor subscription invoices currently charge no VAT at all; ad slot bookings are
        // treated like other billable platform services, so VAT is applied here.
        $taxAmount = (int) round($subtotal * ((float) $country->vat_rate / 100));

        return [
            'pricing_model' => $pricingModel->value,
            'units' => $units,
            'unit_rate' => $slot->base_rate,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
            'budget_amount' => $budgetAmount,
            'currency' => $country->currency_code,
            'days' => $days,
            'booked_from' => $from->toDateString(),
            'booked_until' => $to->toDateString(),
        ];
    }
}
