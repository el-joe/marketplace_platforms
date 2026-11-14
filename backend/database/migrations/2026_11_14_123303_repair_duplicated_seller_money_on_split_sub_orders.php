<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Data repair for orders placed before SellerSubOrderApportioner existed.
 *
 * When one seller's lines used different shipping methods, place-order created one sub-order per
 * method but copied that seller's WHOLE tax, commission, warranty, shipping and payout onto each of
 * them (e.g. NAWY-20261102-Z70HYW: three sub-orders each holding the vendor's 19,504.85 tax and
 * 390,097 payout). This divides each duplicated figure across the seller's sub-orders the same way
 * checkout now does: exactly from the order items where they carry the figure (tax, commission,
 * coupon cost, marketer commission, warranty), pro rata to subtotal otherwise, with vendor payout
 * re-derived per sub-order.
 *
 * Only seller groups whose sub-orders all hold the identical tax and payout while the order's
 * sub-orders over-count its tax are touched, and never an order with ledger entries or payout
 * items. The previous values are written to storage/app/migration-backups so down() restores them.
 */
return new class extends Migration
{
    private const BACKUP_PATH = 'migration-backups/2026_11_14_repair_duplicated_seller_money_on_split_sub_orders.json';

    /** Figures split pro rata to the sub-order's subtotal. */
    private const GROSS_SPLIT_COLUMNS = [
        'shipping', 'admin_subsidy_amount', 'vendor_contribution_amount', 'carrier_shipping_cost',
        'shipping_gap', 'platform_coupon_cost', 'gateway_fee',
    ];

    /** Sub-order figure => order_items column that carries it per line. */
    private const ITEM_SPLIT_COLUMNS = [
        'platform_commission' => 'platform_commission_after_discount',
        'vendor_coupon_cost' => 'vendor_coupon_cost',
        'marketer_commission' => 'marketer_commission',
    ];

    private const BACKUP_COLUMNS = [
        'tax', 'warranty_revenue', 'platform_commission', 'commission_vat', 'vendor_coupon_cost',
        'marketer_commission', 'shipping', 'admin_subsidy_amount', 'vendor_contribution_amount',
        'carrier_shipping_cost', 'shipping_gap', 'platform_coupon_cost', 'gateway_fee',
        'billable_weight_grams', 'vendor_payout',
    ];

    public function up(): void
    {
        $backup = [];

        foreach ($this->affectedOrderIds() as $orderId) {
            DB::transaction(function () use ($orderId, &$backup) {
                foreach ($this->duplicatedSellerGroups($orderId) as $subOrders) {
                    foreach ($this->repairedFigures($subOrders) as $subOrderId => $figures) {
                        $original = $subOrders->firstWhere('id', $subOrderId);
                        $backup[$subOrderId] = array_intersect_key((array) $original, array_flip(self::BACKUP_COLUMNS));

                        DB::table('sub_orders')->where('id', $subOrderId)->update($figures + ['updated_at' => now()]);
                    }
                }
            });
        }

        if ($backup !== []) {
            Storage::disk('local')->put(self::BACKUP_PATH, json_encode($backup, JSON_PRETTY_PRINT));
            Log::info('Repaired duplicated seller money on split sub-orders.', ['sub_orders' => array_keys($backup)]);
        }
    }

    public function down(): void
    {
        if (! Storage::disk('local')->exists(self::BACKUP_PATH)) {
            return;
        }

        $backup = json_decode(Storage::disk('local')->get(self::BACKUP_PATH), true) ?? [];

        DB::transaction(function () use ($backup) {
            foreach ($backup as $subOrderId => $figures) {
                DB::table('sub_orders')->where('id', $subOrderId)->update($figures);
            }
        });
    }

    /** @return list<string> orders whose sub-orders over-count the order's tax, with no ledger or payout yet */
    private function affectedOrderIds(): array
    {
        return DB::table('orders')
            ->join('sub_orders', 'sub_orders.order_id', '=', 'orders.id')
            ->whereNotExists(fn ($q) => $q->from('ledger_entries')->whereColumn('ledger_entries.transaction_group_id', 'orders.id'))
            ->whereNotExists(fn ($q) => $q->from('payout_items')
                ->join('sub_orders as paid', 'paid.id', '=', 'payout_items.sub_order_id')
                ->whereColumn('paid.order_id', 'orders.id'))
            ->groupBy('orders.id', 'orders.tax')
            ->havingRaw('COUNT(sub_orders.id) > 1')
            ->havingRaw('SUM(sub_orders.tax) > orders.tax + 0.01')
            ->pluck('orders.id')
            ->all();
    }

    /** @return list<Collection<int, object>> seller groups holding one duplicated figure set */
    private function duplicatedSellerGroups(string $orderId): array
    {
        return DB::table('sub_orders')
            ->where('order_id', $orderId)
            ->where('sub_order_number', 'not like', 'EX-%')
            ->lockForUpdate()
            ->get()
            ->groupBy(fn ($subOrder) => $subOrder->vendor_id ?? ($subOrder->marketer_id ? 'marketer:'.$subOrder->marketer_id : 'platform'))
            ->filter(fn ($group) => $group->count() > 1
                && $group->pluck('tax')->unique()->count() === 1
                && $group->pluck('vendor_payout')->unique()->count() === 1)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $subOrders
     * @return array<string, array<string, int|float>>
     */
    private function repairedFigures($subOrders): array
    {
        $seller = $subOrders->first();
        $ids = $subOrders->pluck('id')->all();

        $items = DB::table('order_items')
            ->leftJoin('warranty_purchases', 'warranty_purchases.id', '=', 'order_items.warranty_purchase_id')
            ->whereIn('order_items.sub_order_id', $ids)
            ->get([
                'order_items.sub_order_id', 'order_items.line_tax', 'order_items.platform_commission_after_discount',
                'order_items.vendor_coupon_cost', 'order_items.marketer_commission', 'warranty_purchases.price_paid',
            ])
            ->groupBy('sub_order_id');

        $sumItems = fn (string $column) => array_combine($ids, array_map(
            fn (string $id) => (float) ($items->get($id)?->sum($column) ?? 0),
            $ids,
        ));

        $gross = array_combine($ids, $subOrders->map(fn ($s) => (float) $s->subtotal)->all());
        $itemTax = $sumItems('line_tax');
        $warranty = $sumItems('price_paid');

        $figures = array_fill_keys($ids, []);
        $set = function (string $column, array $values) use (&$figures) {
            foreach ($values as $id => $value) {
                $figures[$id][$column] = $value;
            }
        };

        // Tax: each sub-order's own line taxes, plus its share of the warranty tax (the remainder).
        $warrantyTax = $this->split((float) $seller->tax - array_sum($itemTax), $warranty, $gross);
        $byId = array_combine($ids, $ids);
        $set('tax', array_map(fn ($id) => $this->money($itemTax[$id] + $warrantyTax[$id]), $byId));
        $set('warranty_revenue', $this->split((float) $seller->warranty_revenue, $warranty, $gross));

        foreach (self::ITEM_SPLIT_COLUMNS as $column => $itemColumn) {
            $perItem = $sumItems($itemColumn);
            $set($column, abs(array_sum($perItem) - (float) $seller->{$column}) < 0.01
                ? array_map(fn ($value) => $this->money($value), $perItem)
                : $this->split((float) $seller->{$column}, $gross, $gross));
        }

        $commissionWeights = array_map(fn ($id) => (float) $figures[$id]['platform_commission'], $byId);
        $set('commission_vat', $this->split((float) $seller->commission_vat, $commissionWeights, $gross));

        foreach (self::GROSS_SPLIT_COLUMNS as $column) {
            $set($column, $this->split((float) $seller->{$column}, $gross, $gross));
        }

        $set('billable_weight_grams', array_map('intval', $this->split((int) $seller->billable_weight_grams, $gross, $gross, 0)));

        $payout = [];
        foreach ($ids as $id) {
            $payout[$id] = $seller->seller_type === 'vendor'
                ? $this->money($gross[$id]
                    - $figures[$id]['vendor_coupon_cost']
                    - $figures[$id]['platform_commission']
                    - $figures[$id]['commission_vat']
                    - $figures[$id]['gateway_fee']
                    - $figures[$id]['vendor_contribution_amount']
                    - (in_array($seller->marketer_commission_owner, ['vendor', 'marketer'], true) ? $figures[$id]['marketer_commission'] : 0))
                : 0;
        }
        if ($seller->seller_type === 'vendor') {
            $largest = array_keys($gross, max($gross), true)[0];
            $payout[$largest] = $this->money($payout[$largest] + (float) $seller->vendor_payout - array_sum($payout));
        }
        $set('vendor_payout', $payout);

        return $figures;
    }

    /**
     * Largest-remainder split of $total by $weights (then $fallback, then equal parts when all are zero).
     *
     * @param  array<string, float>  $weights
     * @param  array<string, float>  $fallback
     * @return array<string, int|float>
     */
    private function split(float $total, array $weights, array $fallback, int $decimals = 4): array
    {
        $result = array_fill_keys(array_keys($fallback), 0);
        if (abs($total) < 0.00005) {
            return $result;
        }

        $positive = fn (array $w) => array_filter($w, fn ($value) => $value > 0);
        $effective = $positive($weights) ?: $positive($fallback) ?: array_fill_keys(array_keys($fallback), 1);

        $scale = 10 ** $decimals;
        $units = (int) round(abs($total) * $scale);
        $sumWeights = array_sum($effective);

        $remainders = [];
        $allocated = 0;
        foreach ($effective as $id => $weight) {
            $exact = $units * $weight / $sumWeights;
            $result[$id] = (int) floor($exact + 1e-9);
            $remainders[$id] = $exact - $result[$id];
            $allocated += $result[$id];
        }

        arsort($remainders);
        foreach (array_slice(array_keys($remainders), 0, $units - $allocated) as $id) {
            $result[$id]++;
        }

        $sign = $total < 0 ? -1 : 1;

        return array_map(fn ($value) => $this->money($sign * $value / $scale), $result);
    }

    private function money(float $value): int|float
    {
        $rounded = round($value, 4);

        return floor($rounded) == $rounded ? (int) $rounded : $rounded;
    }
};
