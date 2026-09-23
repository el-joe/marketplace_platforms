<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerAdPackage;
use App\Models\MarketerAdPackageSubscription;
use App\Services\Marketer\AdPackageService;
use App\Services\WalletService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdPackageController extends Controller
{
    public function __construct(private readonly AdPackageService $service, private readonly WalletService $wallets) {}

    private function marketer(): Marketer
    {
        return Auth::guard('marketer_api')->user()->marketer;
    }

    private function presentSub(?MarketerAdPackageSubscription $s): ?array
    {
        return $s ? [
            'subscription_id' => $s->id, 'package_id' => $s->package_id, 'status' => $s->status,
            'price' => $s->price, 'vat_amount' => $s->vat_amount, 'amount_paid' => $s->amount_paid,
            'currency' => $s->currency, 'payment_method' => $s->payment_method,
            'starts_at' => $s->starts_at, 'expires_at' => $s->expires_at,
        ] : null;
    }

    public function index(): JsonResponse
    {
        $m = $this->marketer();
        $currency = $m->country?->currency_code;
        $packages = $this->service->packagesFor($m)->map(fn ($p) => [
            'id' => $p->id, 'name_ar' => $p->name_ar, 'name_en' => $p->name_en, 'description_ar' => $p->description_ar,
            'price' => $p->price, 'vat_pct' => $p->vat_pct, 'vat_amount' => $p->vat_amount, 'total' => $p->total,
            'currency' => $p->currency, 'duration_days' => $p->duration_days, 'features' => $p->features ?? [],
        ]);

        return response()->json(['success' => true, 'data' => [
            'packages' => $packages,
            'wallet_balance' => $currency ? $this->wallets->getOrCreateWallet('marketer', $m->id, $currency)->balance : 0,
            'currency' => $currency,
        ]]);
    }

    public function subscribe(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', 'in:wallet,bank_transfer'],
            'payment_proof' => ['required_if:payment_method,bank_transfer', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $package = MarketerAdPackage::findOrFail($id);

        try {
            $proof = $request->hasFile('payment_proof')
                ? $request->file('payment_proof')->store('ad-package-proofs', 'private') : null;
            $sub = $this->service->subscribe($this->marketer(), $package, $data['payment_method'], $proof);
        } catch (InsufficientBalanceException) {
            return response()->json(['success' => false, 'message' => __('ad_packages.insufficient_balance')], 422);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => __('ad_packages.err_'.$e->getMessage())], 422);
        }

        return response()->json(['success' => true, 'data' => [
            'subscription_id' => $sub->id, 'status' => $sub->status, 'expires_at' => $sub->expires_at,
        ]], 201);
    }

    public function mySubscription(): JsonResponse
    {
        return response()->json(['success' => true,
            'data' => $this->presentSub($this->service->currentSubscription($this->marketer()))]);
    }
}
