<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignFeeSettlement;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCampaignSample;
use App\Models\Payout;
use App\Models\Refund;
use App\Models\SubOrder;
use App\Models\VendorListing;
use App\Services\MarketingFeeReportService;
use App\Support\Money;
use App\Traits\HasExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    use HasExport;

    public function transactions(Request $request)
    {
        $vendorAdmin = Auth::guard('vendor')->user();
        $vendor = $vendorAdmin->vendor;
        $vendorId = $vendor->id;
        $currency = $vendor->country?->currency_code ?? '';

        // ── Date range ────────────────────────────────────────────────────────
        $dateFrom = $request->input('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->startOfMonth();

        $dateTo = $request->input('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
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
        $page = max(1, (int) $request->input('page', 1));

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
                        'type' => 'sale',
                        'date' => $so->created_at,
                        'reference' => $so->sub_order_number,
                        'description' => 'مبيعات – '.($so->order?->order_number ?? ''),
                        'amount' => $so->vendor_payout,
                        'gross' => $so->subtotal,
                        'commission' => $so->platform_commission,
                        'net' => $so->vendor_payout,
                        'payout_number' => $payoutItem?->payout?->payout_number,
                        'receipt_url' => null,
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
                        'type' => 'refund',
                        'date' => $ref->created_at,
                        'reference' => $ref->subOrder?->sub_order_number ?? ('REF-'.$ref->id),
                        'description' => 'مرتجع – '.($ref->subOrder?->order?->order_number ?? ''),
                        'amount' => -$ref->amount,
                        'gross' => null,
                        'commission' => null,
                        'net' => null,
                        'payout_number' => null,
                        'receipt_url' => null,
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
                        'type' => 'payout',
                        'date' => $po->processed_at ?? $po->created_at,
                        'reference' => $po->payout_number,
                        'description' => 'تحويل بنكي',
                        'amount' => -$po->net_amount,
                        'gross' => null,
                        'commission' => null,
                        'net' => null,
                        'payout_number' => $po->payout_number,
                        'receipt_url' => $po->receipt_url,
                    ]);
                });
        }

        // Promotion samples: 0-priced statement lines; only the admin commission is charged.
        if (in_array($type, ['all', 'samples'])) {
            MarketerCampaignSample::whereNotNull('stock_deducted_at')
                ->where('status', '!=', 'cancelled')
                ->whereHas('campaign', fn ($q) => $q->where('vendor_id', $vendorId))
                ->whereBetween('stock_deducted_at', [$dateFrom, $dateTo])
                ->with('campaign:id,title,vendor_listing_id', 'payoutItems.payout:id,payout_number')
                ->get()
                ->each(function ($sample) use (&$rows) {
                    $commission = Money::number($sample->commission_amount);
                    $rows->push([
                        'type' => 'promotion_sample',
                        'date' => $sample->stock_deducted_at,
                        'reference' => $sample->campaign?->title ?: ('#'.substr((string) $sample->campaign_id, 0, 8)),
                        'description' => __('partner.finance.promotion_sample_line', ['qty' => $sample->quantity]),
                        'amount' => -$commission,
                        'gross' => 0,
                        'commission' => $commission,
                        'net' => -$commission,
                        'payout_number' => null,
                        'receipt_url' => null,
                    ]);
                });
        }

        // v3 campaign fee: charged once when the campaign goes live (wallet first, otherwise the next statement).
        if (in_array($type, ['all', 'influencer_fees', 'campaign_fees'])) {
            MarketerCampaign::where('vendor_id', $vendorId)
                ->whereIn('campaign_fee_status', ['pending', 'paid'])
                ->where('marketing_fee_total', '>', 0)
                ->whereBetween(DB::raw('COALESCE(campaign_fee_paid_at, updated_at)'), [$dateFrom, $dateTo])
                ->with('payoutItems.payout:id,payout_number')
                ->get()
                ->each(function ($campaign) use (&$rows) {
                    $fee = Money::number($campaign->marketing_fee_total);
                    $rows->push([
                        'type' => 'campaign_fee',
                        'date' => $campaign->campaign_fee_paid_at ?? $campaign->updated_at,
                        'reference' => $campaign->title ?: ('#'.substr((string) $campaign->id, 0, 8)),
                        'description' => __('marketer_campaigns.finance.campaign_fee_line', [
                            'status' => $campaign->campaign_fee_status === 'paid'
                                ? __('partner.finance.fee_settled_'.($campaign->campaign_fee_paid_via ?: 'wallet'))
                                : __('partner.finance.fee_unsettled'),
                        ]),
                        'amount' => -$fee,
                        'gross' => 0,
                        'commission' => 0,
                        'net' => -$fee,
                        'payout_number' => $campaign->payoutItems->first()?->payout?->payout_number,
                        'receipt_url' => null,
                    ]);
                });
        }

        // Influencer platform fees (+VAT): settled from the wallet during the week or
        // deducted in the weekly statement; still-pending ones show as unsettled.
        if (in_array($type, ['all', 'influencer_fees'])) {
            MarketerCampaignInvitation::whereIn('platform_fee_status', ['pending', 'paid', 'refunded'])
                ->where('platform_fee_payer', 'vendor')
                ->where('platform_fee_amount', '>', 0)
                ->whereHas('campaign', fn ($q) => $q->where('vendor_id', $vendorId))
                ->whereBetween('platform_fee_recorded_at', [$dateFrom, $dateTo])
                ->with('campaign:id,title', 'marketer:id,name', 'payoutItems.payout:id,payout_number')
                ->get()
                ->each(function ($inv) use (&$rows) {
                    $total = $inv->platformFeeTotal();
                    $rows->push([
                        'type' => 'influencer_fee',
                        'date' => $inv->platform_fee_recorded_at,
                        'reference' => $inv->campaign?->title ?: ('#'.substr((string) $inv->campaign_id, 0, 8)),
                        'description' => __($inv->platform_fee_marketing_amount > 0 ? 'partner.finance.influencer_fee_line_with_marketing' : 'partner.finance.influencer_fee_line', [
                            'name' => $inv->marketer?->name ?? '—',
                            'fee' => Money::format($inv->platform_fee_amount),
                            'vat' => Money::format($inv->platform_fee_vat_amount),
                            'marketing' => Money::format($inv->platform_fee_marketing_amount),
                            'rate' => (float) $inv->platform_fee_vat_rate,
                            'status' => in_array($inv->platform_fee_status, ['paid', 'refunded'], true)
                                ? __('partner.finance.fee_settled_'.($inv->platform_fee_paid_via ?: 'wallet'))
                                : __('partner.finance.fee_unsettled'),
                        ]),
                        'amount' => -$total,
                        'gross' => 0,
                        'commission' => 0,
                        'net' => -$total,
                        'payout_number' => $inv->payoutItems->first()?->payout?->payout_number,
                        'receipt_url' => null,
                    ]);

                    // P7: the refund of a paid fee (wallet credit) is its own positive line.
                    if ($inv->platform_fee_refunded_at) {
                        $refund = Money::number($inv->platform_fee_refund_amount);
                        $rows->push([
                            'type' => 'influencer_fee_refund',
                            'date' => $inv->platform_fee_refunded_at,
                            'reference' => $inv->campaign?->title ?: ('#'.substr((string) $inv->campaign_id, 0, 8)),
                            'description' => __('partner.finance.influencer_fee_refund_line', ['name' => $inv->marketer?->name ?? '—']),
                            'amount' => $refund,
                            'gross' => 0,
                            'commission' => 0,
                            'net' => $refund,
                            'payout_number' => null,
                            'receipt_url' => null,
                        ]);
                    }
                });
        }

        // Affiliate marketing fees (settlements): paid from the wallet at once, or deducted in the weekly statement.
        if (in_array($type, ['all', 'affiliate_fees'])) {
            MarketerCampaignFeeSettlement::where('vendor_id', $vendorId)
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->with('campaign:id,title', 'payoutItems.payout:id,payout_number')
                ->get()
                ->each(function ($settlement) use (&$rows) {
                    $amount = Money::number($settlement->amount);
                    $refund = $settlement->isRefund();
                    $signed = $refund ? $amount : -$amount;
                    $rows->push([
                        'type' => $refund ? 'affiliate_marketing_fee_refund' : 'affiliate_marketing_fee',
                        'date' => $settlement->created_at,
                        'reference' => $settlement->campaign?->title ?: ('#'.substr((string) $settlement->campaign_id, 0, 8)),
                        'description' => $refund
                            ? __('marketing_fees.affiliate.finance_refund_line')
                            : __('marketing_fees.affiliate.finance_line', [
                                'status' => $settlement->status === 'paid'
                                    ? __('partner.finance.fee_settled_'.($settlement->paid_via ?: 'wallet'))
                                    : __('partner.finance.fee_unsettled'),
                            ]),
                        'amount' => $signed,
                        'gross' => 0,
                        'commission' => 0,
                        'net' => $signed,
                        'payout_number' => $settlement->payoutItems->first()?->payout?->payout_number,
                        'receipt_url' => null,
                    ]);
                });
        }

        // Sort newest first then paginate manually
        $sorted = $rows->sortByDesc('date')->values();
        $total = $sorted->count();
        $transactions = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $marketingTotals = app(MarketingFeeReportService::class)->vendorTotals($vendorId, $dateFrom, $dateTo);

        return view('partner.finance.transactions', compact(
            'marketingTotals',
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
        $vendor = $vendorAdmin->vendor;
        $vendorId = $vendor->id;
        $currency = $vendor->country?->currency_code ?? '';

        $dateFrom = $request->input('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->startOfMonth();

        $dateTo = $request->input('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfDay();

        $baseQuery = SubOrder::where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $totals = (clone $baseQuery)
            ->selectRaw('
                COALESCE(SUM(shipping), 0) as total_shipping_charged,
                COALESCE(SUM(vendor_contribution_amount), 0) as total_shipping_contribution,
                COALESCE(SUM(vendor_contribution_amount), 0) as total_vendor_contribution,
                COALESCE(SUM(shipping + vendor_contribution_amount), 0) as total_actual_shipping_cost
            ')
            ->first();

        $hasVendorContribution = VendorListing::where('vendor_id', $vendorId)
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
        $vendor = $vendorAdmin->vendor;
        $vendorId = $vendor->id;
        $currency = $vendor->country?->currency_code ?? '';

        $dateFrom = $request->input('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->startOfMonth();

        $dateTo = $request->input('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfDay();

        $shipments = SubOrder::where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->get();

        $headers = [
            'Date', 'Sub-order Number', 'Order Number',
            'Shipping Charged', 'Shipping Contribution', 'Your Delivery Contribution',
            'Exceptional Zone Deduction', 'Currency',
        ];

        $rows = $shipments->map(fn ($shipment) => [
            $shipment->created_at->format('Y-m-d'),
            $shipment->sub_order_number,
            $shipment->order?->order_number,
            number_format($shipment->shipping, 2, '.', ''),
            number_format($shipment->vendor_contribution_amount, 2, '.', ''),
            number_format($shipment->vendor_contribution_amount, 2, '.', ''),
            $shipment->shipping_gap > 0 ? number_format($shipment->vendor_contribution_amount, 2, '.', '') : '',
            $currency,
        ]);

        $totalExceptionalDeduction = $shipments->where('shipping_gap', '>', 0)->sum('vendor_contribution_amount');
        $rows->push(['', '', '', '', '', '', 'Exceptional Zone Deduction Subtotal ('.$currency.')', number_format($totalExceptionalDeduction, 2, '.', '')]);

        $filename = 'sales-report-'.$dateFrom->toDateString().'-to-'.$dateTo->toDateString();
        $format = $request->input('format', 'csv');

        return match ($format) {
            'excel' => $this->exportExcel($filename, $headers, $rows),
            'word' => $this->exportWord($filename, 'Sales Report', $rows),
            'csv' => $this->exportCsv($filename, $headers, $rows),
            default => abort(400, __('common.invalid_export_format')),
        };
    }
}
