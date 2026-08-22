<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Enums\TravelBookingStatus;
use App\Enums\TravelPackageInquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use App\Models\TravelPackageInquiry;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ResolvesTravelAgency;

    public function index(): View
    {
        $agency = $this->agency();
        $agencyId = $this->agencyId();

        $packageCounts = TravelPackage::where('travel_agency_id', $agencyId)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // Booking stats scoped to this agency's packages
        $bookingStats = TravelBooking::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId))
            ->selectRaw('COUNT(*) as total, SUM(total_price) as revenue')
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->first();

        $totalBookings = TravelBooking::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId))
            ->count();

        $pendingBookings = TravelBooking::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId))
            ->where('status', TravelBookingStatus::PendingDocuments)
            ->count();

        $confirmedBookings = TravelBooking::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId))
            ->where('status', TravelBookingStatus::Confirmed)
            ->count();

        $newInquiries = TravelPackageInquiry::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId))
            ->where('status', TravelPackageInquiryStatus::New)
            ->count();

        // Revenue grouped by currency — never sum across currencies.
        $revenueByCurrency = TravelBooking::query()
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->where('travel_packages.travel_agency_id', $agencyId)
            ->whereIn('travel_bookings.status', [TravelBookingStatus::Confirmed, TravelBookingStatus::Completed])
            ->selectRaw('travel_packages.currency as currency, SUM(travel_bookings.total_price) as total')
            ->groupBy('travel_packages.currency')
            ->pluck('total', 'currency');

        $recentPackages = TravelPackage::where('travel_agency_id', $agencyId)
            ->with('media')
            ->latest()
            ->limit(5)
            ->get();

        $recentBookings = TravelBooking::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId))
            ->with(['package', 'customer'])
            ->latest()
            ->limit(5)
            ->get();

        $recentInquiries = TravelPackageInquiry::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId))
            ->where('status', TravelPackageInquiryStatus::New)
            ->with('package')
            ->latest()
            ->limit(5)
            ->get();

        return view('travel-agency.dashboard', compact(
            'agency',
            'packageCounts',
            'totalBookings',
            'pendingBookings',
            'confirmedBookings',
            'newInquiries',
            'revenueByCurrency',
            'bookingStats',
            'recentPackages',
            'recentBookings',
            'recentInquiries',
        ));
    }
}
