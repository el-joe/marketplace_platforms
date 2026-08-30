<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    public function index()
    {
        $vendor = Auth::guard('vendor')->user();
        $vendor->loadMissing('country');
        $currency = $vendor->country?->currency_code;

        if (! $currency) {
            return redirect()->back()->with('error', 'Vendor country not configured. Cannot determine wallet currency.');
        }

        $wallet = $this->walletService->getOrCreateWallet('vendor', $vendor->id, $currency);
        $transactions = $wallet->transactions()->paginate(20);
        $withdrawalRequests = $wallet->withdrawalRequests()->latest()->take(10)->get();

        return view('partner.wallet.index', compact('wallet', 'transactions', 'withdrawalRequests'));
    }

    public function requestWithdrawal(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();
        $vendor->loadMissing('country');
        $currency = $vendor->country?->currency_code;

        if (! $currency) {
            return redirect()->back()->with('error', 'Vendor country not configured. Cannot determine wallet currency.');
        }

        $wallet = $this->walletService->getOrCreateWallet('vendor', $vendor->id, $currency);

        $data = $request->validate([
            'amount'    => ['required', 'numeric', 'min:1'],
            'bank_name' => ['required', 'string', 'max:150'],
            'bank_iban' => ['required', 'string', 'max:50'],
        ]);

        $amount = (int) $data['amount'];

        $this->walletService->requestWithdrawal($wallet, $amount, [
            'bank_name' => $data['bank_name'],
            'bank_iban' => $data['bank_iban'],
        ]);

        return back()->with('success', __('partner.wallet.messages.withdrawal_submitted'));
    }
}
