<?php

namespace App\Http\Controllers\Carrier;

use App\Http\Controllers\Controller;
use App\Http\Resources\Carrier\LiveMapResource;
use App\Http\Responses\ApiResponse;
use App\Services\Carrier\LiveMapService;
use Illuminate\Http\JsonResponse;

class LiveMapController extends Controller
{
    public function __construct(private LiveMapService $liveMapService) {}

    /**
     * GET /api/carrier/v1/agents/live-map
     * Requires view_orders OR view_reports — checked explicitly because the
     * middleware supports a single permission only.
     */
    public function index(): JsonResponse
    {
        $supervisor = auth('shipping_supervisor_api')->user();

        if (! $supervisor->hasPermission('view_orders') && ! $supervisor->hasPermission('view_reports')) {
            return ApiResponse::error(__('carrier.api.forbidden'), [], 403);
        }

        $data = $this->liveMapService->getCompanyMapData($supervisor->company);

        return ApiResponse::success(
            new LiveMapResource($data),
            __('carrier.api.live_map_retrieved'),
        );
    }
}
