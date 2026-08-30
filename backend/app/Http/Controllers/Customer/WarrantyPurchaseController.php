<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\WarrantyPurchaseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\WarrantyPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarrantyPurchaseController extends Controller
{
    public function index(Request $request, string $country): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'active', 'expired', 'cancelled'])],
        ]);

        $query = WarrantyPurchase::forCustomer($customer->id)
            ->select(['id', 'customer_id', 'order_id', 'order_item_id', 'warranty_plan_id', 'plan_snapshot', 'price_paid', 'currency', 'status', 'coverage_starts_at', 'coverage_ends_at', 'created_at'])
            ->with([
                'orderItem:id,sku,product_snapshot',
                'plan:id,duration_months',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', '!=', 'cancelled');
        }

        $paginator = $query->paginate(15);

        return ApiResponse::paginated($paginator, WarrantyPurchaseResource::class);
    }

    public function show(string $country, string $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $purchase = WarrantyPurchase::where('id', $id)
            ->where('customer_id', $customer->id)
            ->with([
                'orderItem:id,sku,product_snapshot',
                'plan:id,duration_months',
            ])
            ->first();

        if (!$purchase) {
            return ApiResponse::error(__('common.exceptions.warranty_purchase.not_found'), [], 404);
        }

        return ApiResponse::success(new WarrantyPurchaseResource($purchase));
    }
}
