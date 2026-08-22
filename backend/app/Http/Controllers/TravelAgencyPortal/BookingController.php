<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Enums\TravelBookingStatus;
use App\Enums\TravelPackageInquiryStatus;
use App\Enums\TravelPackageStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\TravelAgency;
use App\Models\TravelBooking;
use App\Models\TravelPackage;
use App\Models\TravelPackageInquiry;
use App\Notifications\Customer\TravelBookingCancelled as CustomerTravelBookingCancelled;
use App\Notifications\Customer\TravelBookingConfirmed;
use App\Notifications\TravelAgency\LowSeatsRemaining;
use App\Services\TravelAgency\BookingCreationService;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{
    use ResolvesTravelAgency;
    use HasExport;

    public function __construct(private readonly BookingCreationService $bookingCreationService)
    {
    }

    // ── Customer search (AJAX) ────────────────────────────────────────────────

    public function customerSearch(Request $request): JsonResponse
    {
        $term = trim($request->query('q', ''));

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $customers = Customer::query()
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%");
            })
            ->select('id', 'name', 'email', 'phone')
            ->limit(10)
            ->get();

        return response()->json($customers);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(Request $request): View
    {
        $packages = TravelPackage::where('travel_agency_id', $this->agencyId())
            ->where('status', TravelPackageStatus::Active)
            ->orderBy('departure_date')
            ->get()
            ->filter(fn (TravelPackage $pkg) => $pkg->available_seats === null || $pkg->seatsRemaining() > 0)
            ->values();

        $selectedPackageId = old('travel_package_id', $request->query('package_id'));

        $package = $selectedPackageId
            ? $packages->firstWhere('id', $selectedPackageId)
            : null;

        return view('travel-agency.bookings.create', compact('packages', 'package'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $mode = $request->input('customer_mode', 'existing');

        $rules = [
            'travel_package_id' => [
                'required',
                'uuid',
                Rule::exists('travel_packages', 'id')->where('travel_agency_id', $this->agencyId()),
            ],
            'travelers_count' => ['required', 'integer', 'min:1'],
            'customer_mode'   => ['required', 'in:existing,new'],
        ];

        if ($mode === 'existing') {
            $rules['customer_id'] = ['required', 'uuid', 'exists:customers,id'];
        } else {
            $rules['new_name']  = ['required', 'string', 'max:255'];
            $rules['new_phone'] = ['required', 'string', 'max:30'];
            $rules['new_email'] = ['required', 'email', 'max:255', 'unique:customers,email'];
        }

        $validated = $request->validate($rules);

        $booking = $this->bookingCreationService->create($this->agencyId(), $validated);

        // Link inquiry → booking if this booking originated from a lead inquiry
        if ($inquiryId = $request->input('from_inquiry')) {
            $inquiry = TravelPackageInquiry::where('id', $inquiryId)
                ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $this->agencyId()))
                ->where('status', '!=', TravelPackageInquiryStatus::Converted)
                ->first();

            $inquiry?->update([
                'status'                 => TravelPackageInquiryStatus::Converted,
                'converted_to_booking_id' => $booking->id,
            ]);
        }

        return redirect()
            ->route('travel-agency.bookings.show', $booking)
            ->with('success', __('travel.bookings.created_success'));
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    private function filteredBookingsQuery(Request $request)
    {
        $query = TravelBooking::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $this->agencyId()));

        if ($search = $request->query('search')) {
            $query->where('booking_number', 'like', "%{$search}%");
        }

        if ($status = $request->query('status')) {
            $query->where('status', TravelBookingStatus::from($status));
        }

        if ($packageId = $request->query('package_id')) {
            $query->where('travel_package_id', $packageId);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query;
    }

    public function index(Request $request): View
    {
        $bookings = $this->filteredBookingsQuery($request)
            ->with(['package', 'customer'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $packages = TravelPackage::where('travel_agency_id', $this->agencyId())
            ->orderBy('title_en')
            ->get(['id', 'title_ar', 'title_en']);

        return view('travel-agency.bookings.index', compact('bookings', 'packages'));
    }

    public function export(Request $request): Response|StreamedResponse
    {
        $bookings = $this->filteredBookingsQuery($request)
            ->with(['package', 'customer'])
            ->latest()
            ->get();

        $headers = [
            __('travel.bookings.export.ref'),
            __('travel.bookings.export.package'),
            __('travel.bookings.export.travelers'),
            __('travel.bookings.export.total'),
            __('travel.bookings.export.currency'),
            __('travel.bookings.export.status'),
            __('travel.bookings.export.date'),
        ];

        $rows = $bookings->map(fn (TravelBooking $booking) => [
            $booking->booking_number,
            $booking->package->title_en ?? '',
            $booking->travelers_count,
            $booking->total_price,
            $booking->package->currency ?? '',
            $booking->status->value,
            $booking->created_at?->toDateString(),
        ]);

        $filename = 'bookings-' . now()->toDateString();
        $format = $request->input('format', 'csv');

        return match ($format) {
            'excel' => $this->exportExcel($filename, $headers, $rows),
            'word'  => $this->exportWord($filename, __('travel.bookings.export.sheet_title'), $rows),
            'csv'   => $this->exportCsv($filename, $headers, $rows),
            default => abort(400, __('travel.export.invalid_format')),
        };
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(TravelBooking $booking): View
    {
        $this->authorise($booking);
        $booking->load(['package', 'customer']);

        return view('travel-agency.bookings.show', compact('booking'));
    }

    // ── Status update (confirm / cancel) ─────────────────────────────────────

    public function updateStatus(Request $request, TravelBooking $booking): RedirectResponse
    {
        $this->authorise($booking);

        $request->validate([
            'status' => ['required', 'in:confirmed,cancelled'],
            'cancellation_reason' => ['required_if:status,cancelled', 'nullable', 'string', 'max:1000'],
        ]);

        $newStatus = TravelBookingStatus::from($request->status);

        $allowed = match ($newStatus) {
            TravelBookingStatus::Confirmed => in_array($booking->status, [TravelBookingStatus::PendingDocuments]),
            TravelBookingStatus::Cancelled => in_array($booking->status, [TravelBookingStatus::PendingDocuments, TravelBookingStatus::Confirmed]),
            default     => false,
        };

        if (!$allowed) {
            return back()->withErrors(['status' => __('travel.bookings.status_change_forbidden')]);
        }

        if ($newStatus === TravelBookingStatus::Confirmed && !$booking->passport_file_path) {
            return back()->withErrors(['status' => __('travel.bookings.confirm_requires_passport')]);
        }

        $cancellationReason = $request->input('cancellation_reason');

        DB::transaction(function () use ($booking, $newStatus, $cancellationReason) {
            if ($newStatus === TravelBookingStatus::Confirmed) {
                $pkg = TravelPackage::lockForUpdate()->findOrFail($booking->travel_package_id);

                if ($pkg->available_seats !== null
                    && ($pkg->seats_booked + $booking->travelers_count) > $pkg->available_seats
                ) {
                    abort(422, __('travel.bookings.insufficient_seats'));
                }

                $pkg->increment('seats_booked', $booking->travelers_count);
                $pkg->refresh();

                if ($pkg->available_seats !== null
                    && $pkg->seats_booked >= $pkg->available_seats
                ) {
                    $pkg->update(['status' => TravelPackageStatus::SoldOut]);
                } elseif ($pkg->available_seats !== null) {
                    $remaining = $pkg->available_seats - $pkg->seats_booked;
                    if ($remaining > 0 && $remaining <= 3) {
                        Notification::send($pkg->agency->activeMembers(), new LowSeatsRemaining($pkg, $remaining));
                    }
                }
            }

            if ($newStatus === TravelBookingStatus::Cancelled && $booking->status === TravelBookingStatus::Confirmed) {
                $booking->package()->increment('seats_booked', -$booking->travelers_count);
            }

            $booking->update([
                'status' => $newStatus,
                ...($newStatus === TravelBookingStatus::Cancelled ? ['cancellation_reason' => $cancellationReason] : []),
            ]);
        });

        $booking->loadMissing('customer');

        /** @var \App\Models\TravelAgencyMember $agencyMember */
        $agencyMember = $this->member();

        if ($newStatus === TravelBookingStatus::Confirmed) {
            $booking->customer?->notify(new TravelBookingConfirmed($booking));
        } elseif ($newStatus === TravelBookingStatus::Cancelled) {
            $booking->customer?->notify(new CustomerTravelBookingCancelled($booking, 'agency'));
        }

        Activity::create([
            'log_name'     => 'travel_bookings',
            'description'  => $newStatus === TravelBookingStatus::Confirmed ? 'Booking confirmed' : 'Booking cancelled',
            'subject_type' => TravelBooking::class,
            'subject_id'   => $booking->id,
            'causer_type'  => TravelAgency::class,
            'causer_id'    => $agencyMember->travel_agency_id,
            'event'        => $newStatus->value,
            'properties'   => json_encode(array_filter(['cancellation_reason' => $cancellationReason])),
            'ip_address'   => $request->ip(),
        ]);

        $label = $newStatus === TravelBookingStatus::Confirmed
            ? __('travel.bookings.confirm_success')
            : __('travel.bookings.cancel_success');

        return back()->with('success', $label);
    }

    // ── Auth guards ───────────────────────────────────────────────────────────

    private function authorise(TravelBooking $booking): void
    {
        if ($booking->package->travel_agency_id !== $this->agencyId()) {
            abort(403);
        }
    }
}
