<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\WalletTransaction;
use App\Services\MarketingFeeReportService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * enhancement.md P-16 task 2 (API parity): mobile/partner-app equivalent
 * of Http\Controllers\Marketer\FinanceController (web portal). Delegates
 * all wallet/withdrawal mutation to WalletService — the same service the
 * web controller and Admin\WalletController::approveWithdrawal use — so
 * there is exactly one withdrawal implementation.
 */
class FinanceController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    private function marketer(): Marketer
    {
        return Auth::guard('marketer_api')->user()->marketer;
    }

    public function commissions(Request $request): JsonResponse
    {
        $marketer = $this->marketer();
        $invitationIds = MarketerCampaignInvitation::where('marketer_id', $marketer->id)->pluck('id');

        $conversions = MarketerCampaignConversion::whereIn('invitation_id', $invitationIds)
            ->with('order', 'invitation')
            ->latest()
            ->paginate(25);

        return response()->json(['success' => true, 'data' => $conversions]);
    }

    /**
     * Same report figures as the web Reports page: earnings per campaign (gross, platform commission fee, net, totals
     * per currency) and the influencer's per-campaign fixed amount lines. Campaign fees are owner money and are never
     * exposed to the marketer.
     */
    public function reports(): JsonResponse
    {
        $marketer = $this->marketer();
        $earnings = app(MarketingFeeReportService::class)->marketerEarnings($marketer->id);

        return response()->json([
            'success' => true,
            'data' => [
                'campaigns' => $earnings['campaigns'],
                'fixed_amounts' => $earnings['fixed']->values(),
                'transaction_labels' => collect(WalletTransaction::SOURCE_LABELS)
                    ->mapWithKeys(fn ($key, $source) => [$source => __('marketer.finance.tx_source.'.$key)])->all(),
            ],
        ]);
    }

    public function wallet(): JsonResponse
    {
        $marketer = $this->marketer();
        $marketer->loadMissing('country');
        $currency = $marketer->country?->currency_code;

        if (! $currency) {
            return response()->json(['success' => false, 'message' => 'No country/currency set for this marketer.'], 422);
        }

        $wallet = $this->walletService->getOrCreateWallet('marketer', $marketer->id, $currency);

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $wallet->balance,
                'pending_balance' => $wallet->pending_balance,
                'currency' => $wallet->currency,
                'transactions' => $wallet->transactions()->take(20)->get()->map(fn ($tx) => array_merge($tx->toArray(), [
                    'source_label' => $tx->sourceLabel(),
                ]))->values(),
                'fixed_amounts' => app(\App\Services\Marketer\MarketerEarningsService::class)->fixedAmountLines($marketer->id)->values(),
                'withdrawal_requests' => $wallet->withdrawalRequests()->take(10)->get(),
            ],
        ]);
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        $marketer = $this->marketer();
        $marketer->loadMissing('country');
        $currency = $marketer->country?->currency_code;

        if (! $currency) {
            return response()->json(['success' => false, 'message' => 'No country/currency set for this marketer.'], 422);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'bank_name' => ['required', 'string', 'max:150'],
            'bank_iban' => ['required', 'string', 'max:50'],
        ]);

        $wallet = $this->walletService->getOrCreateWallet('marketer', $marketer->id, $currency);

        $withdrawal = $this->walletService->requestWithdrawal($wallet, round((float) $data['amount'], 2), [
            'bank_name' => $data['bank_name'],
            'bank_iban' => $data['bank_iban'],
        ]);

        return response()->json(['success' => true, 'data' => $withdrawal]);
    }
}
