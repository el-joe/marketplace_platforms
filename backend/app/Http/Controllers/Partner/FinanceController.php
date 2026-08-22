<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\SubOrder;
use App\Traits\HasExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinanceController extends Controller
{
    use HasExport;

    public function transactions(Request $request)
    {
        $vendorAdmin = Auth::guard('vendor')->user();
        $vendor      = $vendorAdmin->vendor;
        $vendorId    = $vendor->id;
        $currency    = $vendor->country?->currency_code ?? '';

        // ── Date range ────────────────────────────────────────────────────────
        $dateFrom = $request->input('date_from')
            ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->startOfMonth();

        $dateTo = $request->input('date_to')
            ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfDay();

        $type = $request->input('type', 'all'); // all | sales | refunds | payouts

        // ── Summary stats for the selected range ──────────────────────────────
        $totalSales = SubOrder::where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('vendor_payout');

        $totalRefunds = Refund::whereHas('subOrder', fn ($q) => $q->where('vendor_id', $vendorId))
            ->where('status', 'completed')
            ->where('vendor_charged_back', true)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('amount');

        $totalPaidOut = Payout::where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->whereBetween('processed_at', [$dateFrom, $dateTo])
            ->sum('net_amount');

        // ── Build unified transaction collection (paginated) ──────────────────
        // We pull three query sets and merge them in PHP. For large datasets
        // this page would need a UNION query; for now the vendor's transaction
        // volume is modest, so PHP-merge + paginate is acceptable.

        $perPage = 30;
        $page    = max(1, (int) $request->input('page', 1));

        $rows = collect();

        if (in_array($type, ['all', 'sales'])) {
            SubOrder::where('vendor_id', $vendorId)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->with(['order:id,order_number', 'payoutItems.payout:id,payout_number'])
                ->get()
                ->each(function ($so) use (&$rows) {
                    $payoutItem = $so->payoutItems->first();
                    $rows->push([
                        'type'          => 'sale',
                        'date'          => $so->created_at,
                        'reference'     => $so->sub_order_number,
                        'description'   => 'مبيعات – ' . ($so->order?->order_number ?? ''),
                        'amount'        => $so->vendor_payout,
                        'gross'         => $so->subtotal,
                        'commission'    => $so->platform_commission,
                        'net'           => $so->vendor_payout,
                        'payout_number' => $payoutItem?->payout?->payout_number,
                        'receipt_url'   => null,
                    ]);
                });
        }

        if (in_array($type, ['all', 'refunds'])) {
            Refund::whereHas('subOrder', fn ($q) => $q->where('vendor_id', $vendorId))
                ->where('status', 'completed')
                ->where('vendor_charged_back', true)
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->with('subOrder:id,sub_order_number,order_id', 'subOrder.order:id,order_number')
                ->get()
                ->each(function ($ref) use (&$rows) {
                    $rows->push([
                        'type'          => 'refund',
                        'date'          => $ref->created_at,
                        'reference'     => $ref->subOrder?->sub_order_number ?? ('REF-' . $ref->id),
                        'description'   => 'مرتجع – ' . ($ref->subOrder?->order?->order_number ?? ''),
                        'amount'        => -$ref->amount,
                        'gross'         => null,
                        'commission'    => null,
                        'net'           => null,
                        'payout_number' => null,
                        'receipt_url'   => null,
                    ]);
                });
        }

        if (in_array($type, ['all', 'payouts'])) {
            Payout::where('vendor_id', $vendorId)
                ->where('status', 'completed')
                ->whereBetween('processed_at', [$dateFrom, $dateTo])
                ->get()
                ->each(function ($po) use (&$rows) {
                    $rows->push([
                        'type'          => 'payout',
                        'date'          => $po->processed_at ?? $po->created_at,
                        'reference'     => $po->payout_number,
                        'description'   => 'تحويل بنكي',
                        'amount'        => -$po->net_amount,
                        'gross'         => null,
                        'commission'    => null,
                        'net'           => null,
                        'payout_number' => $po->payout_number,
                        'receipt_url'   => $po->receipt_url,
                    ]);
                });
        }

        // Sort newest first then paginate manually
        $sorted     = $rows->sortByDesc('date')->values();
        $total      = $sorted->count();
        $transactions = new \Illuminate\Pagination\LengthAwarePaginator(
            $sorted->forPage($page, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('partner.finance.transactions', compact(
            'vendor',
            'currency',
            'transactions',
            'totalSales',
            'totalRefunds',
            'totalPaidOut',
            'dateFrom',
            'dateTo',
            'type',
        ));
    }

    public function salesReport(Request $request)
    {
        $vendorAdmin = Auth::guard('vendor')->user();
        $vendor      = $vendorAdmin->vendor;
        $vendorId    = $vendor->id;
        $currency    = $vendor->country?->currency_code ?? '';

        $dateFrom = $request->input('date_from')
            ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->startOfMonth();

        $dateTo = $request->input('date_to')
            ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfDay();

        $baseQuery = SubOrder::where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $totals = (clone $baseQuery)
            ->selectRaw('
                COALESCE(SUM(shipping), 0) as total_shipping_charged,
                COALESCE(SUM(admin_subsidy_amount), 0) as total_platform_subsidy,
                COALESCE(SUM(vendor_contribution_amount), 0) as total_vendor_contribution,
                COALESCE(SUM(shipping + admin_subsidy_amount + vendor_contribution_amount), 0) as total_actual_shipping_cost
            ')
            ->first();

        $hasVendorContribution = \App\Models\VendorListing::where('vendor_id', $vendorId)
            ->where('vendor_covers_delivery', true)
            ->exists();

        $exceptionalTotals = (clone $baseQuery)
            ->where('shipping_gap', '>', 0)
            ->selectRaw('
                COALESCE(SUM(vendor_contribution_amount), 0) as total_exceptional_deduction,
                COUNT(*) as exceptional_orders
            ')
            ->first();

        $hasExceptionalDeduction = $exceptionalTotals->exceptional_orders > 0;

        $shipments = (clone $baseQuery)
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('partner.finance.sales-report', compact(
            'vendor',
            'currency',
            'totals',
            'shipments',
            'hasVendorContribution',
            'exceptionalTotals',
            'hasExceptionalDeduction',
            'dateFrom',
            'dateTo',
        ));
    }

    public function exportSalesReport(Request $request)
    {
        $vendorAdmin = Auth::guard('vendor')->user();
        $vendor      = $vendorAdmin->vendor;
        $vendorId    = $vendor->id;
        $currency    = $vendor->country?->currency_code ?? '';

        $dateFrom = $request->input('date_from')
            ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->startOfMonth();

        $dateTo = $request->input('date_to')
            ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfDay();

        $shipments = SubOrder::where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->get();

        $headers = [
            'Date', 'Sub-order Number', 'Order Number',
            'Shipping Charged', 'Delivery Subsidy', 'Your Delivery Contribution',
            'Exceptional Zone Deduction', 'Currency',
        ];

        $rows = $shipments->map(fn($shipment) => [
            $shipment->created_at->format('Y-m-d'),
            $shipment->sub_order_number,
            $shipment->order?->order_number,
            number_format($shipment->shipping / 100, 2, '.', ''),
            number_format($shipment->admin_subsidy_amount / 100, 2, '.', ''),
            number_format($shipment->vendor_contribution_amount / 100, 2, '.', ''),
            $shipment->shipping_gap > 0 ? number_format($shipment->vendor_contribution_amount / 100, 2, '.', '') : '',
            $currency,
        ]);

        $totalExceptionalDeduction = $shipments->where('shipping_gap', '>', 0)->sum('vendor_contribution_amount');
        $rows->push(['', '', '', '', '', '', 'Exceptional Zone Deduction Subtotal (' . $currency . ')', number_format($totalExceptionalDeduction / 100, 2, '.', '')]);

        $filename = 'sales-report-' . $dateFrom->toDateString() . '-to-' . $dateTo->toDateString();
        $format = $request->input('format', 'csv');

        return match ($format) {
            'excel' => $this->exportExcel($filename, $headers, $rows),
            'word' => $this->exportWord($filename, 'Sales Report', $rows),
            'csv' => $this->exportCsv($filename, $headers, $rows),
            default => abort(400, __('common.invalid_export_format')),
        };
    }
}
