<?php

namespace App\Http\Controllers\Delivery\Api;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\DeliveryAgent;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    public function index(): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();
        $agent->loadMissing('country');
        $currency = $agent->country?->currency_code ?? 'AED';
        $wallet = $this->walletService->getOrCreateWallet('delivery_agent', $agent->id, $currency);

        return ApiResponse::success([
            'balance' => $wallet->balance,
            'pending_balance' => $wallet->pending_balance,
            'currency' => $wallet->currency,
            'is_frozen' => $wallet->is_frozen,
        ]);
    }

    public function transactions(): JsonResponse
    {
        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();
        $agent->loadMissing('country');
        $currency = $agent->country?->currency_code ?? 'AED';
        $wallet = $this->walletService->getOrCreateWallet('delivery_agent', $agent->id, $currency);

        $transactions = $wallet->transactions()->paginate(20);

        return ApiResponse::success([
            'items' => $transactions->getCollection()->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => $t->amount,
                'balance_after' => $t->balance_after,
                'description' => $t->description,
                'source_type' => $t->source_type,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'bank_name' => ['required', 'string', 'max:150'],
            'bank_iban' => ['required', 'string', 'max:50'],
        ]);

        /** @var DeliveryAgent $agent */
        $agent = auth('delivery_api')->user();
        $agent->loadMissing('country');
        $currency = $agent->country?->currency_code ?? 'AED';
        $wallet = $this->walletService->getOrCreateWallet('delivery_agent', $agent->id, $currency);

        try {
            $this->walletService->requestWithdrawal($wallet, (int) $data['amount'], [
                'bank_name' => $data['bank_name'],
                'bank_iban' => $data['bank_iban'],
            ]);
        } catch (InsufficientBalanceException) {
            return ApiResponse::error('Insufficient balance.', [], 422);
        }

        return ApiResponse::success(null, 'Withdrawal request submitted.');
    }
}
