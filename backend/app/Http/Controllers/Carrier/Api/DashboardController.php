<?php

namespace App\Http\Controllers\Carrier\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ShippingCompanySupervisor;
use App\Services\Carrier\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    public function index(): JsonResponse
    {
        /** @var ShippingCompanySupervisor $supervisor */
        $supervisor = auth('shipping_supervisor_api')->user();
        $company    = $supervisor->company;

        $snapshot = $this->dashboardService->getSnapshot($company);

        return ApiResponse::success([
            'company' => [
                'id'     => $company->id,
                'name'   => $company->name,
                'status' => $company->status?->value,
            ],
            'permissions' => $supervisor->permissions ?? [],
            ...$snapshot,
        ]);
    }
}
