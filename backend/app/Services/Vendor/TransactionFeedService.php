<?php

namespace App\Services\Vendor;

use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCampaignSample;
use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\Refund;
use App\Models\SubOrder;
use App\Models\Vendor;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TransactionFeedService
{
    private const EARNED_STATUSES = ['delivered', 'completed'];

    private const CACHE_TTL = 300; // 5 minutes

    public function getFeed(
        Vendor $vendor,
        ?string $type,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $perPage
    ): array {
        $cacheKey = "txn_feed_{$vendor->id}_".md5("{$type}|{$dateFrom}|{$dateTo}");

        $allItems = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($vendor, $type, $dateFrom, $dateTo) {
            $items = collect();

            if (! $type || $type === 'sale') {
                $items = $items->merge($this->fetchSales($vendor, $dateFrom, $dateTo));
            }
            if (! $type || $type === 'refund') {
                $items = $items->merge($this->fetchRefunds($vendor, $dateFrom, $dateTo));
            }
            if (! $type || $type === 'payout') {
                $items = $items->merge($this->fetchPayouts($vendor, $dateFrom, $dateTo));
            }

            if (! $type || $type === 'promotion_sample') {
                $items = $items->merge($this->fetchPromotionSamples($vendor, $dateFrom, $dateTo));
            }

            if (! $type || $type === 'influencer_fee') {
                $items = $items->merge($this->fetchInfluencerFees($vendor, $dateFrom, $dateTo));
            }

            return $items->sortByDesc('date')->values()->all();
        });

        $collection = collect($allItems);
        $total = $collection->count();

        return [
            'items' => $collection->forPage($page, $perPage)->values()->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            ],
            'summary' => $this->computeSummary($collection),
        ];
    }

    private function fetchSales(Vendor $vendor, ?string $dateFrom, ?string $dateTo): Collection
    {
        $subOrders = SubOrder::where('vendor_id', $vendor->id)
            ->whereIn('status', self::EARNED_STATUSES)
            ->when($dateFrom, fn ($q) => $q->where('delivered_at', '>=', Carbon::parse($dateFrom)->startOfDay()))
            ->when($dateTo, fn ($q) => $q->where('delivered_at', '<=', Carbon::parse($dateTo)->endOfDay()))
            ->get(['id', 'sub_order_number', 'subtotal', 'platform_commission', 'vendor_payout', 'delivered_at']);

        // Resolve which payout (if any) has settled each sub_order
        $payoutItems = PayoutItem::whereIn('sub_order_id', $subOrders->pluck('id'))
            ->with('payout:id,payout_number,status')
            ->get()
            ->keyBy('sub_order_id');

        return $subOrders->map(function ($so) use ($payoutItems) {
            $pi = $payoutItems->get($so->id);
            $payoutStatus = $pi
                ? $pi->payout->payout_number.' ('.$pi->payout->status?->value.')'
                : 'unsettled';

            return [
                'type' => 'sale',
                'date' => $so->delivered_at?->toISOString(),
                'reference' => $so->sub_order_number,
                'description' => "Order {$so->sub_order_number} commission",
                'gross' => $so->subtotal,
                'commission' => $so->platform_commission,
                'net' => $so->vendor_payout,
                'payout_status' => $payoutStatus,
            ];
        });
    }

    /**
     * Statement lines for promotion samples taken from stock: price 0, only the
     * admin sale commission (snapshotted at dispatch) is charged, once.
     */
    private function fetchPromotionSamples(Vendor $vendor, ?string $dateFrom, ?string $dateTo): Collection
    {
        $samples = MarketerCampaignSample::whereNotNull('stock_deducted_at')
            ->where('status', '!=', 'cancelled')
            ->whereHas('campaign', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->when($dateFrom, fn ($q) => $q->where('stock_deducted_at', '>=', Carbon::parse($dateFrom)->startOfDay()))
            ->when($dateTo, fn ($q) => $q->where('stock_deducted_at', '<=', Carbon::parse($dateTo)->endOfDay()))
            ->with(['campaign.vendorListing.productVariant.product', 'payoutItems.payout:id,payout_number,status'])
            ->get();

        return $samples->map(function ($s) {
            $product = $s->campaign?->vendorListing?->productVariant?->product;
            $payout = $s->payoutItems->first()?->payout;
            $commission = Money::number($s->commission_amount);

            return [
                'type' => 'promotion_sample',
                'date' => $s->stock_deducted_at?->toISOString(),
                'reference' => $s->campaign?->title,
                'description' => 'عينة ترويج مع المسوقين / Promotion sample — '.($product?->name_en ?? $product?->name_ar ?? '—').' × '.$s->quantity,
                'gross' => 0,
                'commission' => $commission,
                'net' => -$commission,
                'payout_status' => $payout ? $payout->payout_number.' ('.$payout->status?->value.')' : 'unsettled',
            ];
        });
    }

    /**
     * Influencer platform fee lines (fee + VAT + marketing fees): settled from the wallet during the
     * week, or deducted in a weekly statement (payout_items.invitation_id).
     */
    private function fetchInfluencerFees(Vendor $vendor, ?string $dateFrom, ?string $dateTo): Collection
    {
        $invitations = MarketerCampaignInvitation::whereIn('platform_fee_status', ['pending', 'paid'])
            ->where('platform_fee_payer', 'vendor')
            ->where('platform_fee_amount', '>', 0)
            ->whereHas('campaign', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->when($dateFrom, fn ($q) => $q->where('platform_fee_recorded_at', '>=', Carbon::parse($dateFrom)->startOfDay()))
            ->when($dateTo, fn ($q) => $q->where('platform_fee_recorded_at', '<=', Carbon::parse($dateTo)->endOfDay()))
            ->with(['campaign:id,title', 'marketer:id,name', 'payoutItems.payout:id,payout_number,status'])
            ->get();

        return $invitations->map(function ($inv) {
            $payout = $inv->payoutItems->first()?->payout;
            $total = $inv->platformFeeTotal();
            $status = $payout
                ? $payout->payout_number.' ('.$payout->status?->value.')'
                : ($inv->platform_fee_status === 'paid' ? 'wallet' : 'unsettled');

            return [
                'type' => 'influencer_fee',
                'date' => $inv->platform_fee_recorded_at?->toISOString(),
                'reference' => $inv->campaign?->title,
                'description' => 'رسوم مؤثر / Influencer fee — '.($inv->marketer?->name ?? '—')
                    .((float) $inv->platform_fee_marketing_amount > 0 ? ' (+ رسوم تسويق / marketing fees '.Money::format($inv->platform_fee_marketing_amount).')' : ''),
                'gross' => 0,
                'commission' => 0,
                'fee' => Money::number($inv->platform_fee_amount),
                'vat' => Money::number($inv->platform_fee_vat_amount),
                'marketing_fee' => Money::number($inv->platform_fee_marketing_amount),
                'net' => Money::number(-$total),
                'payout_status' => $status,
            ];
        });
    }

    private function fetchRefunds(Vendor $vendor, ?string $dateFrom, ?string $dateTo): Collection
    {
        // Scope to this vendor's sub_orders only — never accept vendor_id from refunds table
        // directly since refunds only carry sub_order_id (no vendor_id column).
        $vendorSubOrderIds = SubOrder::where('vendor_id', $vendor->id)->pluck('id');

        $refunds = Refund::whereIn('sub_order_id', $vendorSubOrderIds)
            ->where('vendor_charged_back', true)
            ->where('status', 'completed')
            ->when($dateFrom, fn ($q) => $q->where('updated_at', '>=', Carbon::parse($dateFrom)->startOfDay()))
            ->when($dateTo, fn ($q) => $q->where('updated_at', '<=', Carbon::parse($dateTo)->endOfDay()))
            ->with('subOrder:id,sub_order_number')
            ->get(['id', 'sub_order_id', 'amount', 'reason', 'updated_at']);

        return $refunds->map(fn ($r) => [
            'type' => 'refund',
            'date' => $r->updated_at?->toISOString(),
            'reference' => $r->subOrder?->sub_order_number,
            'description' => 'Refund — '.str_replace('_', ' ', $r->reason?->value ?? ''),
            'amount' => Money::number(-(float) $r->amount),
        ]);
    }

    private function fetchPayouts(Vendor $vendor, ?string $dateFrom, ?string $dateTo): Collection
    {
        $payouts = Payout::where('vendor_id', $vendor->id)
            ->when($dateFrom, fn ($q) => $q->where('processed_at', '>=', Carbon::parse($dateFrom)->startOfDay()))
            ->when($dateTo, fn ($q) => $q->where('processed_at', '<=', Carbon::parse($dateTo)->endOfDay()))
            ->get(['id', 'payout_number', 'period_start', 'period_end', 'net_amount', 'currency', 'receipt_url', 'processed_at']);

        return $payouts->map(fn ($p) => [
            'type' => 'payout',
            'date' => $p->processed_at?->toISOString(),
            'reference' => $p->payout_number,
            'description' => 'Payout — '.$p->period_start->toDateString().' to '.$p->period_end->toDateString(),
            'amount' => Money::number($p->net_amount),
            'currency' => $p->currency,
            'receipt_url' => $p->receipt_url,
        ]);
    }

    private function computeSummary(Collection $items): array
    {
        return [
            'total_sales' => Money::number(round((float) $items->where('type', 'sale')->sum('net'), 2)),
            'total_refunds' => Money::number(round((float) $items->where('type', 'refund')->sum('amount'), 2)),
            'total_paid_out' => Money::number(round((float) $items->where('type', 'payout')->sum('amount'), 2)),
        ];
    }
}
