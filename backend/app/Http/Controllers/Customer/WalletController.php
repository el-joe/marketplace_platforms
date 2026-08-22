<?php

namespace App\Http\Controllers\Customer;

use App\Enums\WalletOwnerType;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\WalletResource;
use App\Http\Resources\Customer\WalletTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Admin;
use App\Models\WalletWithdrawalRequest;
use App\Notifications\Admin\WithdrawalRequested;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    public function show(string $country): JsonResponse
    {
        $customer = auth('customer')->user();
        $currency = $customer->country?->currency_code ?? 'EGP';

        $wallet = $this->walletService->getOrCreateWallet(
            WalletOwnerType::Customer->value,
            $customer->id,
            $currency,
        );

        return ApiResponse::success(new WalletResource($wallet));
    }

    public function transactions(string $country): JsonResponse
    {
        $customer = auth('customer')->user();
        $currency = $customer->country?->currency_code ?? 'EGP';

        $wallet = $this->walletService->getOrCreateWallet(
            WalletOwnerType::Customer->value,
            $customer->id,
            $currency,
        );

        $transactions = $wallet->transactions()->paginate(15);

        return ApiResponse::paginated($transactions, WalletTransactionResource::class);
    }

    public function requestWithdrawal(Request $request, string $country): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:100'],
            'bank_name'    => ['required', 'string', 'max:150'],
            'bank_iban'    => ['required', 'string', 'max:50'],
        ]);

        $customer = auth('customer')->user();
        $currency = $customer->country?->currency_code ?? 'EGP';

        $wallet = $this->walletService->getOrCreateWallet(
            WalletOwnerType::Customer->value,
            $customer->id,
            $currency,
        );

        if ($wallet->is_frozen) {
            return ApiResponse::error(__('common.exceptions.wallet.frozen_withdrawal'), [], 422);
        }

        if ($wallet->balance < $data['amount']) {
            return ApiResponse::error(__('common.exceptions.wallet.insufficient_balance'), [], 422);
        }

        try {
            $withdrawalRequest = $this->walletService->requestWithdrawal($wallet, $data['amount'], [
                'bank_name' => $data['bank_name'],
                'bank_iban' => $data['bank_iban'],
            ]);
        } catch (InsufficientBalanceException $e) {
            return ApiResponse::error($e->getMessage(), [], 422);
        }

        try {
            Notification::send(
                Admin::where('status', 'active')->get(),
                new WithdrawalRequested($withdrawalRequest),
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return ApiResponse::success(null, __('common.exceptions.wallet.withdrawal_submitted'), 201);
    }
}
