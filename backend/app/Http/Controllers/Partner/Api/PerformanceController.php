<?php

namespace App\Http\Controllers\Partner\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Review;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Services\Vendor\PerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PerformanceController extends Controller
{
    public function __construct(private PerformanceService $performanceService) {}

    private function vendor(): Vendor { return Auth::guard('vendor_api')->user()->vendor; }
    private function vendorId(): string { return Auth::guard('vendor_api')->user()->vendor_id; }

    public function index(Request $request): JsonResponse
    {
        $days = (int) ($request->query('days', 30));
        if (!in_array($days, [30, 60, 90])) { $days = 30; }

        return ApiResponse::success($this->performanceService->getSummary($this->vendor(), $days));
    }

    public function reviews(Request $request): JsonResponse
    {
        $listingIds   = VendorListing::where('vendor_id', $this->vendorId())->pluck('id');
        $ratingFilter = $request->query('rating');

        $reviews = Review::whereIn('vendor_listing_id', $listingIds)
            ->where('status', 'published')
            ->when(is_numeric($ratingFilter), fn ($q) => $q->where('rating', (int) $ratingFilter))
            ->latest()
            ->paginate((int) ($request->query('per_page', 20)));

        return ApiResponse::success([
            'data' => $reviews->map(fn ($r) => [
                'id'         => $r->id,
                'rating'     => $r->rating,
                'title'      => $r->title,
                'body'       => $r->body,
                'created_at' => $r->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $reviews->currentPage(), 'last_page' => $reviews->lastPage(), 'total' => $reviews->total()],
        ]);
    }
}
