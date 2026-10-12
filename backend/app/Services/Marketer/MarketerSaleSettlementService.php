<?php

namespace App\Services\Marketer;

use App\Enums\RefundStatus;
use App\Models\MarketerListing;
use App\Models\MarketerSaleSettlement;
use App\Models\Refund;
use App\Models\SubOrder;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Settles sub-orders whose SELLER is a marketer (seller_type = 'marketer'): an influencer's own-FBN listing sold
 * directly, or sold through a peer promoter's proxy listing (the owner is the seller, the promoter earns a
 * conversion commission that the owner pays).
 *
 * The money is the one CheckoutPricingEngine already persisted on the sub-order at checkout, exactly as for vendors:
 *   net = gross (subtotal)
 *         - coupon cost carried by the seller
 *         - platform category commission (after the seller's discount)      <- charged like a vendor
 *         - VAT on that commission (country rate, only when commission_vat_enabled)  <- same VAT concept as vendor sales
 *         - gateway fee share
 *         - peer promoter commission (marketer_commission_owner = 'marketer')  <- paid by the owner
 *         - other seller-borne deductions (shipping contribution)
 *         - completed refunds charged back to the seller
 * i.e. net = sub_orders.vendor_payout - charged-back refunds. Every term is snapshotted on marketer_sale_settlements,
 * all BIGINT base units (decimal(…,4) sub-order columns are rounded half-up to whole units, never floats on a sum).
 *
 * Eligibility is the vendor payout rule (PayoutCalculationService): completed, or delivered with every item's return
 * window passed; COD only once the cash remittance is confirmed. One settlement per sub-order (unique key + row lock) =
 * idempotent. The net is credited to the marketer wallet's pending_balance and released into the spendable balance by
 * ReleaseMarketerPendingCommissionJob after `marketer_payout_clearing_days`, like conversion commissions.
 */
class MarketerSaleSettlementService
{
    /** @return Builder<SubOrder> */
    public function eligibleQuery(): Builder
    {
        return SubOrder::query()
            ->where('seller_type', 'marketer')
            ->whereNotNull('marketer_id')
            ->whereIn('status', ['delivered', 'completed'])
            ->where(function ($q) {
                $q->where('status', 'completed')
                    ->orWhere(fn ($q2) => $q2->where('status', 'delivered')
                        ->whereDoesntHave('items', fn ($i) => $i->whereNotNull('return_eligible_until')
                            ->where('return_eligible_until', '>=', now()->toDateString())));
            })
            ->where(function ($q) {
                $q->whereHas('order', fn ($o) => $o->where('payment_method', '!=', 'cod'))
                    ->orWhere('cod_remittance_confirmed', true);
            })
            ->whereNotIn('id', MarketerSaleSettlement::query()->select('sub_order_id'));
    }

    /** Settle every eligible sub-order; returns how many were credited. */
    public function settleAllEligible(): int
    {
        $count = 0;
        $this->eligibleQuery()->orderBy('created_at')->chunkById(100, function ($subOrders) use (&$count) {
            foreach ($subOrders as $subOrder) {
                if ($this->settle($subOrder)) {
                    $count++;
                }
            }
        });

        return $count;
    }

    public function settle(SubOrder $subOrder): ?MarketerSaleSettlement
    {
        return DB::transaction(function () use ($subOrder) {
            /** @var SubOrder|null $locked */
            $locked = SubOrder::whereKey($subOrder->id)->lockForUpdate()->first();
            if (! $locked
                || $locked->seller_type !== 'marketer'
                || ! $locked->marketer_id
                || ! in_array($locked->status instanceof \BackedEnum ? $locked->status->value : $locked->status, ['delivered', 'completed'], true)
                || MarketerSaleSettlement::where('sub_order_id', $locked->id)->exists()) {
                return null;
            }

            $locked->loadMissing(['order', 'items']);
            $snapshot = $this->breakdown($locked);

            $settlement = MarketerSaleSettlement::create($snapshot + [
                'marketer_id' => $locked->marketer_id,
                'sub_order_id' => $locked->id,
                'order_id' => $locked->order_id,
                'currency' => (string) $locked->order->currency,
                'credited_at' => now(),
            ]);

            // Same wallet lookup as conversion approval (one wallet per marketer; created on first credit).
            $wallet = Wallet::firstOrCreate(
                ['owner_type' => 'marketer', 'owner_id' => $locked->marketer_id],
                ['balance' => 0, 'pending_balance' => 0, 'currency' => $settlement->currency]
            );
            if (abs((float) $settlement->net_amount) > 0) {
                Wallet::whereKey($wallet->id)->increment('pending_balance', $settlement->net_amount);
            }

            return $settlement;
        });
    }

    /**
     * Release credited settlements into the spendable balance once the clearing period passed (idempotent).
     */
    public function releaseDue(int $clearingDays): int
    {
        $released = 0;
        MarketerSaleSettlement::whereNotNull('credited_at')->whereNull('released_at')
            ->where('credited_at', '<=', now()->subDays($clearingDays))
            ->chunkById(200, function ($rows) use (&$released) {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, &$released) {
                        $locked = MarketerSaleSettlement::whereKey($row->id)->lockForUpdate()->first();
                        if (! $locked || $locked->released_at) {
                            return;
                        }
                        $wallet = Wallet::where('owner_type', 'marketer')->where('owner_id', $locked->marketer_id)->lockForUpdate()->first();
                        if ($wallet && abs((float) $locked->net_amount) > 0) {
                            Wallet::whereKey($wallet->id)->decrement('pending_balance', $locked->net_amount);
                            Wallet::whereKey($wallet->id)->increment('balance', $locked->net_amount);

                            $type = $locked->net_amount > 0 ? 'credit' : 'debit';
                            $order = $locked->order?->order_number ?? '#'.substr((string) $locked->order_id, 0, 8);
                            WalletTransaction::create([
                                'wallet_id' => $wallet->id,
                                'type' => $type,
                                'direction' => $type,
                                'amount' => abs($locked->net_amount),
                                'balance_after' => (float) $wallet->fresh()->balance,
                                'currency_code' => $wallet->currency,
                                'source_type' => 'marketer_sale',
                                'source_id' => (string) $locked->id,
                                'description' => mb_substr(__('marketer.finance.tx_sale', [
                                    'order' => $order, 'gross' => Money::format($locked->gross_amount),
                                    'deductions' => Money::format($locked->gross_amount - $locked->net_amount), 'net' => Money::format($locked->net_amount),
                                ]), 0, 255),
                                'created_at' => now(),
                            ]);
                        }
                        $locked->update(['released_at' => now()]);
                        $released++;
                    });
                }
            });

        return $released;
    }

    /** @return array<string, float|string> the snapshot columns */
    public function breakdown(SubOrder $subOrder): array
    {
        $int = fn ($v): float => round((float) $v, 2);

        $gross = $int($subOrder->subtotal);
        $coupon = $int($subOrder->vendor_coupon_cost);
        $commission = $int($subOrder->platform_commission);
        $vat = $int($subOrder->commission_vat);
        $gateway = $int($subOrder->gateway_fee);
        $peer = $subOrder->marketer_commission_owner === 'marketer' ? $int($subOrder->marketer_commission) : 0;

        $currency = (string) ($subOrder->order?->currency ?? '');
        $refunds = $int(Refund::where('sub_order_id', $subOrder->id)
            ->where('vendor_charged_back', true)
            ->where('status', RefundStatus::Completed->value)
            ->when($currency !== '', fn ($q) => $q->where('currency', $currency))
            ->sum('amount'));

        $net = $int($subOrder->vendor_payout) - $refunds;
        // Whatever else the engine deducted from the seller (shipping contribution) + the refunds charged back.
        $other = $gross - $coupon - $commission - $vat - $gateway - $peer - $net;

        return [
            'sold_via' => $this->soldVia($subOrder),
            'gross_amount' => $gross,
            'coupon_cost_amount' => $coupon,
            'platform_commission_amount' => $commission,
            'commission_vat_rate' => (float) $subOrder->commission_vat_rate,
            'commission_vat_amount' => $vat,
            'gateway_fee_amount' => $gateway,
            'peer_commission_amount' => $peer,
            'other_deduction_amount' => $other,
            'net_amount' => $net,
        ];
    }

    private function soldVia(SubOrder $subOrder): string
    {
        $proxy = MarketerListing::whereIn('id', $subOrder->items->pluck('marketer_listing_id')->filter())
            ->where('source_type', 'marketer_listing')->exists();

        return $proxy ? MarketerSaleSettlement::VIA_PEER : MarketerSaleSettlement::VIA_DIRECT;
    }

    /**
     * Lifetime sales statement of one marketer (admin + marketer panels).
     *
     * @return array{gross: float, commission: float, vat: float, peer: float, other: float, net: float, count: int}
     */
    public function totalsFor(string $marketerId): array
    {
        $r = MarketerSaleSettlement::where('marketer_id', $marketerId)
            ->selectRaw('COUNT(*) c, COALESCE(SUM(gross_amount),0) g, COALESCE(SUM(platform_commission_amount),0) pc, COALESCE(SUM(commission_vat_amount),0) v,
                COALESCE(SUM(peer_commission_amount),0) p, COALESCE(SUM(gross_amount - net_amount - platform_commission_amount - commission_vat_amount - peer_commission_amount),0) o, COALESCE(SUM(net_amount),0) n')
            ->first();

        return [
            'gross' => (float) $r->g, 'commission' => (float) $r->pc, 'vat' => (float) $r->v, 'peer' => (float) $r->p,
            'other' => (float) $r->o, 'net' => (float) $r->n, 'count' => (int) $r->c,
        ];
    }
}
