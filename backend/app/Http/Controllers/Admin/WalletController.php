<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryAgentCodSettlementStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WalletWithdrawalRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\DeliveryAgent;
use App\Models\DeliveryAgentCodSettlement;
use App\Models\Wallet;
use App\Models\WalletWithdrawalRequest;
use App\Services\WalletService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WalletController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function __construct(private WalletService $walletService) {}

    public function index(Request $request): \Illuminate\View\View|StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportWallets($request);
        }

        return view('admin.wallets.index');
    }

    /**
     * Base query for the wallets listing.
     *
     * Wallet.owner is polymorphic (owner_type/owner_id, resolved via a manual
     * accessor, not an Eloquent relation) so we left-join customers directly
     * for search/display when owner_type = customer. Other owner types show
     * their raw owner_id since wallets aren't limited to customers.
     */
    private function buildWalletsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Wallet::query()
            ->leftJoin('customers', function ($join) {
                $join->on('customers.id', '=', 'wallets.owner_id')
                    ->where('wallets.owner_type', '=', 'customer');
            })
            ->select([
                'wallets.id',
                'wallets.owner_type',
                'wallets.owner_id',
                'wallets.balance',
                'wallets.pending_balance',
                'wallets.currency',
                'wallets.is_frozen',
                'wallets.frozen_reason',
                'wallets.created_at',
                'customers.name as customer_name',
                'customers.email as customer_email',
            ]);

        return $this->applyFilters($query, $request, [
            'search' => fn($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('customers.name', 'like', "%{$v}%")
                    ->orWhere('customers.email', 'like', "%{$v}%");
            }),
            'owner_type' => fn($q, $v) => $q->where('wallets.owner_type', $v),
            'currency' => fn($q, $v) => $q->where('wallets.currency', $v),
            'status' => fn($q, $v) => $v === 'frozen'
                ? $q->where('wallets.is_frozen', true)
                : $q->where('wallets.is_frozen', false),
            'date_from' => fn($q, $v) => $q->whereDate('wallets.created_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('wallets.created_at', '<=', $v),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $columns = [
            0 => ['searchable_columns' => ['customers.name', 'customers.email'], 'orderable_column' => 'customers.name'],
            1 => ['orderable_column' => 'wallets.owner_type'],
            2 => ['orderable_column' => 'wallets.balance'],
            3 => ['orderable_column' => 'wallets.pending_balance'],
            4 => ['orderable_column' => 'wallets.currency'],
            5 => ['orderable_column' => 'wallets.is_frozen'],
            6 => [],
        ];

        $query = $this->buildWalletsQuery($request);

        return $this->dataTableResponse($request, $query, $columns, function ($row) {
            return [
                'id' => $row->id,
                'owner' => e($row->customer_name ?? $row->owner_id),
                'owner_type' => $row->owner_type->value,
                'balance' => number_format($row->balance, 2),
                'pending_balance' => number_format($row->pending_balance, 2),
                'currency' => strtoupper($row->currency),
                'status' => $row->is_frozen ? 'frozen' : 'active',
                'show_url' => route('admin.wallets.show', $row->id),
            ];
        });
    }

    /**
     * Excel export of wallets matching current filters.
     *
     * Balances are grouped/exported by currency — NEVER summed across
     * currencies. Rows are ordered by currency so a spreadsheet reader can
     * visually group each currency's wallets together.
     */
    private function exportWallets(Request $request): StreamedResponse
    {
        $wallets = $this->buildWalletsQuery($request)
            ->orderBy('wallets.currency')
            ->orderByDesc('wallets.created_at')
            ->get();

        $headers = ['Customer', 'Email', 'Balance', 'Currency', 'Status'];

        $rows = $wallets->map(fn($row) => [
            $row->customer_name ?? $row->owner_id,
            $row->customer_email ?? '',
            number_format($row->balance, 2),
            strtoupper($row->currency),
            $row->is_frozen ? 'Frozen' : 'Active',
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('wallets', $headers, $rows),
            'csv' => $this->exportCsv('wallets', $headers, $rows),
            'word' => $this->exportWord('wallets', 'Wallets', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    public function show(Wallet $wallet)
    {
        $transactions = $wallet->transactions()->paginate(30);
        return view('admin.wallets.show', compact('wallet', 'transactions'));
    }

    public function adjustBalance(Request $request, Wallet $wallet)
    {
        $data = $request->validate([
            'type'        => ['required', Rule::enum(WalletTransactionType::class)],
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $amountCents = (int) round($data['amount'] * 100);

        if ($data['type'] === WalletTransactionType::Credit->value) {
            $this->walletService->credit($wallet, $amountCents, 'admin_adjustment', null, $data['description'], $admin->id);
        } else {
            $this->walletService->debit($wallet, $amountCents, 'admin_adjustment', null, $data['description'], $admin->id);
        }

        return back()->with('success', 'Balance adjusted successfully.');
    }

    public function freezeWallet(Request $request, Wallet $wallet)
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $wallet->update(['is_frozen' => true, 'frozen_reason' => $request->reason]);
        return back()->with('success', 'Wallet frozen.');
    }

    public function unfreezeWallet(Wallet $wallet)
    {
        $wallet->update(['is_frozen' => false, 'frozen_reason' => null]);
        return back()->with('success', 'Wallet unfrozen.');
    }

    public function withdrawalRequests(Request $request)
    {
        $requests = WalletWithdrawalRequest::with('wallet')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(25);

        return view('admin.wallets.withdrawal-requests', compact('requests'));
    }

    public function approveWithdrawal(WalletWithdrawalRequest $withdrawal)
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $this->walletService->approveWithdrawal($withdrawal, $admin);
        return back()->with('success', 'Withdrawal approved.');
    }

    public function rejectWithdrawal(Request $request, WalletWithdrawalRequest $withdrawal)
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $this->walletService->rejectWithdrawal($withdrawal, $admin, $request->reason);
        return back()->with('success', 'Withdrawal rejected.');
    }

    public function markWithdrawalProcessed(WalletWithdrawalRequest $withdrawal)
    {
        $withdrawal->update(['status' => WalletWithdrawalRequestStatus::Processed, 'processed_at' => now()]);
        return back()->with('success', 'Marked as processed.');
    }

    public function codSettlements(Request $request)
    {
        $settlements = DeliveryAgentCodSettlement::with('agent')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(25);

        $agents = DeliveryAgent::select('id', 'name')->orderBy('name')->get();

        return view('admin.wallets.cod-settlements', compact('settlements', 'agents'));
    }

    public function runCodSettlement(Request $request)
    {
        $data = $request->validate([
            'agent_id'     => ['required', 'exists:delivery_agents,id'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $agent = DeliveryAgent::findOrFail($data['agent_id']);
        $settlement = $this->walletService->settleAgentCod($agent, $data['period_start'], $data['period_end']);

        return back()->with('success', "Settlement created (net: {$settlement->net_to_remit} cents).");
    }

    public function markSettlementSettled(DeliveryAgentCodSettlement $settlement)
    {
        $settlement->update(['status' => DeliveryAgentCodSettlementStatus::Settled, 'settled_at' => now()]);
        return back()->with('success', 'Settlement marked as settled.');
    }
}
