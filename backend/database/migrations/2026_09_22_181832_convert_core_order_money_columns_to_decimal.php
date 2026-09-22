<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Converts core order-pricing money columns from BIGINT to DECIMAL(19,4).
 *
 * Scope: order_items, orders, sub_orders only (the tables read/written by
 * CheckoutPricingEngine, ShippingFeeCalculator and the checkout controllers).
 * This is the first of several planned slices — see docs/formula.md and
 * docs/decimal-money-migration-plan.md for the full column inventory across
 * the rest of the schema (payouts, refunds, wallets, ad billing, etc.).
 *
 * Raw SQL is used instead of Blueprint::change() because doctrine/dbal is
 * not installed in this project and we don't want to add a dependency for
 * a one-off type change.
 */
return new class extends Migration
{
    private const DECIMAL_TYPE = 'DECIMAL(19,4)';

    /** @var array<string, list<string>> */
    private array $columns = [
        'order_items' => [
            'unit_price', 'unit_cost_price', 'line_subtotal', 'line_discount',
            'vendor_coupon_cost', 'line_tax', 'line_total', 'commission_fixed',
            'commission_amount', 'marketer_commission', 'platform_commission_after_discount',
        ],
        'orders' => [
            'subtotal', 'discount', 'loyalty_discount', 'shipping', 'tax',
            'cod_fee', 'warranty_total', 'total', 'wallet_amount_used',
        ],
        'sub_orders' => [
            'subtotal', 'shipping', 'carrier_shipping_cost', 'shipping_gap',
            'admin_subsidy_amount', 'vendor_contribution_amount', 'tax',
            'platform_commission', 'vendor_coupon_cost', 'platform_coupon_cost',
            'marketer_commission', 'warranty_revenue', 'gateway_fee', 'vendor_payout',
        ],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column) {
                $definition = $this->columnDefinition($table, $column);
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column) {
                $definition = $this->columnDefinition($table, $column, revert: true);
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
            }
        }
    }

    private function columnDefinition(string $table, string $column, bool $revert = false): string
    {
        $type = $revert ? 'BIGINT' : self::DECIMAL_TYPE;

        $nullable = match (true) {
            $table === 'order_items' && $column === 'unit_cost_price' => 'NULL',
            default => 'NOT NULL',
        };

        $unsigned = ($table === 'orders' && $column === 'wallet_amount_used') ? ' UNSIGNED' : '';

        $default = match (true) {
            $nullable === 'NULL' => '',
            in_array($column, ['line_discount', 'vendor_coupon_cost', 'commission_fixed', 'marketer_commission', 'platform_commission_after_discount', 'discount', 'loyalty_discount', 'cod_fee', 'warranty_total', 'wallet_amount_used', 'carrier_shipping_cost', 'shipping_gap', 'admin_subsidy_amount', 'vendor_contribution_amount', 'vendor_coupon_cost', 'platform_coupon_cost', 'warranty_revenue', 'gateway_fee'], true) => $revert ? " DEFAULT '0'" : " DEFAULT '0.0000'",
            default => '',
        };

        return "{$type}{$unsigned} {$nullable}{$default}";
    }
};
