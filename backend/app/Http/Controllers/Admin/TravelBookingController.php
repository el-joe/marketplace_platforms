<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TravelBookingStatus;
use App\Http\Controllers\Controller;
use App\Models\TravelBooking;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TravelBookingController extends Controller
{
    use HasDataTable;
    use HasExport;

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('travel.view'), 403);

        if ($request->filled('export')) {
            return $this->exportBookings($request);
        }

        $thisMonthStart = now()->startOfMonth();

        $stats = [
            'total'       => TravelBooking::count(),
            'this_month'  => TravelBooking::where('created_at', '>=', $thisMonthStart)->count(),
            'confirmed'   => TravelBooking::where('status', TravelBookingStatus::Confirmed)->count(),
            'cancelled'   => TravelBooking::where('status', TravelBookingStatus::Cancelled)->count(),
        ];

        // Revenue grouped by currency — never a single blended sum across currencies
        $revenueByCurrency = TravelBooking::query()
            ->whereIn('travel_bookings.status', [TravelBookingStatus::Confirmed, TravelBookingStatus::Completed])
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->selectRaw('travel_packages.currency, SUM(travel_bookings.total_price) as total')
            ->groupBy('travel_packages.currency')
            ->orderBy('travel_packages.currency')
            ->get()
            ->map(fn($row) => [
                'currency' => $row->currency,
                'formatted' => $row->currency . ' ' . number_format($row->total / 100, 2),
            ]);

        return view('admin.travel-bookings.index', compact('stats', 'revenueByCurrency'));
    }

    // ── DataTable ─────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('travel.view'), 403);

        $query = $this->buildBookingsQuery($request);

        $columns = [
            ['searchable_columns' => ['travel_bookings.booking_number'], 'orderable_column' => 'travel_bookings.booking_number'],
            ['searchable_columns' => ['travel_packages.title_en'], 'orderable_column' => 'travel_packages.title_en'],
            ['searchable_columns' => ['customers.name', 'customers.email'], 'orderable_column' => 'customers.name'],
            ['searchable_columns' => [], 'orderable_column' => 'travel_packages.departure_date'], // travel dates
            ['searchable_columns' => [], 'orderable_column' => 'travel_bookings.total_price'],
            ['searchable_columns' => [], 'orderable_column' => 'travel_bookings.status'],
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        $statusColors = [
            TravelBookingStatus::PendingDocuments->value => 'warning',
            TravelBookingStatus::Confirmed->value        => 'success',
            TravelBookingStatus::Cancelled->value        => 'danger',
            TravelBookingStatus::Completed->value        => 'gray',
        ];

        return $this->dataTableResponse($request, $query, $columns, function (TravelBooking $row) use ($statusColors) {
            $statusColor = $statusColors[$row->status->value] ?? 'gray';
            $statusLabel = $row->status->label();
            $statusBadge = "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{$statusColor}-100 text-{$statusColor}-700\">{$statusLabel}</span>";

            $currency   = $row->package?->currency ?? '';
            $amount     = $currency . ' ' . number_format($row->total_price / 100, 2);

            $departure  = $row->package?->departure_date ? Carbon::parse($row->package->departure_date)->format('d M Y') : '—';
            $returnDate = $row->package?->return_date ? Carbon::parse($row->package->return_date)->format('d M Y') : '—';
            $dateRange  = $departure . ' – ' . $returnDate;

            $showUrl = route('admin.travel.bookings.show', $row->id);

            return [
                'booking_number' => '<span class="font-mono text-xs">' . e($row->booking_number) . '</span>',
                'package'        => e($row->package?->title_en ?? '—'),
                'customer'       => e($row->customer?->name ?? '—') . '<br><span class="text-xs text-gray-400">' . e($row->customer?->email ?? '') . '</span>',
                'travel_dates'   => $dateRange,
                'amount'         => e($amount),
                'status'         => $statusBadge,
                'actions'        => "<a href=\"{$showUrl}\" class=\"btn btn-xs btn-secondary\">View</a>",
                'DT_RowData'     => ['id' => $row->id],
            ];
        });
    }

    // ── Query building / Export ──────────────────────────────────────────────

    private function buildBookingsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = TravelBooking::query()
            ->select('travel_bookings.*')
            ->with(['package.agency', 'customer'])
            ->join('travel_packages', 'travel_packages.id', '=', 'travel_bookings.travel_package_id')
            ->join('customers', 'customers.id', '=', 'travel_bookings.customer_id');

        return $this->applyFilters($query, $request, [
            'search'     => fn($q, $v) => $q->where('travel_bookings.booking_number', 'like', '%' . $v . '%'),
            'status'     => fn($q, $v) => $q->where('travel_bookings.status', $v),
            'package_id' => fn($q, $v) => $q->where('travel_bookings.travel_package_id', $v),
            'date_from'  => fn($q, $v) => $q->whereDate('travel_bookings.created_at', '>=', $v),
            'date_to'    => fn($q, $v) => $q->whereDate('travel_bookings.created_at', '<=', $v),
        ]);
    }

    private function exportBookings(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $bookings = $this->buildBookingsQuery($request)->orderByDesc('travel_bookings.created_at')->get();

        $headers = ['Booking Ref', 'Package', 'Agency', 'Status', 'Price', 'Currency', 'Date'];

        $rows = $bookings->map(fn($booking) => [
            $booking->booking_number,
            $booking->package?->title_en,
            $booking->package?->agency?->name,
            $booking->status?->value,
            number_format($booking->total_price / 100, 2),
            $booking->package?->currency,
            optional($booking->created_at)->format('d M Y H:i'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('travel_bookings', $headers, $rows),
            'csv' => $this->exportCsv('travel_bookings', $headers, $rows),
            'word' => $this->exportWord('travel_bookings', 'Travel Bookings', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(TravelBooking $travelBooking): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('travel.view'), 403);

        $travelBooking->load(['package.agency', 'package.media', 'customer']);

        return view('admin.travel-bookings.show', compact('travelBooking'));
    }
}
