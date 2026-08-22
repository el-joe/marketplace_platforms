<?php

namespace App\Http\Controllers\Carrier;

use App\Http\Controllers\Controller;
use App\Http\Resources\Carrier\CarrierDashboardResource;
use App\Http\Responses\ApiResponse;
use App\Services\Carrier\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    /**
     * GET /api/carrier/v1/dashboard
     * Snapshot cached 60 s per company — no permission gate beyond authentication.
     */
    public function index(): JsonResponse
    {
        $supervisor = auth('shipping_supervisor_api')->user();
        $company    = $supervisor->company;

        $snapshot = $this->dashboardService->getSnapshot($company);

        return ApiResponse::success(
            new CarrierDashboardResource($snapshot),
            __('carrier.api.dashboard_retrieved'),
        );
    }
}
