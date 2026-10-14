<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\SubOrder;
use App\Services\MarketingFeeReportService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayoutController extends Controller
{
    use HasDataTable;
    use HasExport;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): View|StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportPayouts($request);
        }

        $payouts = $this->buildPayoutsQuery($request)
            ->orderByDesc('period_end')
            ->paginate(20)
            ->withQueryString();

        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();

        $thisMonthEarnings = SubOrder::where('vendor_id', $this->vendorId())
            ->whereIn('status', ['completed', 'delivered'])
            ->whereBetween('created_at', [$startOfMonth, $now])
            ->sum('vendor_payout');

        $pendingAmount = Payout::where('vendor_id', $this->vendorId())
            ->where('status', 'pending')
            ->sum('net_amount');

        $lastPayout = Payout::where('vendor_id', $this->vendorId())
            ->where('status', 'completed')
            ->orderByDesc('processed_at')
            ->first(['net_amount', 'processed_at', 'payout_number']);

        return view('partner.payouts.index', compact(
            'payouts',
            'thisMonthEarnings',
            'pendingAmount',
            'lastPayout',
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────────────────────────────────

    private function buildPayoutsQuery(Request $request): Builder
    {
        $query = Payout::where('vendor_id', $this->vendorId());

        $query = $this->applyFilters($query, $request, [
            'status' => fn ($q, $v) => $q->where('status', $v),
            'currency' => fn ($q, $v) => $q->where('currency', $v),
            'date_from' => fn ($q, $v) => $q->whereDate('period_end', '>=', $v),
            'date_to' => fn ($q, $v) => $q->whereDate('period_end', '<=', $v),
        ]);

        return $query;
    }

    private function exportPayouts(Request $request): StreamedResponse
    {
        $payouts = $this->buildPayoutsQuery($request)->orderByDesc('period_end')->get();

        $headers = ['Batch #', 'Amount', 'Currency', 'Status', 'Date'];

        $rows = $payouts->map(fn ($row) => [
            $row->payout_number,
            number_format($row->net_amount, 2),
            $row->currency,
            $row->status instanceof \BackedEnum ? $row->status->value : $row->status,
            optional($row->period_end)->format('Y-m-d'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('payouts', $headers, $rows),
            'csv' => $this->exportCsv('payouts', $headers, $rows),
            'word' => $this->exportWord('payouts', 'Payouts', $rows),
            default => abort(400, __('common.invalid_export_format')),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────────

    public function show(string $payoutNumber): View
    {
        $payout = Payout::where('vendor_id', $this->vendorId())
            ->where('payout_number', $payoutNumber)
            ->with([
                'items.subOrder',
                'bankAccount',
            ])
            ->firstOrFail();

        // P8: this statement's marketer-campaign charges as separate figures (+ fee refunds credited in its period).
        $influencerItems = $payout->items->where('item_type', 'influencer_fee');
        $statementMarketing = [$payout->currency => [
            'influencer_fees' => round($influencerItems->sum(fn ($i) => abs((float) $i->net) - (float) $i->marketing_fee_amount), 2),
            'marketing_fees' => round((float) $influencerItems->sum('marketing_fee_amount'), 2),
            'affiliate_marketing_fees' => round($payout->items->where('item_type', 'affiliate_marketing_fee')->sum(fn ($i) => abs((float) $i->net)), 2),
            'refunds' => $payout->period_start && $payout->period_end
                ? (app(MarketingFeeReportService::class)->vendorTotals($this->vendorId(), $payout->period_start, $payout->period_end)[$payout->currency]['refunds'] ?? 0.0)
                : 0.0,
        ]];

        return view('partner.payouts.show', compact('payout', 'statementMarketing'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Earnings Summary (AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    public function earningsSummary(): JsonResponse
    {
        $vendorId = $this->vendorId();
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfYear = $now->copy()->startOfYear();

        $thisMonth = SubOrder::where('vendor_id', $vendorId)
            ->whereIn('status', ['completed', 'delivered'])
            ->whereBetween('created_at', [$startOfMonth, $now])
            ->sum('vendor_payout');

        $pending = Payout::where('vendor_id', $vendorId)
            ->where('status', 'pending')
            ->sum('net_amount');

        $ytd = SubOrder::where('vendor_id', $vendorId)
            ->whereIn('status', ['completed', 'delivered'])
            ->whereBetween('created_at', [$startOfYear, $now])
            ->sum('vendor_payout');

        $lastPayout = Payout::where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->orderByDesc('processed_at')
            ->first(['net_amount', 'processed_at', 'payout_number']);

        return response()->json([
            'this_month' => round($thisMonth, 2),
            'pending' => round($pending, 2),
            'ytd' => round($ytd, 2),
            'last_payout' => $lastPayout ? [
                'amount' => round($lastPayout->net_amount, 2),
                'date' => $lastPayout->processed_at?->format('Y-m-d'),
                'number' => $lastPayout->payout_number,
            ] : null,
        ]);
    }
}
