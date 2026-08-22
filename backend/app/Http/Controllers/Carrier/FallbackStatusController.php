<?php

namespace App\Http\Controllers\Carrier;

use App\Http\Controllers\Controller;
use App\Http\Resources\Carrier\FallbackStatusResource;
use App\Http\Responses\ApiResponse;
use App\Models\ShippingFallbackRule;
use Illuminate\Http\JsonResponse;

class FallbackStatusController extends Controller
{
    /**
     * GET /api/carrier/v1/fallback-status
     *
     * Read-only visibility into how this company fits into the routing network:
     *   - primary_cities:  city IDs from served_cities (this company is the primary carrier)
     *   - fallback_cities: cities from shipping_fallback_rules where this company is the
     *                      named fallback — volume it MAY receive when primary carriers fail
     *
     * Fallback rule CONFIGURATION is admin-only and is never exposed here.
     */
    public function index(): JsonResponse
    {
        $supervisor = auth('shipping_supervisor_api')->user();
        $company    = $supervisor->company;

        // Cities this company serves as primary carrier
        $primaryCityIds = is_array($company->served_cities) ? $company->served_cities : [];

        // Cities this company is configured as a fallback carrier for
        $fallbackRules = ShippingFallbackRule::where('fallback_shipping_company_id', $company->id)
            ->with('unservedCity:id,name')
            ->orderBy('priority')
            ->get()
            ->map(fn($rule) => [
                'city_id'   => $rule->unserved_city_id,
                'city_name' => $rule->unservedCity?->name,
                'priority'  => $rule->priority,
            ])
            ->all();

        $data = [
            'primary_cities' => array_map(fn($id) => ['city_id' => $id], $primaryCityIds),
            'fallback_cities' => $fallbackRules,
            'summary' => [
                'primary_count'  => count($primaryCityIds),
                'fallback_count' => count($fallbackRules),
            ],
        ];

        return ApiResponse::success(
            new FallbackStatusResource($data),
            __('carrier.api.fallback_routing_retrieved'),
        );
    }
}
