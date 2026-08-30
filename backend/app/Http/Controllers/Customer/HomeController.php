<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\HomeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Country;
use App\Services\Customer\HomeService;
use App\Services\Shared\PageBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(
        private readonly HomeService $home,
    ) {
    }

    public function index(Request $request, $country, PageBuilderService $pageBuilder): JsonResponse
    {
        $country = $request->attributes->get('country');

        $deviceTarget = $pageBuilder->detectDevice($request);
        $audience = auth('customer')->check() ? 'authenticated' : 'guest';

        $data = $this->home->getHomeData($country, $deviceTarget, $audience);

        return ApiResponse::success(new HomeResource($data));
    }
}
