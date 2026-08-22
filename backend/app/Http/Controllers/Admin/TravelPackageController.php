<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TravelBookingStatus;
use App\Enums\TravelPackageStatus;
use App\Http\Controllers\Controller;
use App\Models\TravelCountry;
use App\Models\TravelPackage;
use Illuminate\Support\Facades\Storage;
use App\Notifications\TravelAgency\PackageApproved;
use App\Notifications\TravelAgency\PackageRejected;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TravelPackageController extends Controller
{
    use HasDataTable;
    use HasExport;

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('travel.view'), 403);

        if ($request->filled('export')) {
            return $this->exportPackages($request);
        }

        $urgencyThreshold = now()->addDays(7);

        $stats = [
            'pending' => TravelPackage::where('status', TravelPackageStatus::PendingReview)->count(),
            'active' => TravelPackage::where('status', TravelPackageStatus::Active)->count(),
            'sold_out' => TravelPackage::where('status', TravelPackageStatus::SoldOut)->count(),
            // Packages departing within 7 days that are still pending — fulfillment urgency
            'urgent' => TravelPackage::where('status', TravelPackageStatus::PendingReview)
                ->whereDate('departure_date', '<=', $urgencyThreshold)
                ->count(),
        ];

        $travelCountries = TravelCountry::where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'flag_emoji']);

        return view('admin.travel-packages.index', compact('stats', 'travelCountries'));
    }

    // ── DataTable ─────────────────────────────────────────────────────────────

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('travel.view'), 403);

        $query = $this->buildPackagesQuery($request);

        $columns = [
            ['searchable_columns' => ['travel_packages.title_en', 'travel_packages.title_ar'], 'orderable_column' => 'travel_packages.title_en'],
            ['searchable_columns' => ['travel_agencies.name'], 'orderable_column' => 'travel_agencies.name'],
            ['searchable_columns' => [], 'orderable_column' => null], // destination
            ['searchable_columns' => [], 'orderable_column' => 'travel_packages.price'],
            ['searchable_columns' => [], 'orderable_column' => 'travel_packages.departure_date'],
            ['searchable_columns' => [], 'orderable_column' => null], // seats
            ['searchable_columns' => [], 'orderable_column' => 'travel_packages.status'],
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        $statusColors = [
            TravelPackageStatus::Draft->value => 'gray',
            TravelPackageStatus::PendingReview->value => 'warning',
            TravelPackageStatus::Active->value => 'success',
            TravelPackageStatus::SoldOut->value => 'purple',
            TravelPackageStatus::Expired->value => 'gray',
        ];

        $canEdit = $admin->hasPermissionTo('travel.view');

        return $this->dataTableResponse($request, $query, $columns, function (TravelPackage $row) use ($statusColors, $canEdit) {
            $cover = $row->media->where('media_type', 'image')->first();
            $thumbHtml = $cover
                ? "<img src=\"/storage/{$cover->file_path}\" class=\"w-10 h-10 rounded object-cover inline-block mr-2 align-middle\" alt=\"\">"
                : "<span class=\"inline-block w-10 h-10 rounded bg-gray-100 mr-2 align-middle\"></span>";

            $title = $thumbHtml . '<span class="font-medium text-gray-900">' . e($row->title_en) . '</span>';

            $country = $row->destinationCountry;
            $destination = $country
                ? ($country->flag_emoji ? $country->flag_emoji . ' ' : '') . e($country->name_en)
                : e($row->destination_country ?? '—');
            if ($row->destination_city) {
                $destination .= '<br><span class="text-xs text-gray-400">' . e($row->destination_city) . '</span>';
            }

            $price = e($row->currency) . ' ' . number_format($row->price / 100, 2);

            $departure = Carbon::parse($row->departure_date)->format('d M Y');

            $seatsAvail = $row->available_seats;
            $seatsBooked = $row->seats_booked;
            $seatDisplay = $seatsAvail !== null ? "{$seatsBooked} / {$seatsAvail}" : "{$seatsBooked} / ∞";

            $statusColor = $statusColors[$row->status->value] ?? 'gray';
            $statusLabel = $row->status->label();
            $statusBadge = "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{$statusColor}-100 text-{$statusColor}-700\">{$statusLabel}</span>";

            $showUrl = route('admin.travel.packages.show', $row->id);
            $approveUrl = route('admin.travel.packages.approve', $row->id);
            $rejectUrl = route('admin.travel.packages.reject', $row->id);

            $actions = '<div class="flex items-center gap-1">';
            $actions .= "<a href=\"{$showUrl}\" class=\"btn btn-xs btn-secondary\">View</a>";
            if ($canEdit && $row->status === TravelPackageStatus::PendingReview) {
                $actions .= "<button type=\"button\" class=\"btn btn-xs btn-success js-approve-btn\" data-url=\"{$approveUrl}\" data-name=\"" . e($row->title_en) . "\">Approve</button>";
                $actions .= "<button type=\"button\" class=\"btn btn-xs btn-danger js-reject-btn\" data-url=\"{$rejectUrl}\" data-name=\"" . e($row->title_en) . "\">Reject</button>";
            }
            $actions .= '</div>';

            return [
                'title' => $title,
                'agency' => e($row->agency?->name ?? '—'),
                'destination' => $destination,
                'price' => $price,
                'departure' => $departure,
                'seats' => $seatDisplay,
                'status' => $statusBadge,
                'actions' => $actions,
                'DT_RowData' => ['id' => $row->id, 'status' => $row->status?->value],
            ];
        });
    }

    // ── Query building / Export ──────────────────────────────────────────────

    private function buildPackagesQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = TravelPackage::query()
            ->select('travel_packages.*')
            ->with(['agency', 'destinationCountry', 'media'])
            ->join('travel_agencies', 'travel_agencies.id', '=', 'travel_packages.travel_agency_id');

        return $this->applyFilters($query, $request, [
            'search' => fn($q, $v) => $q->where('travel_packages.title_en', 'like', '%' . $v . '%'),
            'status' => fn($q, $v) => $q->where('travel_packages.status', $v),
            'destination_travel_country_id' => fn($q, $v) => $q->where('travel_packages.destination_travel_country_id', $v),
            'agency_id' => fn($q, $v) => $q->where('travel_packages.travel_agency_id', $v),
            'departure_from' => fn($q, $v) => $q->whereDate('travel_packages.departure_date', '>=', $v),
            'departure_to' => fn($q, $v) => $q->whereDate('travel_packages.departure_date', '<=', $v),
        ]);
    }

    private function exportPackages(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $packages = $this->buildPackagesQuery($request)->orderByDesc('travel_packages.departure_date')->get();

        $headers = ['Package', 'Agency', 'Destination', 'Price', 'Currency', 'Status', 'Date'];

        $rows = $packages->map(fn($pkg) => [
            $pkg->title_en,
            $pkg->agency?->name,
            $pkg->destinationCountry?->name_en ?? $pkg->destination_country,
            number_format($pkg->price / 100, 2),
            $pkg->currency,
            $pkg->status?->value,
            optional($pkg->departure_date)->format('d M Y'),
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('travel_packages', $headers, $rows),
            'csv' => $this->exportCsv('travel_packages', $headers, $rows),
            'word' => $this->exportWord('travel_packages', 'Travel Packages', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(TravelPackage $travelPackage): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('travel.view'), 403);

        $travelPackage->load([
            'agency',
            'media',
            'categories',
            'inclusions',
            'approvedByAdmin',
            'destinationCountry',
            'destinationCity',
        ]);

        $bookingStats = [
            'total' => $travelPackage->bookings()->count(),
            'confirmed' => $travelPackage->bookings()->where('status', TravelBookingStatus::Confirmed)->count(),
            'cancelled' => $travelPackage->bookings()->where('status', TravelBookingStatus::Cancelled)->count(),
            'revenue' => $travelPackage->bookings()
                ->whereIn('status', [TravelBookingStatus::Confirmed, TravelBookingStatus::Completed])
                ->sum('total_price'),
        ];

        $fillPct = ($travelPackage->available_seats && $travelPackage->available_seats > 0)
            ? min(100, round($travelPackage->seats_booked / $travelPackage->available_seats * 100))
            : null;

        return view('admin.travel-packages.show', compact(
            'travelPackage',
            'bookingStats',
            'fillPct'
        ));
    }

    // ── Approve ───────────────────────────────────────────────────────────────

    public function approve(TravelPackage $travelPackage): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('travel.approve'), 403);

        if ($travelPackage->status !== TravelPackageStatus::PendingReview) {
            return response()->json(['message' => 'Package is not pending review.'], 422);
        }

        // Minimum lead time: 3 days (judgment call — shorter windows make
        // contract signing, document collection, and logistics unrealistic).
        // Raise or lower this threshold if business requirements change.
        // $minimumDeparture = now()->addDays(3);
        // if ($travelPackage->departure_date->lt($minimumDeparture)) {
        //     return response()->json([
        //         'message' => 'Cannot approve: departure is in the past or fewer than 3 days away — insufficient time to fulfill.',
        //     ], 422);
        // }

        $travelPackage->update([
            'status' => TravelPackageStatus::Active,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $travelPackage->agency->ownerMember()?->notify(new PackageApproved($travelPackage));

        return response()->json(['message' => 'Package approved and is now live.']);
    }

    // ── Reject ────────────────────────────────────────────────────────────────

    public function reject(Request $request, TravelPackage $travelPackage): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('travel.reject'), 403);

        $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        // The status enum has no 'rejected' value; package is returned to draft
        // so the agency can amend and resubmit. The rejection_reason preserves
        // context that would otherwise be lost with a silent status flip.
        $travelPackage->update([
            'status' => TravelPackageStatus::Draft,
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        $travelPackage->agency->ownerMember()?->notify(new PackageRejected($travelPackage, $request->input('rejection_reason')));

        return response()->json(['message' => 'Package returned to agency as draft with reason recorded.']);
    }

    // ── Expire ────────────────────────────────────────────────────────────────

    public function expire(TravelPackage $travelPackage): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('travel.suspend'), 403);

        if (!in_array($travelPackage->status, [TravelPackageStatus::Active, TravelPackageStatus::SoldOut])) {
            return response()->json(['message' => 'Package cannot be expired from its current status.'], 422);
        }

        $travelPackage->update(['status' => TravelPackageStatus::Expired]);

        return response()->json(['message' => 'Package marked as expired.']);
    }

    // ── Download contract ─────────────────────────────────────────────────────

    public function downloadContract(TravelPackage $travelPackage): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('travel.view'), 403);

        abort_unless(
            $travelPackage->contract_file_path && Storage::disk('local')->exists($travelPackage->contract_file_path),
            404
        );

        return Storage::disk('local')->download(
            $travelPackage->contract_file_path,
            $travelPackage->contract_file_original_name ?? 'contract.pdf'
        );
    }
}
