<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\CarrierPerformanceRatedByType;
use App\Http\Controllers\Controller;
use App\Models\CarrierPerformanceRating;
use App\Models\SubOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryRatingController extends Controller
{
    public function store(Request $request, SubOrder $subOrder): JsonResponse
    {
        $customer = auth('customer')->user();

        // Ensure this sub-order belongs to the authenticated customer
        abort_unless($subOrder->order?->customer_id === $customer->id, 403);
        abort_unless(in_array($subOrder->status->value, ['delivered', 'completed']), 422);

        $data = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'on_time' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $existing = CarrierPerformanceRating::where('sub_order_id', $subOrder->id)
            ->where('rated_by_type', CarrierPerformanceRatedByType::Customer)
            ->where('rated_by_id', $customer->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => __('common.exceptions.delivery_rating.already_rated')], 422);
        }

        $shipment = $subOrder->shipment;

        CarrierPerformanceRating::create([
            'shipping_company_id' => $shipment?->shippingCompanyId ?? null,
            'delivery_agent_id'   => $shipment?->delivery_agent_id ?? null,
            'sub_order_id'        => $subOrder->id,
            'rated_by_type'       => CarrierPerformanceRatedByType::Customer,
            'rated_by_id'         => $customer->id,
            'rating'              => $data['rating'],
            'on_time'             => $data['on_time'] ?? null,
            'comment'             => $data['comment'] ?? null,
            'visible_to_customer' => false, // hard-enforced: never expose carrier to customers
        ]);

        return response()->json(['message' => __('common.exceptions.delivery_rating.feedback_thanks')]);
    }
}
