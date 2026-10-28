<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Api\Marketer\CommissionRuleController;
use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerAdmin;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerSaleSettlement;
use App\Notifications\Marketer\PayoutScheduled;
use App\Services\Marketer\MarketerSaleSettlementService;
use App\Services\Marketer\MarketerEarningsService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    public function commissions(Request $request): View
    {
        $marketer = $this->marketer();
        $invitationIds = MarketerCampaignInvitation::where('marketer_id', $marketer->id)->pluck('id');

        $conversions = MarketerCampaignConversion::with([
            'campaign.vendorListing.productVariant' => fn ($q) => $q->withTrashed(),
            'campaign.vendorListing.productVariant.product' => fn ($q) => $q->withTrashed(),
            'order',
            'invitation',
        ])
            ->whereIn('invitation_id', $invitationIds)
            ->when($request->filled('campaign_id'), fn ($q) => $q->where('campaign_id', $request->campaign_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $totalEarned = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)
            ->where('commissioned', true)->sum('commission_amount');

        $pendingEarnings = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)
            ->where('commissioned', false)->sum('commission_amount');

        $commissionRules = CommissionRuleController::rulesFor($marketer);

        [$statements, $totals] = $this->statementFor($conversions->getCollection());

        $salesService = app(MarketerSaleSettlementService::class);
        $salesTotals = $salesService->totalsFor($marketer->id);
        $sales = MarketerSaleSettlement::with('order:id,order_number')
            ->where('marketer_id', $marketer->id)->latest()->paginate(15, ['*'], 'sales_page')->withQueryString();

        $fixedLines = app(MarketerEarningsService::class)->fixedAmountLines($marketer);

        return view('marketer.finance.commissions', compact('conversions', 'totalEarned', 'pendingEarnings', 'commissionRules', 'statements', 'totals', 'salesTotals', 'sales', 'fixedLines', 'marketer'));
    }

    /** @return array{0: array<string, array>, 1: array{gross: float, fee: float, net: float}} */
    private function statementFor($conversions): array
    {
        $service = app(MarketerEarningsService::class);
        $rows = [];
        $totals = ['gross' => 0.0, 'fee' => 0.0, 'net' => 0.0];
        foreach ($conversions as $c) {
            $rows[$c->id] = $b = $service->breakdown($c);
            if (in_array($c->status, ['pending', 'approved'], true)) {
                foreach (array_keys($totals) as $k) {
                    $totals[$k] = round($totals[$k] + $b[$k], 2);
                }
            }
        }

        return [$rows, $totals];
    }

    public function wallet(): View
    {
        $marketer = $this->marketer();
        $marketer->loadMissing('country');
        $currency = $marketer->country?->currency_code;

        if (! $currency) {
            return view('marketer.finance.wallet', [
                'wallet' => null,
                'transactions' => collect(),
                'withdrawalRequests' => collect(),
                'error' => __('marketer.msg.wallet_country_missing'),
            ]);
        }

        $wallet = $this->walletService->getOrCreateWallet('marketer', $marketer->id, $currency);
        $transactions = $wallet->transactions()->paginate(20);
        $withdrawalRequests = $wallet->withdrawalRequests()->latest()->take(10)->get();

        // Lifetime statement of approved (non-reversed) conversions: gross -> platform commission fee -> net.
        $approved = MarketerCampaignConversion::with(['campaign', 'invitation.marketer.marketerProfile'])
            ->whereIn('invitation_id', MarketerCampaignInvitation::where('marketer_id', $marketer->id)->pluck('id'))
            ->where('status', 'approved')->get();
        [, $statementTotals] = $this->statementFor($approved);

        $salesTotals = app(MarketerSaleSettlementService::class)->totalsFor($marketer->id);

        // Influencer fixed amounts, one line per campaign (reserved / paid / released).
        $fixedLines = app(MarketerEarningsService::class)->fixedAmountLines($marketer);

        return view('marketer.finance.wallet', compact('wallet', 'transactions', 'withdrawalRequests', 'statementTotals', 'salesTotals', 'fixedLines', 'marketer'));
    }

    public function requestWithdrawal(Request $request)
    {
        $marketer = $this->marketer();
        $marketer->loadMissing('country');
        $currency = $marketer->country?->currency_code;

        if (! $currency) {
            return back()->with('error', __('marketer.msg.wallet_country_missing'));
        }

        $wallet = $this->walletService->getOrCreateWallet('marketer', $marketer->id, $currency);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'bank_name' => ['required', 'string', 'max:150'],
            'bank_iban' => ['required', 'string', 'max:50'],
        ]);

        $withdrawal = $this->walletService->requestWithdrawal($wallet, round((float) $data['amount'], 2), [
            'bank_name' => $data['bank_name'],
            'bank_iban' => $data['bank_iban'],
        ]);

        /** @var MarketerAdmin $marketerAdmin */
        $marketerAdmin = Auth::guard('marketer')->user();
        $marketerAdmin->notify(new PayoutScheduled($withdrawal, $marketerAdmin->id));

        return back()->with('success', __('marketer.msg.withdrawal_submitted'));
    }
}
