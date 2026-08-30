<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\RefundResource;
use App\Http\Responses\ApiResponse;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;

class RefundController extends Controller
{
    public function index(string $country): JsonResponse
    {
        $customer = auth('customer')->user();

        $paginator = Refund::where('initiated_by_customer_id', $customer->id)
            ->select(['id', 'order_id', 'initiated_by_customer_id', 'amount', 'currency', 'reason', 'refund_type', 'status', 'created_at'])
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated($paginator, RefundResource::class);
    }

    public function show(string $country, string $id): JsonResponse
    {
        $customer = auth('customer')->user();

        $refund = Refund::where('id', $id)
            ->where('initiated_by_customer_id', $customer->id)
            ->with('order:id,order_number')
            ->first();

        if (!$refund) {
            return ApiResponse::error(__('common.exceptions.refund.not_found'), [], 404);
        }

        return ApiResponse::success(new RefundResource($refund));
    }
}
