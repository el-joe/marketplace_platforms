<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\RefundResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class RefundController extends Controller
{
    /**
     * Refunds on the customer's own orders — whoever initiated them (the customer, an admin after a
     * return inspection, an RTO). `initiated_by_customer_id` only records a customer-initiated refund.
     */
    private function ownedBy(Customer $customer): Builder
    {
        return Refund::whereHas('order', fn (Builder $q) => $q->where('customer_id', $customer->id));
    }

    public function index(string $country): JsonResponse
    {
        $customer = auth('customer')->user();

        $paginator = $this->ownedBy($customer)
            ->select(['id', 'order_id', 'initiated_by_customer_id', 'amount', 'currency', 'reason', 'refund_type', 'status', 'created_at'])
            ->with('order:id,order_number')
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated($paginator, RefundResource::class);
    }

    public function show(string $country, string $id): JsonResponse
    {
        $customer = auth('customer')->user();

        $refund = $this->ownedBy($customer)
            ->where('id', $id)
            ->with('order:id,order_number')
            ->first();

        if (! $refund) {
            return ApiResponse::error(__('common.exceptions.refund.not_found'), [], 404);
        }

        return ApiResponse::success(new RefundResource($refund));
    }
}
