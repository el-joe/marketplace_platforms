<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\VendorGlobalStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SellerController extends Controller
{
    public function show(Request $request, string $sellerId): JsonResponse
    {
        $cacheKey = "public_seller_profile:v".\App\Support\ListingCacheVersion::current().":{$sellerId}";

        $payload = Cache::remember($cacheKey, 300, fn () => $this->buildPayload($sellerId));

        if ($payload === null) {
            return ApiResponse::error('Seller not found.', [], 404);
        }

        return ApiResponse::success($payload);
    }

    private function buildPayload(string $sellerId): ?array
    {
        $vendor = Vendor::where('id', $sellerId)
            ->where('global_status', VendorGlobalStatus::Active)
            ->with('businessAddress')
            ->first();

        if (!$vendor) {
            return null;
        }

        $ratingCounts = Review::query()
            ->whereHas('vendorListing', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->where('status', 'published')
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $totalRatings = $ratingCounts->sum();

        $breakdown = collect(range(5, 1))->map(function (int $stars) use ($ratingCounts, $totalRatings) {
            $count = $ratingCounts->get($stars, 0);
            return [
                'stars'      => $stars,
                'count'      => $count,
                'percentage' => $totalRatings > 0 ? (int) round(($count / $totalRatings) * 100) : 0,
            ];
        })->values();

        $reviews = Review::query()
            ->whereHas('vendorListing', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->where('status', 'published')
            ->whereNotNull('body')
            ->with('customer:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Review $review) => [
                'id'                   => $review->id,
                'reviewer_name'        => $this->maskCustomerName($review->customer?->name),
                'avatar_letter'        => mb_substr($review->customer?->name ?? '?', 0, 1),
                'is_verified_purchase' => (bool) $review->is_verified_purchase,
                'rating'               => $review->rating,
                'date'                 => $review->created_at->format('M j, Y'),
                'comment'              => $review->body,
                'translated_comment'   => $review->translated_body,
                'original_language'    => $review->original_language,
            ]);

        $customersLast90Days = OrderItem::query()
            ->where('order_items.vendor_id', $vendor->id)
            ->where('order_items.created_at', '>=', now()->subDays(90))
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->distinct('orders.customer_id')
            ->count('orders.customer_id');

        return [
            'id'                       => $vendor->id,
            'store_name'               => $vendor->store_name,
            'logo_text'                => $vendor->store_name,
            'address'                  => $this->formatAddress($vendor),
            'email'                    => $vendor->contact_email,
            'seller_rating'            => $vendor->store_rating_avg,
            'positive_ratings_pct'     => $vendor->positive_rating_pct,
            'customers_count'          => $this->formatCount($customersLast90Days),
            'customers_period_text'    => 'During the last 90 days',
            'product_as_described_pct' => $vendor->product_as_described_pct,
            'seller_since'             => $vendor->created_at->format('F, Y'),
            'total_ratings_count'      => $totalRatings,
            'total_reviews_count'      => $reviews->count(),
            'rating_breakdown'         => $breakdown,
            'reviews'                  => $reviews,
        ];
    }

    private function formatAddress(Vendor $vendor): ?string
    {
        $address = $vendor->businessAddress;

        if (!$address) {
            return null;
        }

        return collect([
            $address->street_address,
            $address->building,
            $address->area,
        ])->filter()->implode(', ');
    }

    private function maskCustomerName(?string $name): string
    {
        if (!$name) {
            return 'Anonymous';
        }
        $parts = explode(' ', trim($name));
        return $parts[0] . (isset($parts[1]) ? ' ' . mb_substr($parts[1], 0, 1) . '.' : '');
    }

    private function formatCount(int $count): string
    {
        if ($count >= 1000) {
            return round($count / 1000, 1) . 'K+';
        }
        return (string) $count;
    }
}
