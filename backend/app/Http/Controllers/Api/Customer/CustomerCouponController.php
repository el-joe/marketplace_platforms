<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Cart;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerCouponController extends Controller
{
    public function __construct(private readonly CouponService $couponService)
    {
    }

    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'cart_id' => ['required', 'uuid'],
        ]);

        $customer = auth('customer')->user();

        $cart = Cart::where('id', $request->cart_id)
            ->where('user_id', $customer->id)
            ->firstOrFail();

        try {
            $coupon = $this->couponService->validate($request->code, $cart, $customer);
            $discount = $this->couponService->calculateDiscount($coupon, $cart);
        } catch (ValidationException $e) {
            return ApiResponse::error($e->validator->errors()->first(), [], 422);
        }

        DB::transaction(function () use ($cart, $coupon, $discount): void {
            $cart->coupon_id = $coupon->id;
            $cart->discount = $discount;
            $cart->estimated_total = max(0,
                $cart->subtotal + $cart->estimated_shipping + $cart->estimated_tax - $discount
            );
            $cart->save();
        });

        return ApiResponse::success([
            'coupon' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'discount_amount' => $discount,
                'currency' => $cart->currency,
            ],
            'cart_totals' => [
                'subtotal' => $cart->subtotal,
                'discount' => $cart->discount,
                'estimated_shipping' => $cart->estimated_shipping,
                'estimated_tax' => $cart->estimated_tax,
                'estimated_total' => $cart->estimated_total,
                'currency' => $cart->currency,
            ],
        ], __('customer_api.coupon.applied'));
    }

    public function remove(Request $request): JsonResponse
    {
        $request->validate([
            'cart_id' => ['required', 'uuid'],
        ]);

        $customer = auth('customer')->user();

        $cart = Cart::where('id', $request->cart_id)
            ->where('user_id', $customer->id)
            ->firstOrFail();

        DB::transaction(function () use ($cart): void {
            $cart->coupon_id = null;
            $cart->discount = 0;
            $cart->estimated_total = max(0,
                $cart->subtotal + $cart->estimated_shipping + $cart->estimated_tax
            );
            $cart->save();
        });

        return ApiResponse::success([
            'cart_totals' => [
                'subtotal' => $cart->subtotal,
                'discount' => $cart->discount,
                'estimated_shipping' => $cart->estimated_shipping,
                'estimated_tax' => $cart->estimated_tax,
                'estimated_total' => $cart->estimated_total,
                'currency' => $cart->currency,
            ],
        ], __('customer_api.coupon.removed'));
    }
}
