<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Enums\TravelBookingStatus;
use App\Enums\TravelPackageInquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use App\Traits\HasExport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    use ResolvesTravelAgency;
    use HasExport;

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

    private function commissionRate(): float
    {
        return (float) config('travel.platform_commission_rate', 0.10);
    }

    // ── Report 1: Revenue ──────────────────────────────────────────────────────

    public function revenue(Request $request): View
    {
        $agencyId = $this->agencyId();
        [$from, $to] = $this->dateRange($request);
        $rate = $this->commissionRate();

        $baseQuery = TravelBooking::query()
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->where('travel_packages.travel_agency_id', $agencyId)
            ->whereBetween('travel_bookings.created_at', [$from, $to]);

        // Summary cards — grouped by currency, never summed across currencies.
        $revenueByCurrency = (clone $baseQuery)
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
            })
            ->keyBy('currency');

        $cancelledByCurrency = (clone $baseQuery)
            ->where('travel_bookings.status', TravelBookingStatus::Cancelled)
            ->selectRaw('travel_packages.currency as currency, SUM(travel_bookings.total_price) as total')
            ->groupBy('travel_packages.currency')
            ->pluck('total', 'currency');

        // Daily revenue chart data, grouped by currency.
        $dailyRevenue = (clone $baseQuery)
            ->where('travel_bookings.status', TravelBookingStatus::Completed)
            ->selectRaw('DATE(travel_bookings.created_at) as day, travel_packages.currency as currency, SUM(travel_bookings.total_price) as total')
            ->groupBy('day', 'currency')
            ->orderBy('day')
            ->get()
            ->groupBy('currency')
            ->map(fn ($rows) => $rows->map(fn ($r) => ['day' => $r->day, 'total' => (int) $r->total])->values());

        // Monthly breakdown table.
        $monthlyBreakdown = (clone $baseQuery)
            ->selectRaw("
                DATE_FORMAT(travel_bookings.created_at, '%Y-%m') as month,
                travel_packages.currency as currency,
                COUNT(*) as bookings_count,
                SUM(CASE WHEN travel_bookings.status = 'completed' THEN travel_bookings.total_price ELSE 0 END) as revenue
            ")
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

        return view('travel-agency.reports.revenue', compact(
            'from', 'to', 'revenueByCurrency', 'cancelledByCurrency', 'dailyRevenue', 'monthlyBreakdown',
        ));
    }

    public function exportRevenue(Request $request): Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $agencyId = $this->agencyId();
        [$from, $to] = $this->dateRange($request);
        $rate = $this->commissionRate();

        $monthlyBreakdown = TravelBooking::query()
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->where('travel_packages.travel_agency_id', $agencyId)
            ->whereBetween('travel_bookings.created_at', [$from, $to])
            ->selectRaw("
                DATE_FORMAT(travel_bookings.created_at, '%Y-%m') as month,
                travel_packages.currency as currency,
                COUNT(*) as bookings_count,
                SUM(CASE WHEN travel_bookings.status = 'completed' THEN travel_bookings.total_price ELSE 0 END) as revenue
            ")
            ->groupBy('month', 'currency')
            ->orderByDesc('month')
            ->get();

        $headers = [
            __('travel.reports.revenue_export.month'),
            __('travel.reports.revenue_export.bookings_count'),
            __('travel.reports.revenue_export.revenue'),
            __('travel.reports.revenue_export.commission'),
            __('travel.reports.revenue_export.net'),
            __('travel.reports.revenue_export.currency'),
        ];

        $rows = $monthlyBreakdown->map(function ($row) use ($rate) {
            $commission = (int) round($row->revenue * $rate);
            return [
                $row->month,
                (int) $row->bookings_count,
                (int) $row->revenue,
                $commission,
                (int) $row->revenue - $commission,
                $row->currency,
            ];
        });

        $filename = 'revenue-report-' . $from->toDateString() . '-to-' . $to->toDateString();
        $format = $request->input('format', 'csv');

        return match ($format) {
            'excel' => $this->exportExcel($filename, $headers, $rows),
            'word' => $this->exportWord($filename, __('travel.reports.revenue_export.sheet_title'), $rows),
            'csv' => $this->exportCsv($filename, $headers, $rows),
            default => abort(400, __('travel.export.invalid_format')),
        };
    }

    // ── Report 2: Bookings ─────────────────────────────────────────────────────

    public function bookings(Request $request): View
    {
        $agencyId = $this->agencyId();

        $query = TravelBooking::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId));

        $baseSummaryQuery = clone $query;

        if ($status = $request->query('status')) {
            $query->where('status', TravelBookingStatus::from($status));
        }

        if ($request->query('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->query('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        if ($packageId = $request->query('package_id')) {
            $query->where('travel_package_id', $packageId);
        }

        if ($country = $request->query('destination_country')) {
            $query->whereHas('package', fn ($q) => $q->where('destination_country', $country));
        }

        $bookings = $query->with(['package', 'customer'])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $summary = [
            'total'     => (clone $baseSummaryQuery)->count(),
            'confirmed' => (clone $baseSummaryQuery)->where('status', TravelBookingStatus::Confirmed)->count(),
            'completed' => (clone $baseSummaryQuery)->where('status', TravelBookingStatus::Completed)->count(),
            'cancelled' => (clone $baseSummaryQuery)->where('status', TravelBookingStatus::Cancelled)->count(),
            'pending'   => (clone $baseSummaryQuery)->where('status', TravelBookingStatus::PendingDocuments)->count(),
        ];

        $byStatus = (clone $baseSummaryQuery)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $overTime = (clone $baseSummaryQuery)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as cnt')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $packages = TravelPackage::where('travel_agency_id', $agencyId)
            ->orderBy('title_en')
            ->get(['id', 'title_ar', 'title_en']);

        $countries = TravelPackage::where('travel_agency_id', $agencyId)
            ->whereNotNull('destination_country')
            ->distinct()
            ->orderBy('destination_country')
            ->pluck('destination_country');

        return view('travel-agency.reports.bookings', compact(
            'bookings', 'summary', 'byStatus', 'overTime', 'packages', 'countries',
        ));
    }

    public function exportBookings(Request $request): Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $agencyId = $this->agencyId();

        $query = TravelBooking::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId));

        if ($status = $request->query('status')) {
            $query->where('status', TravelBookingStatus::from($status));
        }

        if ($request->query('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->query('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        if ($packageId = $request->query('package_id')) {
            $query->where('travel_package_id', $packageId);
        }

        if ($country = $request->query('destination_country')) {
            $query->whereHas('package', fn ($q) => $q->where('destination_country', $country));
        }

        $bookings = $query->with(['package', 'customer'])->latest()->get();

        $headers = [
            __('travel.reports.bookings_export.booking_ref'),
            __('travel.reports.bookings_export.customer_name'),
            __('travel.reports.bookings_export.package'),
            __('travel.reports.bookings_export.travel_date'),
            __('travel.reports.bookings_export.price'),
            __('travel.reports.bookings_export.currency'),
            __('travel.reports.bookings_export.status'),
        ];

        $rows = $bookings->map(fn($bk) => [
            $bk->booking_number,
            $bk->customer->name ?? '',
            $bk->package->title_en ?? '',
            $bk->package->departure_date?->toDateString() ?? '',
            $bk->total_price,
            $bk->package->currency ?? '',
            $bk->status->value,
        ]);

        $filename = 'bookings-report-' . now()->toDateString();
        $format = $request->input('format', 'csv');

        return match ($format) {
            'excel' => $this->exportExcel($filename, $headers, $rows),
            'word' => $this->exportWord($filename, __('travel.reports.bookings_export.sheet_title'), $rows),
            'csv' => $this->exportCsv($filename, $headers, $rows),
            default => abort(400, __('travel.export.invalid_format')),
        };
    }

    // ── Report 3: Packages Performance ─────────────────────────────────────────

    public function packages(Request $request): View
    {
        $agencyId = $this->agencyId();
        $sort = $request->query('sort', 'bookings');

        $packages = TravelPackage::where('travel_agency_id', $agencyId)
            ->withCount('inquiries')
            ->withCount('bookings')
            ->with(['bookings' => fn ($q) => $q->where('status', TravelBookingStatus::Completed)])
            ->get()
            ->map(function (TravelPackage $package) {
                $revenue = $package->bookings->sum('total_price');
                $bookingsCount = $package->bookings_count;
                $inquiriesCount = $package->inquiries_count;

                return [
                    'id'                => $package->id,
                    'title'             => $package->title_en,
                    'destination'       => trim(($package->destination_city ? $package->destination_city . ', ' : '') . $package->destination_country),
                    'inquiries'         => $inquiriesCount,
                    'bookings'          => $bookingsCount,
                    'conversion_rate'   => $inquiriesCount > 0 ? round(($bookingsCount / $inquiriesCount) * 100, 1) : 0.0,
                    'revenue'           => (int) $revenue,
                    'avg_booking_value' => $bookingsCount > 0 ? (int) round($revenue / $bookingsCount) : 0,
                    'currency'          => $package->currency,
                    'status'            => $package->status->value ?? $package->status,
                ];
            });

        $packages = match ($sort) {
            'revenue'      => $packages->sortByDesc('revenue')->values(),
            'conversion'   => $packages->sortByDesc('conversion_rate')->values(),
            default        => $packages->sortByDesc('bookings')->values(),
        };

        return view('travel-agency.reports.packages', compact('packages', 'sort'));
    }
}
