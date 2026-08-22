<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\PaymentHistoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\PaymentTransaction;
use Illuminate\Http\JsonResponse;

/**
 * Customer-facing payment transaction history.
 * Returns only the customer's own transactions, with sensitive gateway fields excluded.
 * Routes: /api/customer/v1/{country}/payment-history
 */
class PaymentHistoryController extends Controller
{
    public function index(): JsonResponse
    {
        $customer = auth('customer')->user();

        $transactions = PaymentTransaction::where('customer_id', $customer->id)
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated($transactions, PaymentHistoryResource::class);
    }
}
