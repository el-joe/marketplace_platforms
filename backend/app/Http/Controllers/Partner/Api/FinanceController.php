<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\BankAccountResource;
use App\Http\Resources\Vendor\LedgerEntryResource;
use App\Http\Resources\Vendor\PayoutDetailResource;
use App\Http\Resources\Vendor\PayoutResource;
use App\Http\Resources\Vendor\TransactionFeedItemResource;
use App\Http\Responses\ApiResponse;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\SubOrder;
use App\Models\Vendor;
use App\Models\VendorBankAccount;
use App\Models\VendorListing;
use App\Services\Vendor\FinanceService;
use App\Services\Vendor\TransactionFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinanceController extends Controller
{
    public function __construct(
        private FinanceService $financeService,
        private TransactionFeedService $transactionFeedService,
    ) {}

    private function vendor(): Vendor { return Auth::guard('vendor_api')->user()->vendor; }
    private function vendorId(): string { return Auth::guard('vendor_api')->user()->vendor_id; }

    public function summary(): JsonResponse
    {
        return ApiResponse::success($this->financeService->getSummary($this->vendor()));
    }

    public function transactions(Request $request): JsonResponse
    {
        $type    = $request->query('type');
        $feed    = $this->transactionFeedService->getFeed(
            $this->vendor(), $type,
            $request->query('date_from'), $request->query('date_to'),
            max(1, (int) $request->query('page', 1)),
            min(100, max(1, (int) $request->query('per_page', 20)))
        );

        return ApiResponse::success([
            'items'   => TransactionFeedItemResource::collection($feed['items'])->resolve(),
            'meta'    => $feed['meta'],
            'summary' => $feed['summary'],
        ]);
    }

    public function ledger(Request $request): JsonResponse
    {
        $entries = LedgerEntry::where('account_holder_type', Vendor::class)
            ->where('account_holder_id', $this->vendorId())
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

        $dateFrom = $request->filled('date_from')
            ? \Carbon\Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfMonth();

        $dateTo = $request->filled('date_to')
            ? \Carbon\Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        $baseQuery = SubOrder::where('vendor_id', $vendorId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $totals = (clone $baseQuery)->selectRaw('
            COALESCE(SUM(shipping), 0) as total_shipping_charged_to_customers,
            COALESCE(SUM(admin_subsidy_amount), 0) as total_platform_subsidy,
            COALESCE(SUM(vendor_contribution_amount), 0) as total_vendor_shipping_contribution
        ')->first();

        $shipments = (clone $baseQuery)->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->paginate(min(100, (int) $request->query('per_page', 30)));

        return ApiResponse::success([
            'currency'  => $currency,
            'date_from' => $dateFrom->toDateString(),
            'date_to'   => $dateTo->toDateString(),
            'totals'    => [
                'total_shipping_charged_to_customers' => (int) $totals->total_shipping_charged_to_customers,
                'total_platform_subsidy'              => (int) $totals->total_platform_subsidy,
                'total_vendor_shipping_contribution'  => (int) $totals->total_vendor_shipping_contribution,
            ],
            'shipments' => $shipments->through(fn (SubOrder $s) => [
                'sub_order_number'           => $s->sub_order_number,
                'order_number'               => $s->order?->order_number,
                'date'                       => $s->created_at->toDateString(),
                'shipping_charged'           => (int) $s->shipping,
                'delivery_subsidy'           => (int) $s->admin_subsidy_amount,
                'your_delivery_contribution' => (int) $s->vendor_contribution_amount,
            ]),
        ]);
    }

    public function payouts(Request $request): JsonResponse
    {
        $query = Payout::where('vendor_id', $this->vendorId())
            ->when($request->filled('status'),    fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest();

        return ApiResponse::paginated($query->paginate((int) ($request->query('per_page', 20))), PayoutResource::class);
    }

    public function showPayout(int $id): JsonResponse
    {
        $payout = Payout::where('vendor_id', $this->vendorId())
            ->with(['items', 'bankAccount'])
            ->findOrFail($id);

        return ApiResponse::success(new PayoutDetailResource($payout));
    }

    public function bankAccounts(): JsonResponse
    {
        $accounts = VendorBankAccount::where('vendor_id', $this->vendorId())->get();
        return ApiResponse::success(BankAccountResource::collection($accounts)->resolve());
    }
}
