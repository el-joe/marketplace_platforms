<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Review\ReviewStoreRequest;
use App\Http\Requests\Customer\Review\ReviewUpdateRequest;
use App\Http\Resources\Customer\ReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\Product;
use App\Models\Review;
use App\Services\Customer\ReviewService;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviewService) {}

    public function store(ReviewStoreRequest $request, string $country, string $orderNumber): JsonResponse
    {
        $customer = auth('customer')->user();

        $order = $customer->orders()->where('order_number', $orderNumber)->first();

        if (!$order) {
            return ApiResponse::error(__('common.exceptions.order.not_found'), [], 404);
        }

        $review = $this->reviewService->store($customer, $order, $request->validated());

        return ApiResponse::success(new ReviewResource($review), __('common.exceptions.review.submitted'), 201);
    }

    public function indexByProduct(string $country, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)->first();

        if (!$product) {
            return ApiResponse::error(__('common.exceptions.review.product_not_found'), [], 404);
        }

        $countryModel = request()->attributes->get('country');

        $paginator = $this->reviewService->listForProduct($product, $countryModel->id);

        return ApiResponse::paginated($paginator, ReviewResource::class, [
            'rating_breakdown' => $this->reviewService->ratingBreakdown($product),
        ]);
    }

    public function update(ReviewUpdateRequest $request, string $country, string $reviewId): JsonResponse
    {
        $customer = auth('customer')->user();

        $review = Review::find($reviewId);

        if (!$review || $review->customer_id !== $customer->id) {
            return ApiResponse::error(__('common.exceptions.review.not_found'), [], 404);
        }

        $review = $this->reviewService->update($customer, $review, $request->validated());

        return ApiResponse::success(new ReviewResource($review), __('common.exceptions.review.updated'));
    }

    public function helpful(string $country, string $reviewId): JsonResponse
    {
        $customer = auth('customer')->user();

        $review = Review::find($reviewId);

        if (!$review) {
            return ApiResponse::error(__('common.exceptions.review.not_found'), [], 404);
        }

        $this->reviewService->markHelpful($customer, $review);

        return ApiResponse::success(null, __('common.exceptions.review.marked_helpful'));
    }
}
