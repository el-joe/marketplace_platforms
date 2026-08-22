<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Traits\HasDataTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LedgerController extends Controller
{
    use HasDataTable;

    public function index(): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ledger.view'), 403);

        $totalDebit = LedgerEntry::sum('debit');
        $totalCredit = LedgerEntry::sum('credit');

        $stats = [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => $totalDebit === $totalCredit,
            'difference' => abs($totalDebit - $totalCredit),
        ];

        $accountTypes = [
            __('admin.ledger_section.account_types.customer_payment'),
            __('admin.ledger_section.account_types.platform_revenue'),
            __('admin.ledger_section.account_types.platform_commission'),
            __('admin.ledger_section.account_types.seller_payable'),
            __('admin.ledger_section.account_types.gateway_fee'),
            __('admin.ledger_section.account_types.tax_payable'),
            __('admin.ledger_section.account_types.refund_liability'),
            __('admin.ledger_section.account_types.shipping_revenue'),
            __('admin.ledger_section.account_types.cod_clearing'),
        ];

        return view('admin.ledger.index', compact('stats', 'accountTypes'));
    }

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ledger.view'), 403);

        $query = LedgerEntry::query();

        $query = $this->applyFilters($query, $request, [
            'account_type' => fn($q, $v) => $q->where('account_type', $v),
            'currency' => fn($q, $v) => $q->where('currency', $v),
            'date_from' => fn($q, $v) => $q->whereDate('created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('created_at', '<=', $v),
            'reference_type' => fn($q, $v) => $q->where('reference_type', $v),
            'transaction_group_id' => fn($q, $v) => $q->where('transaction_group_id', $v),
        ]);

        $columns = [
            0 => ['orderable_column' => 'ledger_entries.created_at'],
            1 => ['searchable_columns' => ['ledger_entries.transaction_group_id'], 'orderable_column' => 'ledger_entries.transaction_group_id'],
            2 => ['orderable_column' => 'ledger_entries.account_type'],
            3 => [],
            4 => ['orderable_column' => 'ledger_entries.debit'],
            5 => ['orderable_column' => 'ledger_entries.credit'],
            6 => [],
            7 => [],
            8 => ['searchable_columns' => ['ledger_entries.description']],
        ];

        return $this->dataTableResponse($request, $query, $columns, function ($entry) {
            $typeBadgeColors = [
                'customer_payment' => 'bg-blue-100 text-blue-700',
                'platform_revenue' => 'bg-green-100 text-green-700',
                'platform_commission' => 'bg-emerald-100 text-emerald-700',
                'seller_payable' => 'bg-indigo-100 text-indigo-700',
                'gateway_fee' => 'bg-gray-100 text-gray-600',
                'tax_payable' => 'bg-yellow-100 text-yellow-700',
                'refund_liability' => 'bg-orange-100 text-orange-700',
                'shipping_revenue' => 'bg-cyan-100 text-cyan-700',
                'cod_clearing' => 'bg-purple-100 text-purple-700',
            ];
            $badgeClass = $typeBadgeColors[$entry->account_type] ?? 'bg-gray-100 text-gray-700';

            $debitHtml = $entry->debit > 0
                ? '<span class="text-green-600 font-semibold tabular-nums">' . number_format($entry->debit / 100, 2) . '</span>'
                : '<span class="text-gray-300">—</span>';

            $creditHtml = $entry->credit > 0
                ? '<span class="text-blue-600 font-semibold tabular-nums">' . number_format($entry->credit / 100, 2) . '</span>'
                : '<span class="text-gray-300">—</span>';

            $holderHtml = '<span class="text-gray-400 text-xs">—</span>';
            if ($entry->account_holder_id) {
                $holderType = strtolower(class_basename($entry->account_holder_type ?? ''));
                $holderShort = e(Str::upper($holderType) . ' #' . substr($entry->account_holder_id, 0, 8));
                if ($holderType === 'vendor') {
                    $holderHtml = '<a href="' . route('admin.vendors.show', $entry->account_holder_id) . '" class="text-primary-600 hover:underline text-xs font-mono">' . $holderShort . '</a>';
                } elseif ($holderType === 'customer') {
                    $holderHtml = '<a href="' . route('admin.customers.show', $entry->account_holder_id) . '" class="text-primary-600 hover:underline text-xs font-mono">' . $holderShort . '</a>';
                } else {
                    $holderHtml = '<span class="font-mono text-xs text-gray-600">' . $holderShort . '</span>';
                }
            }

            $groupId = $entry->transaction_group_id;
            $groupShort = substr($groupId, 0, 8);
            $groupHtml = '<button type="button" class="font-mono text-xs text-primary-600 hover:underline js-view-group" data-group-id="' . e($groupId) . '">' . e($groupShort) . '…</button>';

            $refId = $entry->reference_id ?? '';
            $refShort = strlen($refId) > 8 ? substr($refId, 0, 8) . '…' : $refId;
            $referenceHtml = '<span class="font-mono text-xs text-gray-600">' . e($entry->reference_type) . '<br><span class="text-gray-400">' . e($refShort) . '</span></span>';

            return [
                'DT_RowId' => 'ledger-' . $entry->id,
                'created_at' => $entry->created_at->format('M d, Y H:i'),
                'transaction_group_id' => $groupHtml,
                'account_type' => '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $badgeClass . '">' . e(str_replace('_', ' ', $entry->account_type)) . '</span>',
                'account_holder' => $holderHtml,
                'debit' => $debitHtml,
                'credit' => $creditHtml,
                'currency' => '<span class="text-xs font-mono text-gray-600">' . e($entry->currency) . '</span>',
                'reference' => $referenceHtml,
                'description' => '<span class="text-sm text-gray-700">' . e(Str::limit($entry->description, 80)) . '</span>',
            ];
        });
    }

    public function transactionGroup(string $groupId): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ledger.view'), 403);

        if (!preg_match('/^[0-9a-f-]{36}$/i', $groupId)) {
            return response()->json(['message' => 'Invalid group ID format.'], 422);
        }

        $entries = LedgerEntry::where('transaction_group_id', $groupId)
            ->orderBy('created_at')
            ->get();

        if ($entries->isEmpty()) {
            return response()->json(['message' => 'No entries found for this transaction group.'], 404);
        }

        $totalDebit = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');
        $balanced = $totalDebit === $totalCredit;

        return response()->json([
            'group_id' => $groupId,
            'balanced' => $balanced,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'total_debit_f' => number_format($totalDebit / 100, 2),
            'total_credit_f' => number_format($totalCredit / 100, 2),
            'difference' => abs($totalDebit - $totalCredit),
            'difference_f' => number_format(abs($totalDebit - $totalCredit) / 100, 2),
            'entries' => $entries->map(fn($e) => [
                'id' => $e->id,
                'account_type' => $e->account_type,
                'account_holder_type' => $e->account_holder_type,
                'account_holder_id' => $e->account_holder_id,
                'debit' => $e->debit,
                'credit' => $e->credit,
                'debit_f' => number_format($e->debit / 100, 2),
                'credit_f' => number_format($e->credit / 100, 2),
                'currency' => $e->currency,
                'reference_type' => $e->reference_type,
                'reference_id' => $e->reference_id,
                'description' => $e->description,
                'created_at' => $e->created_at->format('M d, Y H:i:s'),
            ]),
        ]);
    }
}
