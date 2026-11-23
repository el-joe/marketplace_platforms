<?php

namespace App\Services\Customer;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Orders / spend / points-earned per customer, live from `orders`, optionally
 * limited to an activity window (orders.placed_at, the admin dashboard's GMV
 * convention). The denormalized customers.total_orders / total_spent columns
 * are not maintained by any code path, so they are not used here.
 *
 * Counted orders: every status except cancelled, and never a failed payment.
 * Spend is per currency (orders are multi-currency DECIMAL(19,4)); for sorting
 * only, it is converted to a USD equivalent via currencies.exchange_rate_to_base
 * (same rule as HasCurrencyAwareAggregates::consolidatedUsd()).
 */
class CustomerActivityMetrics
{
    /** @var list<string> */
    public const EXCLUDED_ORDER_STATUSES = [OrderStatus::Cancelled->value];

    /** @var list<string> */
    public const EXCLUDED_PAYMENT_STATUSES = ['failed'];

    public function __construct(
        private readonly ?CarbonInterface $from = null,
        private readonly ?CarbonInterface $to = null,
    ) {}

    public function hasWindow(): bool
    {
        return $this->from !== null || $this->to !== null;
    }

    /**
     * Adds activity_orders_count, activity_points_earned and activity_spend_usd
     * as correlated subqueries (one SQL statement, no N+1).
     */
    public function addMetrics(Builder $customers): Builder
    {
        $constraint = fn (Builder|HasMany $orders) => $this->constrain($orders);

        return $customers
            ->withCount(['orders as activity_orders_count' => $constraint])
            ->withSum(['orders as activity_points_earned' => $constraint], 'loyalty_points_earned')
            ->addSelect(['activity_spend_usd' => $this->constrain(
                Order::query()
                    ->leftJoin('currencies', 'currencies.code', '=', 'orders.currency')
                    ->whereColumn('orders.customer_id', 'customers.id')
                    ->selectRaw('COALESCE(SUM(orders.total / COALESCE(NULLIF(currencies.exchange_rate_to_base, 0), 1)), 0)')
            )]);
    }

    /** Customers with at least $minimum counted orders (in the window when set). */
    public function whereOrderCountAtLeast(Builder $customers, int $minimum): Builder
    {
        return $customers->whereHas('orders', fn (Builder $orders) => $this->constrain($orders), '>=', $minimum);
    }

    /**
     * Spend per customer per currency, for display.
     *
     * @param  list<string>  $customerIds
     * @return array<string, array<string, float>> customer_id => [currency => total]
     */
    public function spendByCurrency(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $rows = $this->constrain(Order::query())
            ->whereIn('orders.customer_id', $customerIds)
            ->groupBy('orders.customer_id', 'orders.currency')
            ->orderBy('orders.currency')
            ->get(['orders.customer_id', 'orders.currency', DB::raw('SUM(orders.total) as spend')]);

        $result = [];
        foreach ($rows as $row) {
            $result[$row->customer_id][$row->currency] = (float) $row->spend;
        }

        return $result;
    }

    /**
     * Formats a per-currency spend map, e.g. "120.00 EGP · 40.00 USD"; "0.00" when empty.
     *
     * @param  array<string, float>  $byCurrency
     */
    public static function formatSpend(array $byCurrency): string
    {
        if ($byCurrency === []) {
            return number_format(0, 2);
        }

        return collect($byCurrency)
            ->map(fn (float $amount, string $currency) => number_format($amount, 2).' '.$currency)
            ->implode(' · ');
    }

    /**
     * @template TQuery of Builder|HasMany
     *
     * @param  TQuery  $orders
     * @return TQuery
     */
    private function constrain(Builder|HasMany $orders): Builder|HasMany
    {
        return $orders
            ->whereNotIn('orders.status', self::EXCLUDED_ORDER_STATUSES)
            ->where(fn ($q) => $q->whereNull('orders.payment_status')
                ->orWhereNotIn('orders.payment_status', self::EXCLUDED_PAYMENT_STATUSES))
            ->when($this->from, fn ($q, $from) => $q->where('orders.placed_at', '>=', $from))
            ->when($this->to, fn ($q, $to) => $q->where('orders.placed_at', '<=', $to));
    }
}
