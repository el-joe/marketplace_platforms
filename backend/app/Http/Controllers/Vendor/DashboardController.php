<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\Vendor\VendorDashboardResource;
use App\Http\Responses\ApiResponse;
use App\Services\Vendor\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function __invoke(): \Illuminate\Http\JsonResponse
    {
        $admin    = auth('vendor')->user();
        $snapshot = $this->dashboardService->getSnapshot($admin);

        return ApiResponse::success(
            new VendorDashboardResource($snapshot),
            'Dashboard loaded.'
        );
    }
}
