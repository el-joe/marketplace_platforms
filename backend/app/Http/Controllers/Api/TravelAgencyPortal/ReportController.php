<?php

namespace App\Http\Controllers\Api\TravelAgencyPortal;

use App\Enums\TravelBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    private function agencyId(): string
    {
        return auth()->guard('travel_agencies')->user()->id;
    }

    private function dateRange(Request $request): array
    {
        $from = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    private function commissionRate(): float
    {
        return (float) config('travel.platform_commission_rate', 0.10);
    }

    /** GET /api/travel-agency/v1/reports/revenue */
    public function revenue(Request $request): JsonResponse
    {
        $agencyId = $this->agencyId();
        [$from, $to] = $this->dateRange($request);
        $rate = $this->commissionRate();

        $base = TravelBooking::query()
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->where('travel_packages.travel_agency_id', $agencyId)
            ->whereBetween('travel_bookings.created_at', [$from, $to]);

        $revenueByCurrency = (clone $base)
            ->where('travel_bookings.status', TravelBookingStatus::Completed)
            ->selectRaw('travel_packages.currency as currency, SUM(travel_bookings.total_price) as total')
            ->groupBy('travel_packages.currency')
            ->get()
            ->map(function ($row) use ($rate) {
                $commission = (int) round($row->total * $rate);
                return [
                    'currency'   => $row->currency,
                    'revenue'    => (int) $row->total,
                    'commission' => $commission,
                    'net'        => (int) $row->total - $commission,
                ];
            });

        $dailyRevenue = (clone $base)
            ->where('travel_bookings.status', TravelBookingStatus::Completed)
            ->selectRaw('DATE(travel_bookings.created_at) as day, travel_packages.currency as currency, SUM(travel_bookings.total_price) as total')
            ->groupBy('day', 'currency')
            ->orderBy('day')
            ->get()
            ->groupBy('currency')
            ->map(fn ($rows) => $rows->map(fn ($r) => ['day' => $r->day, 'total' => (int) $r->total])->values());

        $monthlyBreakdown = (clone $base)
            ->selectRaw("DATE_FORMAT(travel_bookings.created_at, '%Y-%m') as month, travel_packages.currency as currency, COUNT(*) as bookings_count, SUM(CASE WHEN travel_bookings.status = 'completed' THEN travel_bookings.total_price ELSE 0 END) as revenue")
            ->groupBy('month', 'currency')
            ->orderByDesc('month')
            ->get()
            ->map(function ($row) use ($rate) {
                $commission = (int) round($row->revenue * $rate);
                return [
                    'month'          => $row->month,
                    'bookings_count' => (int) $row->bookings_count,
                    'revenue'        => (int) $row->revenue,
                    'commission'     => $commission,
                    'net'            => (int) $row->revenue - $commission,
                    'currency'       => $row->currency,
                ];
            });

        return response()->json([
            'date_from'          => $from->toDateString(),
            'date_to'            => $to->toDateString(),
            'revenue_by_currency'=> $revenueByCurrency,
            'daily_revenue'      => $dailyRevenue,
            'monthly_breakdown'  => $monthlyBreakdown,
        ]);
    }

    /** GET /api/travel-agency/v1/reports/bookings */
    public function bookings(Request $request): JsonResponse
    {
        $agencyId = $this->agencyId();
        [$from, $to] = $this->dateRange($request);

        $base = TravelBooking::query()
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->where('travel_packages.travel_agency_id', $agencyId)
            ->whereBetween('travel_bookings.created_at', [$from, $to]);

        $summary = [
            'total'     => (clone $base)->count(),
            'confirmed' => (clone $base)->where('travel_bookings.status', TravelBookingStatus::Confirmed)->count(),
            'completed' => (clone $base)->where('travel_bookings.status', TravelBookingStatus::Completed)->count(),
            'cancelled' => (clone $base)->where('travel_bookings.status', TravelBookingStatus::Cancelled)->count(),
            'pending'   => (clone $base)->where('travel_bookings.status', TravelBookingStatus::PendingDocuments)->count(),
        ];

        $byStatus = (clone $base)
            ->selectRaw('travel_bookings.status, COUNT(*) as total')
            ->groupBy('travel_bookings.status')
            ->pluck('total', 'status');

        $dailyBookings = (clone $base)
            ->selectRaw('DATE(travel_bookings.created_at) as day, COUNT(*) as total')
            ->groupBy('day')->orderBy('day')
            ->get()
            ->map(fn ($r) => ['day' => $r->day, 'total' => (int) $r->total]);

        $byPackage = (clone $base)
            ->selectRaw('travel_packages.id as package_id, travel_packages.title_ar, travel_packages.title_en, COUNT(*) as bookings_count')
            ->groupBy('travel_packages.id', 'travel_packages.title_ar', 'travel_packages.title_en')
            ->orderByDesc('bookings_count')
            ->limit(10)->get()
            ->map(fn ($r) => [
                'package_id'     => $r->package_id,
                'title'          => $r->title_ar ?: $r->title_en,
                'bookings_count' => (int) $r->bookings_count,
            ]);

        return response()->json([
            'date_from'      => $from->toDateString(),
            'date_to'        => $to->toDateString(),
            'summary'        => $summary,
            'by_status'      => $byStatus,
            'daily_bookings' => $dailyBookings,
            'by_package'     => $byPackage,
        ]);
    }

    /** GET /api/travel-agency/v1/reports/packages */
    public function packages(Request $request): JsonResponse
    {
        $agencyId = $this->agencyId();
        [$from, $to] = $this->dateRange($request);

        $packages = TravelPackage::where('travel_agency_id', $agencyId)
            ->withCount(['bookings as total_bookings',
                'bookings as completed_bookings' => fn ($q) => $q->where('status', TravelBookingStatus::Completed),
            ])
            ->with(['destinationCountry:id,name_en', 'destinationCity:id,name_en'])
            ->latest()->paginate(20);

        return response()->json([
            'date_from' => $from->toDateString(),
            'date_to'   => $to->toDateString(),
            'packages'  => $packages->map(fn ($p) => [
                'id'                 => $p->id,
                'title'              => $p->title_ar ?: $p->title_en,
                'status'             => $p->status,
                'price'              => (int) $p->price,
                'currency'           => $p->currency,
                'destination'        => trim(($p->destinationCity?->name_en ?? '') . ' ' . ($p->destinationCountry?->name_en ?? '')),
                'total_bookings'     => $p->total_bookings,
                'completed_bookings' => $p->completed_bookings,
            ]),
            'meta' => ['current_page' => $packages->currentPage(), 'last_page' => $packages->lastPage(), 'total' => $packages->total()],
        ]);
    }
}
