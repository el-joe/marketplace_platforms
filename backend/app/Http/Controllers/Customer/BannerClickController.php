<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;

class BannerClickController extends Controller
{
    /**
     * POST /api/customer/v1/{country}/banners/{banner}/click
     * Public, no auth. Records a click on an admin-managed banner placement.
     */
    public function click(Banner $banner): JsonResponse
    {
        dispatch(fn () => $banner->increment('clicks_count'))->afterResponse();

        return ApiResponse::success(null, 'Click recorded');
    }
}
