<?php

namespace App\Support\Marketer;

use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Max Commission Budget rules shared by every campaign create / edit endpoint:
 *
 *   - the budget is optional: blank or 0 means UNLIMITED (stored as 0);
 *   - a given budget must be STRICTLY greater than the influencers' fixed total;
 *   - an unlimited campaign needs an end date after today, otherwise nothing would ever stop it.
 */
final class CampaignBudgetRules
{
    public static function isUnlimited(float|int|string|null $budget): bool
    {
        return round((float) $budget, 2) <= 0;
    }

    /** True when a given (limited) budget does not leave room above the influencers' fixed total. */
    public static function budgetTooSmall(float $budget, float $fixedTotal): bool
    {
        return ! self::isUnlimited($budget) && round($budget - $fixedTotal, 2) <= 0;
    }

    /** Smallest increase of the budget that makes it exceed the influencers' fixed total. */
    public static function shortfall(float $budget, float $fixedTotal): float
    {
        return round(max(0.0, $fixedTotal - $budget) + 0.01, 2);
    }

    public static function mustExceedMessage(float $budget, float $fixedTotal): string
    {
        return __('marketer_campaigns.errors.budget_must_exceed_influencers', [
            'fixed' => Money::format($fixedTotal),
            'budget' => Money::format($budget),
            'shortfall' => Money::format(self::shortfall($budget, $fixedTotal)),
        ]);
    }

    /** Validation rules of an optional budget field. */
    public static function budgetRules(): array
    {
        return ['nullable', 'numeric', 'min:0', 'max:999999999', 'decimal:0,2'];
    }

    /** Validation rules of the end date: required while the budget is blank / 0. */
    public static function endsAtRules(Request $request, string $budgetField = 'max_commission_budget', string $after = 'after:today'): array
    {
        return [
            'nullable', 'date', $after,
            Rule::requiredIf(fn () => self::isUnlimited($request->input($budgetField))),
        ];
    }

    public static function endsAtMessages(): array
    {
        return ['ends_at.required' => __('marketer_campaigns.errors.end_date_required')];
    }

    /**
     * Server-side guard for an unlimited, owner-funded campaign: it needs a future end date.
     *
     * @throws ValidationException
     */
    public static function assertEndDateForUnlimited(float $budget, mixed $endsAt): void
    {
        if (! self::isUnlimited($budget)) {
            return;
        }

        $date = $endsAt ? Carbon::parse($endsAt) : null;
        if (! $date || ! $date->isFuture()) {
            throw ValidationException::withMessages(['ends_at' => __('marketer_campaigns.errors.end_date_required')]);
        }
    }
}
