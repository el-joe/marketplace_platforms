<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
use App\Models\WalletTransaction;
use App\Services\Marketer\PromotionPenaltyService;
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
     * P8: same report figures as the web Reports page: fee-share earnings per campaign (+ totals per currency)
     * and affiliate campaign totals. Marketing fees are vendor money and are never exposed to the marketer.
     */
    public function reports(): JsonResponse
    {
        $marketer = $this->marketer();
        $report = app(MarketingFeeReportService::class);
        $affiliate = $report->marketerAffiliateTotals($marketer->id);

        $stripFees = fn (array $row) => collect($row)->except('marketing_fees')->all();

        return response()->json([
            'success' => true,
            'data' => [
                'fee_share' => $report->marketerFeeShare($marketer->id),
                'affiliate' => [
                    'rows' => $affiliate['rows']->map($stripFees)->values(),
                    'totals' => collect($affiliate['totals'])->map($stripFees)->all(),
                ],
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
                'fee_share' => app(MarketingFeeReportService::class)->marketerFeeShare($marketer->id)['totals'][$wallet->currency]
                    ?? ['credited' => 0, 'reversed' => 0, 'clawback' => 0, 'net' => 0, 'pending' => 0],
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

        // Pending promotion fines are taken before the payout request.
        app(PromotionPenaltyService::class)->collect($marketer);

        $wallet = $this->walletService->getOrCreateWallet('marketer', $marketer->id, $currency);

        $withdrawal = $this->walletService->requestWithdrawal($wallet, round((float) $data['amount'], 2), [
            'bank_name' => $data['bank_name'],
            'bank_iban' => $data['bank_iban'],
        ]);

        return response()->json(['success' => true, 'data' => $withdrawal]);
    }
}
