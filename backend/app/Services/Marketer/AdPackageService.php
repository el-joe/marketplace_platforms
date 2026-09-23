<?php

namespace App\Services\Marketer;

use App\Models\Marketer;
use App\Models\MarketerAdPackage;
use App\Models\MarketerAdPackageSubscription as Sub;
use App\Services\WalletService;
use DomainException;
use Illuminate\Support\Facades\DB;

class AdPackageService
{
    public function __construct(private readonly WalletService $wallets) {}

    public function currentSubscription(Marketer $m): ?Sub
    {
        return Sub::where('marketer_id', $m->id)->currentlyActive()->with('package')->latest('expires_at')->first();
    }

    public function packagesFor(Marketer $m)
    {
        $keys = $m->marketerJobs()->pluck('key')->all();

        return MarketerAdPackage::where('is_active', true)
            ->where(fn ($q) => $q->whereIn('target_type', $keys ?: ['__none__'])->orWhere('target_type', 'all'))
            ->ordered()->get();
    }

    /** @throws DomainException */
    public function subscribe(Marketer $marketer, MarketerAdPackage $package, string $method, ?string $proofPath = null): Sub
    {
        if (! in_array($method, ['wallet', 'bank_transfer'], true)) {
            throw new DomainException('invalid_payment_method');
        }

        return DB::transaction(function () use ($marketer, $package, $method, $proofPath) {
            // Serialise concurrent submits per marketer (double-click / retries).
            Marketer::whereKey($marketer->id)->lockForUpdate()->firstOrFail();
            $package = MarketerAdPackage::whereKey($package->id)->firstOrFail();

            if (! $package->is_active) {
                throw new DomainException('package_unavailable');
            }
            $overlap = Sub::where('marketer_id', $marketer->id)
                ->where(fn ($q) => $q->where('status', 'pending')
                    ->orWhere(fn ($q2) => $q2->where('status', 'active')->where('expires_at', '>', now())))
                ->exists();
            if ($overlap) {
                throw new DomainException('already_subscribed');
            }

            $vat = MarketerAdPackage::vatFor((int) $package->price, (int) $package->vat_pct);
            $total = (int) $package->price + $vat;
            $isWallet = $method === 'wallet';

            $sub = Sub::create([
                'marketer_id' => $marketer->id, 'package_id' => $package->id,
                'price' => $package->price, 'vat_pct' => $package->vat_pct, 'duration_days' => $package->duration_days,
                'amount_paid' => $total, 'vat_amount' => $vat, 'currency' => $package->currency,
                'payment_method' => $method, 'payment_proof_path' => $isWallet ? null : $proofPath,
                'status' => $isWallet ? 'active' : 'pending',
                'starts_at' => $isWallet ? now() : null,
                'expires_at' => $isWallet ? now()->addDays($package->duration_days) : null,
            ]);

            if ($isWallet) {
                $wallet = $this->wallets->getOrCreateWallet('marketer', $marketer->id, $package->currency);
                // Throws InsufficientBalanceException -> whole transaction rolls back.
                $this->wallets->debit($wallet, $total, 'marketer_ad_package', $sub->id, 'Ad package: '.$package->name_ar);
            }

            return $sub;
        });
    }

    public function approve(Sub $sub): Sub
    {
        return DB::transaction(function () use ($sub) {
            $sub = Sub::whereKey($sub->id)->lockForUpdate()->firstOrFail();
            if ($sub->status !== 'pending') {
                throw new DomainException('not_pending');
            }
            $sub->update(['status' => 'active', 'starts_at' => now(), 'expires_at' => now()->addDays($sub->duration_days)]);

            return $sub;
        });
    }

    public function reject(Sub $sub, ?string $adminId = null): Sub
    {
        return DB::transaction(function () use ($sub, $adminId) {
            $sub = Sub::whereKey($sub->id)->lockForUpdate()->firstOrFail();
            if ($sub->status !== 'pending') {
                throw new DomainException('not_pending');
            }
            if ($sub->payment_method === 'wallet' && $sub->amount_paid > 0) {
                $wallet = $this->wallets->getOrCreateWallet('marketer', $sub->marketer_id, $sub->currency);
                $this->wallets->credit($wallet, $sub->amount_paid, 'marketer_ad_package_refund', $sub->id, 'Ad package refund', $adminId);
            }
            $sub->update(['status' => 'cancelled']);

            return $sub;
        });
    }

    public function expireDue(): int
    {
        return Sub::where('status', 'active')->where('expires_at', '<=', now())->update(['status' => 'expired']);
    }
}
