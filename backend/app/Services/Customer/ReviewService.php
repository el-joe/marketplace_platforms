<?php

namespace App\Services\Customer;

use App\Enums\ReviewStatus;
use App\Enums\ReviewVote as ReviewVoteEnum;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewVote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    private const EDIT_WINDOW_DAYS = 7;

    public function canReview(Customer $customer, OrderItem $item): bool
    {
        return $this->reviewBlockReason($customer, $item) === null;
    }

    private function reviewBlockReason(Customer $customer, OrderItem $item): ?string
    {
        if ($item->fulfillment_status !== \App\Enums\OrderItemFulfillmentStatus::Delivered) {
            return 'This item has not been delivered yet.';
        }

        $alreadyReviewed = Review::where('customer_id', $customer->id)
            ->where('order_item_id', $item->id)
            ->exists();

        if ($alreadyReviewed) {
            return 'You have already reviewed this item.';
        }

        return null;
    }

    public function store(Customer $customer, Order $order, array $data): Review
    {
        $item = OrderItem::with('productVariant.product')
            ->where('id', $data['order_item_id'] ?? null)
            ->where('order_id', $order->id)
            ->firstOrFail();

        if ($reason = $this->reviewBlockReason($customer, $item)) {
            throw ValidationException::withMessages([
                'order_item_id' => [$reason],
            ]);
        }

        $product = $item->productVariant->product;

        return DB::transaction(function () use ($customer, $item, $product, $data, $order): Review {
            $review = Review::create([
                'product_id' => $product->id,
                'vendor_listing_id' => $data['vendor_listing_id'] ?? $item->vendor_listing_id,
                'admin_listing_id' => $data['admin_listing_id'] ?? $item->admin_listing_id,
                'customer_id' => $customer->id,
                'order_item_id' => $item->id,
                'country_id' => $customer->country_id,
                'rating' => $data['rating'],
                'title' => null,
                'body' => $data['comment'] ?? null,
                'is_verified_purchase' => true,
                'status' => ReviewStatus::Pending,
            ]);

            if (!empty($data['images'])) {
                foreach ($data['images'] as $image) {
                    $path = $image->store('reviews', 'public');
                    $review->files()->create([
                        'path' => $path,
                        'disk' => 'public',
                        'mime_type' => $image->getMimeType(),
                        'size' => $image->getSize(),
                        'original_name' => $image->getClientOriginalName(),
                    ]);
                }
            }

            return $review;
        });
    }

    public function update(Customer $customer, Review $review, array $data): Review
    {
        if ($review->customer_id !== $customer->id) {
            abort(403);
        }

        if ($review->created_at->addDays(self::EDIT_WINDOW_DAYS)->isPast()) {
            throw ValidationException::withMessages([
                'review' => ['The edit window for this review has expired.'],
            ]);
        }

        $review->update([
            'rating' => $data['rating'] ?? $review->rating,
            'body' => $data['comment'] ?? $review->body,
        ]);

        return $review;
    }

    public function markHelpful(Customer $customer, Review $review): void
    {
        $exists = ReviewVote::where('review_id', $review->id)
            ->where('customer_id', $customer->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'review' => ['You have already voted on this review.'],
            ]);
        }

        ReviewVote::create([
            'review_id' => $review->id,
            'customer_id' => $customer->id,
            'vote' => ReviewVoteEnum::Helpful,
        ]);

        $review->increment('helpful_count');
    }

    public function listForProduct(Product $product, string $countryId): LengthAwarePaginator
    {
        return Review::where('product_id', $product->id)
            ->where('country_id', $countryId)
            ->where('status', ReviewStatus::Published)
            ->with([
                'customer',
                'vendorReply',
                'files',
                'vendorListing.vendor:id,store_name',
                'vendorListing.productVariant.variantAttributes.attribute',
                'vendorListing.productVariant.variantAttributes.attributeValue',
            ])
            ->orderByDesc('helpful_count')
            ->paginate(20);
    }

    public function ratingBreakdown(Product $product): array
    {
        $counts = Review::where('product_id', $product->id)
            ->where('status', ReviewStatus::Published)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $total = $counts->sum();

        return collect(range(5, 1))->map(fn ($star) => [
            'stars'      => $star,
            'count'      => (int) ($counts[$star] ?? 0),
            'percentage' => $total > 0 ? round((($counts[$star] ?? 0) / $total) * 100) : 0,
        ])->values()->all();
    }
}
