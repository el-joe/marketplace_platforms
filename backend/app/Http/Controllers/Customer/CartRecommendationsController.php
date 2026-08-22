<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Cart;
use App\Models\Customer;
use App\Services\CartRecommendationService;
use App\Services\Customer\CartService;
use App\Services\ListingModeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartRecommendationsController extends Controller
{
    public function __construct(
        private readonly CartRecommendationService $recommendationService,
        private readonly CartService $cartService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();
        $isNawyNow = ListingModeResolver::isNawyNow($request);
        $cart = $this->resolveCart($request, $customer);

        $sections = $this->recommendationService->getRecommendations($cart, $customer, $isNawyNow);

        return ApiResponse::success([
            'sections' => $sections,
            'meta' => [
                'total_sections' => count($sections),
                'listing_mode' => $isNawyNow ? 'nawy_now' : 'marketplace',
            ],
        ]);
    }

    private function resolveCart(Request $request, ?Customer $customer): Cart
    {
        $country = $request->attributes->get('country');

        if ($customer) {
            return $this->cartService->getOrCreateCart(
                $customer,
                $country->id,
                $country->currency_code
            );
        }

        $token = $request->attributes->get('guest_cart_token');
        if (!$token) {
            $token = (string) Str::uuid();
            $request->attributes->set('guest_cart_token', $token);
        }

        return $this->cartService->getOrCreateGuestCart(
            $token,
            $country->id,
            $country->currency_code
        );
    }
}
