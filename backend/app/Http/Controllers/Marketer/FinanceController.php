<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerCampaignConversion;
use App\Models\MarketerCampaignInvitation;
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

        $conversions = MarketerCampaignConversion::with(['campaign.vendorListing.productVariant.product', 'order', 'invitation'])
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

        return view('marketer.finance.commissions', compact('conversions', 'totalEarned', 'pendingEarnings'));
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
                'error' => 'لم يتم تحديد الدولة لهذا الحساب، لا يمكن تحديد عملة المحفظة.',
            ]);
        }

        $wallet = $this->walletService->getOrCreateWallet('marketer', $marketer->id, $currency);
        $transactions = $wallet->transactions()->paginate(20);
        $withdrawalRequests = $wallet->withdrawalRequests()->latest()->take(10)->get();

        return view('marketer.finance.wallet', compact('wallet', 'transactions', 'withdrawalRequests'));
    }

    public function requestWithdrawal(Request $request)
    {
        $marketer = $this->marketer();
        $marketer->loadMissing('country');
        $currency = $marketer->country?->currency_code;

        if (! $currency) {
            return back()->with('error', 'لم يتم تحديد الدولة لهذا الحساب، لا يمكن تحديد عملة المحفظة.');
        }

        $wallet = $this->walletService->getOrCreateWallet('marketer', $marketer->id, $currency);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'bank_name' => ['required', 'string', 'max:150'],
            'bank_iban' => ['required', 'string', 'max:50'],
        ]);

        $this->walletService->requestWithdrawal($wallet, (int) $data['amount'], [
            'bank_name' => $data['bank_name'],
            'bank_iban' => $data['bank_iban'],
        ]);

        return back()->with('success', 'تم تقديم طلب السحب بنجاح');
    }
}
