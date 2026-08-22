<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Review;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Services\Vendor\PerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function __construct(private readonly PerformanceService $performanceService) {}

    private function vendor(): Vendor
    {
        /** @var \App\Models\VendorAdmin $auth */
        $auth = auth('vendor')->user();
        /** @var Vendor $vendor */
        $vendor = $auth->vendor;
        return $vendor;
    }

    private function vendorId(): string
    {
        /** @var \App\Models\VendorAdmin $auth */
        $auth = auth('vendor')->user();
        return (string) $auth->vendor_id;
    }

    public function index(Request $request): JsonResponse
    {
        $days = (int) ($request->query('days', 30));

        if (!in_array($days, [30, 60, 90])) {
            $days = 30;
        }

        $summary = $this->performanceService->getSummary($this->vendor(), $days);

        return ApiResponse::success($summary);
    }

    public function reviews(Request $request): JsonResponse
    {
        $vendorId   = $this->vendorId();
        $listingIds = VendorListing::where('vendor_id', $vendorId)->pluck('id');

        $ratingFilter = $request->query('rating');

        $reviews = Review::whereIn('vendor_listing_id', $listingIds)
            ->where('status', 'published')
            ->when(is_numeric($ratingFilter), fn ($q) => $q->where('rating', (int) $ratingFilter))
            ->latest()
            ->paginate((int) ($request->query('per_page', 20)));

        return ApiResponse::paginated($reviews, ReviewResource::class);
    }
}
