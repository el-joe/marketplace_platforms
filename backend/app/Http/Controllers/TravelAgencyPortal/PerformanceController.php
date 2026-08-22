<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Enums\TravelBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use App\Models\TravelPackageInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    private function agencyId(): string
    {
        return Auth::guard('travel_agency')->user()->travel_agency_id;
    }

    private function dateRange(Request $request): array
    {
        $from = $request->query('date_from')
            ? Carbon::parse($request->query('date_from'))->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = $request->query('date_to')
            ? Carbon::parse($request->query('date_to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    public function index(): View
    {
        return view('travel-agency.performance.index');
    }

    public function stats(Request $request): JsonResponse
    {
        $agencyId = $this->agencyId();
        [$from, $to] = $this->dateRange($request);

        $baseQuery = TravelBooking::query()
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->where('travel_packages.travel_agency_id', $agencyId)
            ->whereBetween('travel_bookings.created_at', [$from, $to]);

        $totalBookings = (clone $baseQuery)->count();
        $completedBookings = (clone $baseQuery)->where('travel_bookings.status', TravelBookingStatus::Completed)->count();
        $cancelledBookings = (clone $baseQuery)->where('travel_bookings.status', TravelBookingStatus::Cancelled)->count();
        $cancellationRate = $totalBookings > 0 ? round($cancelledBookings / $totalBookings * 100, 1) : 0;

        $avgBookingValueByCurrency = (clone $baseQuery)
            ->selectRaw('travel_packages.currency as currency, AVG(travel_bookings.total_price) as avg_value, SUM(travel_bookings.total_price) as total_revenue')
            ->where('travel_bookings.status', TravelBookingStatus::Completed)
            ->groupBy('travel_packages.currency')
            ->get()
            ->map(fn ($row) => [
                'currency'      => $row->currency,
                'avg_value'     => round((float) $row->avg_value),
                'total_revenue' => (int) $row->total_revenue,
            ]);

        $totalInquiries = TravelPackageInquiry::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId))
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $avgResponseMinutes = TravelPackageInquiry::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId))
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('contacted_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, contacted_at)) as avg_minutes')
            ->value('avg_minutes');

        // Bookings over time
        $bookingsOverTime = (clone $baseQuery)
            ->selectRaw('DATE(travel_bookings.created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => ['day' => $r->day, 'total' => (int) $r->total]);

        // Revenue over time
        $revenueOverTime = (clone $baseQuery)
            ->where('travel_bookings.status', TravelBookingStatus::Completed)
            ->selectRaw('DATE(travel_bookings.created_at) as day, travel_packages.currency as currency, SUM(travel_bookings.total_price) as total')
            ->groupBy('day', 'currency')
            ->orderBy('day')
            ->get()
            ->groupBy('currency')
            ->map(fn ($rows) => $rows->map(fn ($r) => ['day' => $r->day, 'total' => (int) $r->total])->values());

        // Per-package performance
        $bookingsByPackage = (clone $baseQuery)
            ->selectRaw('travel_packages.id as package_id, travel_packages.title_ar as title_ar, travel_packages.title_en as title_en, travel_packages.currency as currency,
                COUNT(*) as bookings_count,
                SUM(CASE WHEN travel_bookings.status = ? THEN travel_bookings.total_price ELSE 0 END) as revenue', [TravelBookingStatus::Completed->value])
            ->groupBy('travel_packages.id', 'travel_packages.title_ar', 'travel_packages.title_en', 'travel_packages.currency')
            ->get()
            ->keyBy('package_id');

        $inquiriesByPackage = TravelPackageInquiry::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId))
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('travel_package_id, COUNT(*) as total')
            ->groupBy('travel_package_id')
            ->pluck('total', 'travel_package_id');

        $packagePerformance = $bookingsByPackage->map(function ($row) use ($inquiriesByPackage) {
            $inquiries = (int) ($inquiriesByPackage[$row->package_id] ?? 0);
            $bookings = (int) $row->bookings_count;
            return [
                'package_id'     => $row->package_id,
                'title'          => $row->title_ar ?: $row->title_en,
                'inquiries'      => $inquiries,
                'bookings'       => $bookings,
                'conversion_rate'=> $inquiries > 0 ? round($bookings / $inquiries * 100, 1) : 0,
                'revenue'        => (int) $row->revenue,
                'currency'       => $row->currency,
            ];
        })->values();

        $topPackagesByRevenue = $packagePerformance
            ->sortByDesc('revenue')
            ->take(5)
            ->values();

        return response()->json([
            'total_bookings'      => $totalBookings,
            'completed_bookings'  => $completedBookings,
            'cancelled_bookings'  => $cancelledBookings,
            'cancellation_rate'   => $cancellationRate,
            'avg_booking_value'   => $avgBookingValueByCurrency,
            'total_inquiries'     => $totalInquiries,
            'avg_response_minutes'=> $avgResponseMinutes !== null ? round((float) $avgResponseMinutes) : null,
            'bookings_over_time'  => $bookingsOverTime,
            'revenue_over_time'   => $revenueOverTime,
            'top_packages'        => $topPackagesByRevenue,
            'package_performance' => $packagePerformance,
        ]);
    }
}
