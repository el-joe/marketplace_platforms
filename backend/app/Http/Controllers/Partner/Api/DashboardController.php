<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Services\Vendor\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    /** GET /api/partner/v1/dashboard */
    public function index(): JsonResponse
    {
        $admin    = Auth::guard('vendor_api')->user();
        $snapshot = $this->dashboardService->getSnapshot($admin);

        return response()->json(['success' => true, 'data' => $snapshot]);
    }
}
