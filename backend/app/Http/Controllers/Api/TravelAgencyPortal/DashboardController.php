<?php

namespace App\Http\Controllers\Api\TravelAgencyPortal;

use App\Enums\TravelBookingStatus;
use App\Enums\TravelPackageInquiryStatus;
use App\Enums\TravelPackageStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TravelAgencyPortal\TravelBookingResource;
use App\Http\Resources\Api\TravelAgencyPortal\TravelPackageInquiryResource;
use App\Http\Resources\Api\TravelAgencyPortal\TravelPackageResource;
use App\Http\Responses\ApiResponse;
use App\Models\TravelAgency;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use App\Models\TravelPackageInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var TravelAgency $agency */
        $agency = Auth::guard('travel_agencies')->user();

        // travel_packages.status enum is draft/pending_review/active/sold_out/expired
        // (see 2026_06_17_000010_create_travel_packages_table.php) — there is no
        // 'approved' or 'rejected' value. Admin\TravelPackageController::approve()
        // sets 'active'; ::reject() resets to 'draft' and records rejection_reason,
        // so a rejected package is a 'draft' with rejection_reason populated.
        $packageCounts = TravelPackage::where('travel_agency_id', $agency->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $totalPackages = (int) $packageCounts->sum();

        $rejectedPackagesCount = TravelPackage::where('travel_agency_id', $agency->id)
            ->where('status', TravelPackageStatus::Draft)
            ->whereNotNull('rejection_reason')
            ->count();

        // travel_bookings.status enum is pending_documents/confirmed/cancelled/completed
        // (see 2026_06_17_000012_create_travel_bookings_table.php).
        $bookingCounts = TravelBooking::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agency->id))
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $totalBookings = (int) $bookingCounts->sum();

        $bookingStats = TravelBooking::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agency->id))
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->selectRaw('COUNT(*) as total, SUM(total_price) as revenue')
            ->first();

        // total_price is an unsigned BIGINT with no currency column of its
        // own — currency lives on the related travel_packages row. Revenue must
        // be grouped by currency rather than summed across the whole agency, or
        // packages priced in different currencies would be added together.
        // Cancelled bookings are excluded, matching Vendor\DashboardService's
        // treatment of cancelled/refunded orders as non-revenue.
        $revenueByCurrency = TravelBooking::query()
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->where('travel_packages.travel_agency_id', $agency->id)
            ->where('travel_bookings.status', '!=', TravelBookingStatus::Cancelled)
            ->selectRaw('travel_packages.currency as currency, SUM(travel_bookings.total_price) as revenue')
            ->groupBy('travel_packages.currency')
            ->get()
            ->map(fn ($row) => [
                'currency' => $row->currency,
                'revenue' => (int) $row->revenue,
            ])
            ->values();

        $openInquiriesCount = TravelPackageInquiry::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agency->id))
            ->whereIn('status', [TravelPackageInquiryStatus::New, TravelPackageInquiryStatus::Contacted])
            ->count();

        $recentPackages = TravelPackage::where('travel_agency_id', $agency->id)
            ->with('media')
            ->latest()
            ->limit(5)
            ->get();

        $recentBookings = TravelBooking::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agency->id))
            ->with(['package', 'customer'])
            ->latest()
            ->limit(5)
            ->get();

        $recentInquiries = TravelPackageInquiry::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agency->id))
            ->with('package')
            ->latest()
            ->limit(5)
            ->get();

        return ApiResponse::success([
            'total_packages' => $totalPackages,
            'package_counts' => $packageCounts,
            'rejected_packages_count' => $rejectedPackagesCount,
            'total_bookings' => $totalBookings,
            'booking_counts' => $bookingCounts,
            'bookings_this_month' => (int) ($bookingStats->total ?? 0),
            'revenue_this_month' => (int) ($bookingStats->revenue ?? 0),
            'revenue_by_currency' => $revenueByCurrency,
            'open_inquiries_count' => $openInquiriesCount,
            'recent_packages' => TravelPackageResource::collection($recentPackages),
            'recent_bookings' => TravelBookingResource::collection($recentBookings),
            'recent_inquiries' => TravelPackageInquiryResource::collection($recentInquiries),
        ]);
    }
}
