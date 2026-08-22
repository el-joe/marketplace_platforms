<?php

namespace App\Services\Vendor;

use App\Models\Payout;
use App\Models\PayoutItem;
use App\Models\Refund;
use App\Models\SubOrder;
use App\Models\Vendor;
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
        $cacheKey = "txn_feed_{$vendor->id}_" . md5("{$type}|{$dateFrom}|{$dateTo}");

        $allItems = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($vendor, $type, $dateFrom, $dateTo) {
            $items = collect();

            if (!$type || $type === 'sale') {
                $items = $items->merge($this->fetchSales($vendor, $dateFrom, $dateTo));
            }
            if (!$type || $type === 'refund') {
                $items = $items->merge($this->fetchRefunds($vendor, $dateFrom, $dateTo));
            }
            if (!$type || $type === 'payout') {
                $items = $items->merge($this->fetchPayouts($vendor, $dateFrom, $dateTo));
            }

            return $items->sortByDesc('date')->values()->all();
        });

        $collection = collect($allItems);
        $total      = $collection->count();

        return [
            'items'   => $collection->forPage($page, $perPage)->values()->all(),
            'meta'    => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $total > 0 ? (int) ceil($total / $perPage) : 1,
            ],
            'summary' => $this->computeSummary($collection),
        ];
    }

    private function fetchSales(Vendor $vendor, ?string $dateFrom, ?string $dateTo): Collection
    {
        $subOrders = SubOrder::where('vendor_id', $vendor->id)
            ->whereIn('status', self::EARNED_STATUSES)
            ->when($dateFrom, fn ($q) => $q->where('delivered_at', '>=', Carbon::parse($dateFrom)->startOfDay()))
            ->when($dateTo,   fn ($q) => $q->where('delivered_at', '<=', Carbon::parse($dateTo)->endOfDay()))
            ->get(['id', 'sub_order_number', 'subtotal', 'platform_commission', 'vendor_payout', 'delivered_at']);

        // Resolve which payout (if any) has settled each sub_order
        $payoutItems = PayoutItem::whereIn('sub_order_id', $subOrders->pluck('id'))
            ->with('payout:id,payout_number,status')
            ->get()
            ->keyBy('sub_order_id');

        return $subOrders->map(function ($so) use ($payoutItems) {
            $pi            = $payoutItems->get($so->id);
            $payoutStatus  = $pi
                ? $pi->payout->payout_number . ' (' . $pi->payout->status?->value . ')'
                : 'unsettled';

            return [
                'type'             => 'sale',
                'date'             => $so->delivered_at?->toISOString(),
                'reference'        => $so->sub_order_number,
                'description'      => "Order {$so->sub_order_number} commission",
                'gross'      => $so->subtotal,
                'commission' => $so->platform_commission,
                'net'        => $so->vendor_payout,
                'payout_status'    => $payoutStatus,
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
            ->when($dateTo,   fn ($q) => $q->where('updated_at', '<=', Carbon::parse($dateTo)->endOfDay()))
            ->with('subOrder:id,sub_order_number')
            ->get(['id', 'sub_order_id', 'amount', 'reason', 'updated_at']);

        return $refunds->map(fn ($r) => [
            'type'         => 'refund',
            'date'         => $r->updated_at?->toISOString(),
            'reference'    => $r->subOrder?->sub_order_number,
            'description'  => 'Refund — ' . str_replace('_', ' ', $r->reason?->value ?? ''),
            'amount' => -(int) $r->amount,
        ]);
    }

    private function fetchPayouts(Vendor $vendor, ?string $dateFrom, ?string $dateTo): Collection
    {
        $payouts = Payout::where('vendor_id', $vendor->id)
            ->when($dateFrom, fn ($q) => $q->where('processed_at', '>=', Carbon::parse($dateFrom)->startOfDay()))
            ->when($dateTo,   fn ($q) => $q->where('processed_at', '<=', Carbon::parse($dateTo)->endOfDay()))
            ->get(['id', 'payout_number', 'period_start', 'period_end', 'net_amount', 'currency', 'receipt_url', 'processed_at']);

        return $payouts->map(fn ($p) => [
            'type'         => 'payout',
            'date'         => $p->processed_at?->toISOString(),
            'reference'    => $p->payout_number,
            'description'  => 'Payout — ' . $p->period_start->toDateString() . ' to ' . $p->period_end->toDateString(),
            'amount' => $p->net_amount,
            'currency'     => $p->currency,
            'receipt_url'  => $p->receipt_url,
        ]);
    }

    private function computeSummary(Collection $items): array
    {
        return [
            'total_sales'    => (int) $items->where('type', 'sale')->sum('net'),
            'total_refunds'  => (int) $items->where('type', 'refund')->sum('amount'),
            'total_paid_out' => (int) $items->where('type', 'payout')->sum('amount'),
        ];
    }
}
