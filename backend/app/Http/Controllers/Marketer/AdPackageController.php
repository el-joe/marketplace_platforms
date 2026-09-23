<?php

namespace App\Http\Controllers\Marketer;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerAdPackage;
use App\Services\Marketer\AdPackageService;
use App\Services\WalletService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class AdPackageController extends Controller
{
    public function __construct(private readonly AdPackageService $service, private readonly WalletService $wallets) {}

    private function marketer(): Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    public function index(): View
    {
        $marketer = $this->marketer();
        $packages = $this->service->packagesFor($marketer);
        $active = $this->service->currentSubscription($marketer);
        $currency = $marketer->country?->currency_code;
        $walletBalance = $currency ? $this->wallets->getOrCreateWallet('marketer', $marketer->id, $currency)->balance : 0;

        return view('marketer.ad-packages.index', compact('packages', 'active', 'walletBalance', 'currency'));
    }

    public function contract(MarketerAdPackage $package): View
    {
        abort_unless($package->is_active, 404);
        $marketer = $this->marketer();
        $walletBalance = $this->wallets->getOrCreateWallet('marketer', $marketer->id, $package->currency)->balance;

        return view('marketer.ad-packages.contract', compact('package', 'walletBalance'));
    }

    public function subscribe(Request $request, MarketerAdPackage $package): RedirectResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', 'in:wallet,bank_transfer'],
            'payment_proof' => ['required_if:payment_method,bank_transfer', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'accept_terms' => ['accepted'],
        ]);

        try {
            $proof = $request->hasFile('payment_proof')
                ? $request->file('payment_proof')->store('ad-package-proofs', 'private') : null;
            $sub = $this->service->subscribe($this->marketer(), $package, $data['payment_method'], $proof);
        } catch (InsufficientBalanceException) {
            return back()->with('error', __('ad_packages.insufficient_balance'));
        } catch (DomainException $e) {
            return back()->with('error', __('ad_packages.err_'.$e->getMessage()));
        }

        return redirect()->route('marketer.ad-packages.success', ['subscription' => $sub->id]);
    }

    public function success(Request $request): View
    {
        $sub = \App\Models\MarketerAdPackageSubscription::where('marketer_id', $this->marketer()->id)
            ->with('package')->findOrFail($request->query('subscription'));

        return view('marketer.ad-packages.success', compact('sub'));
    }
}
