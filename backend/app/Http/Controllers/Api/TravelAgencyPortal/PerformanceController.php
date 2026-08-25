<?php

namespace App\Http\Controllers\Api\TravelAgencyPortal;

use App\Enums\TravelBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\TravelBooking;
use App\Models\TravelPackageInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PerformanceController extends Controller
{
    private function agencyId(): string
    {
        return auth()->guard('travel_agencies')->user()->id;
    }

    /** GET /api/travel-agency/v1/performance?date_from=&date_to= */
    public function stats(Request $request): JsonResponse
    {
        $agencyId = $this->agencyId();

        $from = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        $base = TravelBooking::query()
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->where('travel_packages.travel_agency_id', $agencyId)
            ->whereBetween('travel_bookings.created_at', [$from, $to]);

        $total     = (clone $base)->count();
        $completed = (clone $base)->where('travel_bookings.status', TravelBookingStatus::Completed)->count();
        $cancelled = (clone $base)->where('travel_bookings.status', TravelBookingStatus::Cancelled)->count();

        $avgValueByCurrency = (clone $base)
            ->where('travel_bookings.status', TravelBookingStatus::Completed)
            ->selectRaw('travel_packages.currency as currency, AVG(travel_bookings.total_price) as avg_value, SUM(travel_bookings.total_price) as total_revenue')
            ->groupBy('travel_packages.currency')
            ->get()
            ->map(fn ($r) => [
                'currency'      => $r->currency,
                'avg_value'     => (int) round((float) $r->avg_value),
                'total_revenue' => (int) $r->total_revenue,
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

        $bookingsOverTime = (clone $base)
            ->selectRaw('DATE(travel_bookings.created_at) as day, COUNT(*) as total')
            ->groupBy('day')->orderBy('day')
            ->get()
            ->map(fn ($r) => ['day' => $r->day, 'total' => (int) $r->total]);

        $revenueOverTime = (clone $base)
            ->where('travel_bookings.status', TravelBookingStatus::Completed)
            ->selectRaw('DATE(travel_bookings.created_at) as day, travel_packages.currency as currency, SUM(travel_bookings.total_price) as total')
            ->groupBy('day', 'currency')->orderBy('day')
            ->get()
            ->groupBy('currency')
            ->map(fn ($rows) => $rows->map(fn ($r) => ['day' => $r->day, 'total' => (int) $r->total])->values());

        $packagePerformance = (clone $base)
            ->selectRaw('travel_packages.id as package_id, travel_packages.title_ar, travel_packages.title_en, travel_packages.currency, COUNT(*) as bookings_count, SUM(CASE WHEN travel_bookings.status = ? THEN travel_bookings.total_price ELSE 0 END) as revenue', [TravelBookingStatus::Completed->value])
            ->groupBy('travel_packages.id', 'travel_packages.title_ar', 'travel_packages.title_en', 'travel_packages.currency')
            ->get()
            ->map(fn ($r) => [
                'package_id' => $r->package_id,
                'title'      => $r->title_ar ?: $r->title_en,
                'bookings'   => (int) $r->bookings_count,
                'revenue'    => (int) $r->revenue,
                'currency'   => $r->currency,
            ]);

        return response()->json([
            'date_from'            => $from->toDateString(),
            'date_to'              => $to->toDateString(),
            'total_bookings'       => $total,
            'completed_bookings'   => $completed,
            'cancelled_bookings'   => $cancelled,
            'cancellation_rate'    => $total > 0 ? round($cancelled / $total * 100, 1) : 0,
            'avg_booking_value'    => $avgValueByCurrency,
            'total_inquiries'      => $totalInquiries,
            'avg_response_minutes' => $avgResponseMinutes !== null ? (int) round((float) $avgResponseMinutes) : null,
            'bookings_over_time'   => $bookingsOverTime,
            'revenue_over_time'    => $revenueOverTime,
            'package_performance'  => $packagePerformance,
        ]);
    }
}
