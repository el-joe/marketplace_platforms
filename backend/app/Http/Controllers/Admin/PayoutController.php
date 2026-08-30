<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Services\LedgerService;
use App\Services\PayoutCalculationService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Notifications\Vendor\PayoutProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayoutController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function __construct(
        private readonly PayoutCalculationService $calculationService,
        private readonly LedgerService $ledgerService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Index / Listing
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): View|StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportPayouts($request);
        }

        return view('admin.payouts.index', [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Payouts'],
            ],
        ]);
    }

    private function buildPayoutsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Payout::query()
            ->leftJoin('vendors as v', 'v.id', '=', 'payouts.vendor_id')
            ->select([
                'payouts.id',
                'payouts.payout_number',
                'payouts.status',
                'payouts.currency',
                'payouts.gross_sales',
                'payouts.commission',
                'payouts.net_amount',
                'payouts.payout_method',
                'payouts.period_start',
                'payouts.period_end',
                'payouts.processed_at',
                'payouts.created_at',
                'v.store_name as vendor_name',
                'v.id as v_id',
            ]);

        return $this->applyFilters($query, $request, [
            'search'     => fn ($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('v.store_name', 'like', "%{$v}%")
                    ->orWhere('payouts.payout_number', 'like', "%{$v}%");
            }),
            'status'     => fn ($q, $v) => $q->where('payouts.status', $v),
            'vendor_id'  => fn ($q, $v) => $q->where('payouts.vendor_id', $v),
            'currency'   => fn ($q, $v) => $q->where('payouts.currency', $v),
            'date_from'  => fn ($q, $v) => $q->whereDate('payouts.created_at', '>=', $v),
            'date_to'    => fn ($q, $v) => $q->whereDate('payouts.created_at', '<=', $v),
            'min_amount' => fn ($q, $v) => $q->where('payouts.net_amount', '>=', (int) round((float) $v)),
        ]);
    }

    /**
     * Excel export of payouts matching the current filters.
     * Rows keep each payout's own currency — amounts are never summed across currencies.
     */
    private function exportPayouts(Request $request): StreamedResponse
    {
        $payouts = $this->buildPayoutsQuery($request)->orderByDesc('payouts.created_at')->get();

        $headers = ['Batch #', 'Vendor', 'Amount', 'Currency', 'Status', 'Date'];

        $rows = $payouts->map(fn ($row) => [
            $row->payout_number,
            $row->vendor_name,
            number_format($row->net_amount, 2),
            strtoupper($row->currency),
            $row->status?->value,
            optional($row->created_at)->format('d M Y H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('payouts', $headers, $rows),
            'csv' => $this->exportCsv('payouts', $headers, $rows),
            'word' => $this->exportWord('payouts', 'Payouts', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    public function datatable(Request $request): JsonResponse
    {
        $columns = $this->columnDefinitions();

        $query = $this->buildPayoutsQuery($request);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            return [
                'id'              => $row->id,
                'payout_number'   => $row->payout_number,
                'vendor_name'     => e($row->vendor_name ?? '—'),
                'net_formatted'   => strtoupper($row->currency) . ' ' . number_format($row->net_amount, 2),
                'gross_formatted' => strtoupper($row->currency) . ' ' . number_format($row->gross_sales, 2),
                'payout_method'   => $row->payout_method,
                'status'          => $row->status?->value,
                'period'          => $row->period_start . ' → ' . $row->period_end,
                'processed_at'    => $row->processed_at,
                'show_url'        => route('admin.payouts.show', $row->id),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show (detail)
    // ─────────────────────────────────────────────────────────────────────────

    public function show(Payout $payout): View
    {
        $payout->load([
            'vendor',
            'bankAccount',
            'approvedByAdmin',
            'items.subOrder',
        ]);

        $ledgerEntries = LedgerEntry::where('reference_type', 'payout')
            ->where('reference_id', (string) $payout->id)
            ->orderBy('created_at')
            ->get();

        return view('admin.payouts.show', [
            'payout'        => $payout,
            'ledgerEntries' => $ledgerEntries,
            'breadcrumbs'   => [
                ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['label' => 'Payouts', 'url' => route('admin.payouts.index')],
                ['label' => '#' . $payout->payout_number],
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Actions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Approve a pending payout.
     */
    public function approve(Request $request, Payout $payout): JsonResponse
    {
        if ($payout->status !== PayoutStatus::Pending) {
            return response()->json(['message' => 'Only pending payouts can be approved.'], 422);
        }

        $request->validate(['notes' => 'nullable|string|max:500']);

        DB::transaction(function () use ($payout, $request) {
            $payout->update([
                'status'               => PayoutStatus::Approved,
                'approved_by_admin_id' => auth('admin')->id(),
            ]);

            // Record double-entry: seller_payable (debit) ↔ platform_revenue (credit)
            $groupId = $this->ledgerService->newGroupId();
            $this->ledgerService->record($groupId, [
                [
                    'account_type'        => 'seller_payable',
                    'account_holder_type' => 'vendor',
                    'account_holder_id'   => $payout->vendor_id,
                    'debit'               => $payout->net_amount,
                    'credit'              => 0,
                    'currency'            => $payout->currency,
                    'reference_type'      => 'payout',
                    'reference_id'        => (string) $payout->id,
                    'description'         => "Payout approved: {$payout->payout_number}",
                ],
                [
                    'account_type'        => 'platform_revenue',
                    'account_holder_type' => null,
                    'account_holder_id'   => null,
                    'debit'               => 0,
                    'credit'              => $payout->net_amount,
                    'currency'            => $payout->currency,
                    'reference_type'      => 'payout',
                    'reference_id'        => (string) $payout->id,
                    'description'         => "Payout approved: {$payout->payout_number}",
                ],
            ]);
        });

        return response()->json(['success' => true, 'message' => 'Payout approved successfully.']);
    }

    /**
     * Mark an approved payout as processed / completed.
     */
    public function process(Request $request, Payout $payout): JsonResponse
    {
        if ($payout->status !== PayoutStatus::Approved) {
            return response()->json(['message' => 'Only approved payouts can be processed.'], 422);
        }

        $request->validate([
            'gateway_reference' => 'nullable|string|max:255',
            'receipt_url'       => 'nullable|url|max:500',
        ]);

        try {
            $payout->update([
                'status'            => PayoutStatus::Completed,
                'gateway_reference' => $request->gateway_reference,
                'receipt_url'       => $request->receipt_url,
                'processed_at'      => now(),
            ]);

            Notification::send($payout->vendor->vendorAdmins, new PayoutProcessed($payout));

            return response()->json(['success' => true, 'message' => 'Payout marked as completed.']);
        } catch (\Throwable $e) {
            Log::error('Payout process failed', ['payout' => $payout->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to process payout.'], 500);
        }
    }

    /**
     * Put a payout on hold.
     */
    public function hold(Request $request, Payout $payout): JsonResponse
    {
        if (in_array($payout->status, [PayoutStatus::Completed, PayoutStatus::Failed], true)) {
            return response()->json(['message' => 'Cannot hold a completed or failed payout.'], 422);
        }

        $request->validate(['reason' => 'required|string|max:500']);

        $payout->update([
            'status'        => PayoutStatus::OnHold,
            'failed_reason' => $request->reason,
        ]);

        return response()->json(['success' => true, 'message' => 'Payout placed on hold.']);
    }

    /**
     * Recalculate amounts for a pending payout.
     */
    public function recalculate(Request $request, Payout $payout): JsonResponse
    {
        if (!in_array($payout->status, [PayoutStatus::Pending, PayoutStatus::OnHold], true)) {
            return response()->json(['message' => 'Only pending or on-hold payouts can be recalculated.'], 422);
        }

        $payout->load('vendor');

        try {
            $calc = $this->calculationService->calculateForVendor(
                $payout->vendor,
                \Carbon\Carbon::parse($payout->period_start),
                \Carbon\Carbon::parse($payout->period_end)
            );

            $payout->update([
                'gross_sales'          => $calc['gross_sales'],
                'commission'           => $calc['commission'],
                'gateway_fee_deducted' => $calc['gateway_fee_deducted'],
                'refunds_deducted'     => $calc['refunds_deducted'],
                'chargebacks_deducted' => $calc['chargebacks_deducted'],
                'storage_fees'         => $calc['storage_fees'],
                'ad_fees'              => $calc['ad_fees'],
                'other_adjustments'    => $calc['other_adjustments'],
                'net_amount'           => $calc['net_amount'],
            ]);

            return response()->json([
                'success'      => true,
                'message'      => 'Payout recalculated.',
                'net_amount'   => $calc['net_amount'],
                'net_formatted' => strtoupper($payout->currency) . ' ' . number_format($calc['net_amount'], 2),
            ]);
        } catch (\Throwable $e) {
            Log::error('Payout recalculation failed', ['payout' => $payout->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Recalculation failed.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function columnDefinitions(): array
    {
        return [
            [
                'title'              => 'Payout #',
                'data'               => 'payout_number',
                'name'               => 'payout_number',
                'searchable_columns' => ['payouts.payout_number'],
                'orderable_column'   => 'payouts.payout_number',
                'render'             => 'function(data,t,row){return "<a href=\""+row.show_url+"\" class=\"font-medium text-primary-600 hover:text-primary-800 hover:underline\">"+data+"</a>";}',
            ],
            [
                'title'    => 'Vendor',
                'data'     => 'vendor_name',
                'name'     => 'vendor_name',
                'orderable_column'   => 'v.store_name',
                'searchable_columns' => ['v.store_name'],
            ],
            [
                'title'           => 'Period',
                'data'            => 'period',
                'name'            => 'period',
                'orderable_column' => 'payouts.period_start',
                'searchable'      => false,
            ],
            [
                'title'           => 'Gross Sales',
                'data'            => 'gross_formatted',
                'name'            => 'gross_formatted',
                'orderable_column' => 'payouts.gross_sales',
                'searchable'      => false,
                'className'       => 'text-right',
            ],
            [
                'title'           => 'Net Amount',
                'data'            => 'net_formatted',
                'name'            => 'net_formatted',
                'orderable_column' => 'payouts.net_amount',
                'searchable'      => false,
                'className'       => 'text-right font-semibold',
            ],
            [
                'title'    => 'Method',
                'data'     => 'payout_method',
                'name'     => 'payout_method',
                'orderable_column' => 'payouts.payout_method',
                'searchable' => false,
                'render'   => 'Renderers.badge({
                    bank_transfer: { label: "Bank Transfer", color: "primary" },
                    wallet:        { label: "Wallet",        color: "primary" },
                    paypal:        { label: "PayPal",        color: "primary" }
                })',
            ],
            [
                'title'    => 'Status',
                'data'     => 'status',
                'name'     => 'status',
                'orderable_column' => 'payouts.status',
                'searchable' => false,
                'render'   => 'Renderers.badge({
                    pending:    { label: "Pending",    color: "gray"    },
                    approved:   { label: "Approved",   color: "primary" },
                    processing: { label: "Processing", color: "primary" },
                    completed:  { label: "Completed",  color: "success" },
                    failed:     { label: "Failed",     color: "danger"  },
                    on_hold:    { label: "On Hold",    color: "warning" }
                })',
            ],
            [
                'title'           => 'Processed',
                'data'            => 'processed_at',
                'name'            => 'processed_at',
                'orderable_column' => 'payouts.processed_at',
                'searchable'      => false,
                'render'          => 'function(data){return data ? Renderers.dateAgo(data) : "<span class=\"text-gray-300\">—</span>";}',
            ],
            [
                'title'      => '',
                'data'       => 'actions',
                'name'       => 'actions',
                'orderable'  => false,
                'searchable' => false,
                'className'  => 'text-right',
                'render'     => 'Renderers.actions([
                    { type: "link", label: "View", url: ":show_url", class: "btn-primary btn-sm" }
                ])',
            ],
        ];
    }
}
