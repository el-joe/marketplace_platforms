<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\BankAccountRequest;
use App\Http\Resources\Vendor\BankAccountResource;
use App\Http\Resources\Vendor\LedgerEntryResource;
use App\Http\Resources\Vendor\PayoutDetailResource;
use App\Http\Resources\Vendor\PayoutResource;
use App\Http\Resources\Vendor\TransactionFeedItemResource;
use App\Services\Vendor\TransactionFeedService;
use App\Http\Responses\ApiResponse;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\SubOrder;
use App\Models\Vendor;
use App\Models\VendorBankAccount;
use App\Models\VendorListing;
use App\Services\Vendor\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function __construct(
        private readonly FinanceService $financeService,
        private readonly TransactionFeedService $transactionFeedService,
    ) {}

    private function vendor(): Vendor
    {
        /** @var \App\Models\VendorAdmin $auth */
        $auth = auth('vendor')->user();
        /** @var Vendor $vendor */
        $vendor = $auth->vendor;
        return $vendor;
    }

    private function vendorId(): string
    {
        /** @var \App\Models\VendorAdmin $auth */
        $auth = auth('vendor')->user();
        return $auth->vendor_id;
    }

    public function transactions(Request $request): JsonResponse
    {
        $type     = $request->query('type');
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');
        $page     = max(1, (int) $request->query('page', 1));
        $perPage  = min(100, max(1, (int) $request->query('per_page', 20)));

        if ($type && !in_array($type, ['sale', 'refund', 'payout'], true)) {
            return ApiResponse::error('Invalid type filter. Must be sale, refund, or payout.', [], 422);
        }

        $feed = $this->transactionFeedService->getFeed(
            $this->vendor(), $type, $dateFrom, $dateTo, $page, $perPage
        );

        return ApiResponse::success([
            'items'   => TransactionFeedItemResource::collection($feed['items'])->resolve(),
            'meta'    => $feed['meta'],
            'summary' => $feed['summary'],
        ]);
    }

    public function summary(): JsonResponse
    {
        return ApiResponse::success($this->financeService->getSummary($this->vendor()));
    }

    public function payouts(Request $request): JsonResponse
    {
        if (!auth('vendor')->user()->can('finance.payouts.view')) {
            return ApiResponse::error('You do not have permission to view payouts.', [], 403);
        }

        $vendorId = $this->vendorId();

        $query = Payout::where('vendor_id', $vendorId)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('date_from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('date_to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest();

        $payouts = $query->paginate((int) ($request->query('per_page', 20)));

        return ApiResponse::paginated($payouts, PayoutResource::class);
    }

    public function showPayout(int $id): JsonResponse
    {
        if (!auth('vendor')->user()->can('finance.payouts.view')) {
            return ApiResponse::error('You do not have permission to view payouts.', [], 403);
        }

        $payout = Payout::where('vendor_id', $this->vendorId())
            ->with(['items', 'bankAccount'])
            ->findOrFail($id);

        return ApiResponse::success(new PayoutDetailResource($payout));
    }

    public function payoutInvoice(int $id): JsonResponse
    {
        if (!auth('vendor')->user()->can('finance.payouts.view')) {
            return ApiResponse::error('You do not have permission to view payouts.', [], 403);
        }

        $payout = Payout::where('vendor_id', $this->vendorId())->findOrFail($id);

        if (!$payout->receipt_url) {
            return ApiResponse::error('Invoice not yet available for this payout. It is generated after the payout completes.', [], 404);
        }

        return ApiResponse::success(['invoice_url' => $payout->receipt_url]);
    }

    public function ledger(Request $request): JsonResponse
    {
        $vendorId = $this->vendorId();

        // Strictly scoped: only entries where account_holder is this vendor.
        // Never leaks platform-side or other vendors' accounts even by id.
        $entries = LedgerEntry::where('account_holder_type', Vendor::class)
            ->where('account_holder_id', $vendorId)
            ->latest()
            ->paginate((int) ($request->query('per_page', 30)));

        return ApiResponse::paginated($entries, LedgerEntryResource::class);
    }

    public function commissionRates(): JsonResponse
    {
        return ApiResponse::success($this->financeService->resolveCommissionRates($this->vendor()));
    }

    public function salesReport(Request $request): JsonResponse
    {
        $vendorId = $this->vendorId();
        $currency = $this->vendor()->country?->currency_code ?? '';

        $dateFrom = $request->query('date_from')
            ? \Carbon\Carbon::parse($request->query('date_from'))->startOfDay()
            : now()->startOfMonth();

        $dateTo = $request->query('date_to')
            ? \Carbon\Carbon::parse($request->query('date_to'))->endOfDay()
            : now()->endOfDay();

        $baseQuery = SubOrder::where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $totals = (clone $baseQuery)
            ->selectRaw('
                COALESCE(SUM(shipping), 0) as total_shipping_charged_to_customers,
                COALESCE(SUM(admin_subsidy_amount), 0) as total_platform_subsidy,
                COALESCE(SUM(vendor_contribution_amount), 0) as total_vendor_shipping_contribution,
                COALESCE(SUM(shipping + admin_subsidy_amount + vendor_contribution_amount), 0) as total_actual_shipping_cost
            ')
            ->first();

        $hasVendorContribution = VendorListing::where('vendor_id', $vendorId)
            ->where('vendor_covers_delivery', true)
            ->exists();

        $perPage = min(100, max(1, (int) $request->query('per_page', 30)));

        $shipments = (clone $baseQuery)
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::success([
            'currency'                 => $currency,
            'date_from'                => $dateFrom->toDateString(),
            'date_to'                  => $dateTo->toDateString(),
            'has_vendor_contribution'  => $hasVendorContribution,
            'totals'                   => [
                'total_shipping_charged_to_customers'  => (int) $totals->total_shipping_charged_to_customers,
                'total_platform_subsidy'                => (int) $totals->total_platform_subsidy,
                'total_vendor_shipping_contribution'    => (int) $totals->total_vendor_shipping_contribution,
                'total_actual_shipping_cost'            => (int) $totals->total_actual_shipping_cost,
            ],
            'shipments' => $shipments->through(fn (SubOrder $s) => [
                'sub_order_number'          => $s->sub_order_number,
                'order_number'              => $s->order?->order_number,
                'date'                      => $s->created_at->toDateString(),
                'shipping_charged'          => (int) $s->shipping,
                'delivery_subsidy'          => (int) $s->admin_subsidy_amount,
                'your_delivery_contribution' => (int) $s->vendor_contribution_amount,
            ]),
        ]);
    }

    // ── Bank Accounts ─────────────────────────────────────────────────────────

    public function bankAccounts(): JsonResponse
    {
        $accounts = VendorBankAccount::where('vendor_id', $this->vendorId())->get();

        return ApiResponse::success(BankAccountResource::collection($accounts)->resolve());
    }

    public function storeBankAccount(BankAccountRequest $request): JsonResponse
    {
        $account = $this->financeService->createBankAccount($this->vendor(), $request->validated());

        return ApiResponse::success(new BankAccountResource($account), 'Bank account added successfully.', 201);
    }

    public function setPrimaryBankAccount(string $id): JsonResponse
    {
        $account = VendorBankAccount::where('vendor_id', $this->vendorId())->findOrFail($id);

        $this->financeService->setPrimaryAccount($this->vendor(), $account);

        return ApiResponse::success(new BankAccountResource($account->fresh()), 'Primary bank account updated.');
    }

    public function deleteBankAccount(string $id): JsonResponse
    {
        $vendor  = $this->vendor();
        $account = VendorBankAccount::where('vendor_id', $vendor->id)->findOrFail($id);

        $this->financeService->deleteBankAccount($vendor, $account);

        return ApiResponse::success(null, 'Bank account removed.');
    }
}
