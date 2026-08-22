<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\CouponResource;
use App\Http\Responses\ApiResponse;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;

class CouponController extends Controller
{
    public function show(string $_country, string $code): JsonResponse
    {
        $coupon = Coupon::where('code', $code)->first();

        abort_if(! $coupon, 404, __('common.exceptions.cart.coupon_not_found'));

        return ApiResponse::success(new CouponResource($coupon));
    }
}
