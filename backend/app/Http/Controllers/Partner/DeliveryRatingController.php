<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Enums\CarrierPerformanceRatedByType;
use App\Enums\SubOrderStatus;
use App\Models\CarrierPerformanceRating;
use App\Models\SubOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryRatingController extends Controller
{
    public function store(Request $request, SubOrder $subOrder): JsonResponse
    {
        $vendor = auth('vendor')->user();

        abort_unless($subOrder->vendor_id === $vendor->id, 403);
        abort_unless($subOrder->status === SubOrderStatus::Delivered || $subOrder->status === SubOrderStatus::Completed, 422);

        $data = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'on_time' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        // Prevent duplicate rating from same vendor
        $existing = CarrierPerformanceRating::where('sub_order_id', $subOrder->id)
            ->where('rated_by_type', CarrierPerformanceRatedByType::Vendor)
            ->where('rated_by_id', $vendor->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => __('common.exceptions.delivery_rating.already_rated')], 422);
        }

        $shipment = $subOrder->shipment;

        CarrierPerformanceRating::create([
            'shipping_company_id' => $shipment?->shippingCompanyId ?? null,
            'delivery_agent_id'   => $shipment?->delivery_agent_id ?? null,
            'sub_order_id'        => $subOrder->id,
            'rated_by_type'       => CarrierPerformanceRatedByType::Vendor,
            'rated_by_id'         => $vendor->id,
            'rating'              => $data['rating'],
            'on_time'             => $data['on_time'] ?? null,
            'comment'             => $data['comment'] ?? null,
            'visible_to_customer' => false, // enforced — never expose carrier
        ]);

        return response()->json(['message' => __('common.exceptions.delivery_rating.rating_submitted')]);
    }
}
