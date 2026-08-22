<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Customer\ReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\Order;
use App\Models\Review;
use App\Services\Customer\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviewService) {}

    /**
     * Store a review for a delivered order item.
     *
     * POST /v1/reviews
     * Auth: required (auth:customer)
     *
     * Body:
     *   order_number  string  required  The order that contains the item
     *   order_item_id uuid    required  The specific item being reviewed
     *   rating        int     required  1–5
     *   comment       string  optional  max 5000 chars
     *   images        array   optional  max 5 images, each ≤5 MB
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number'      => ['required', 'string'],
            'order_item_id'     => ['required', 'uuid', 'exists:order_items,id'],
            'vendor_listing_id' => ['nullable', 'uuid', 'exists:vendor_listings,id'],
            'admin_listing_id'  => ['nullable', 'uuid', 'exists:admin_listings,id'],
            'rating'            => ['required', 'integer', 'min:1', 'max:5'],
            'comment'           => ['nullable', 'string', 'max:5000'],
            'images'            => ['nullable', 'array', 'max:5'],
            'images.*'          => ['image', 'max:5120'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if ($request->filled('vendor_listing_id') && $request->filled('admin_listing_id')) {
                $validator->errors()->add('listing', 'Provide either vendor_listing_id or admin_listing_id, not both.');
            }
        });

        if ($validator->fails()) {
            return ApiResponse::error(__('customer_api.validation_failed'), $validator->errors()->toArray(), 422);
        }

        $customer = auth('customer')->user();
        $data     = $validator->validated();

        $order = Order::where('order_number', $data['order_number'])
            ->where('customer_id', $customer->id)
            ->first();

        if (! $order) {
            return ApiResponse::error(__('common.exceptions.order.not_found'), [], 404);
        }

        try {
            $review = $this->reviewService->store($customer, $order, $data);
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors(), 422);
        }

        return ApiResponse::success(new ReviewResource($review), __('common.exceptions.review.submitted'), 201);
    }

    /**
     * List the authenticated customer's own reviews, newest first.
     *
     * GET /v1/reviews/mine
     * Auth: required (auth:customer)
     */
    public function mine(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $paginator = Review::where('customer_id', $customer->id)
            ->with([
                'files',
                'vendorListing.vendor:id,store_name',
                'vendorListing.productVariant.variantAttributes.attribute',
                'vendorListing.productVariant.variantAttributes.attributeValue',
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated($paginator, ReviewResource::class);
    }
}
