<?php

namespace App\Http\Controllers\TravelAgencyPortal;

use App\Enums\TravelPackageInquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TravelAgencyPortal\Concerns\ResolvesTravelAgency;
use App\Models\Customer;
use App\Models\TravelPackage;
use App\Models\TravelPackageInquiry;
use App\Services\TravelAgency\BookingCreationService;
use App\Traits\HasExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackageInquiryController extends Controller
{
    use ResolvesTravelAgency;
    use HasExport;

    public function __construct(private readonly BookingCreationService $bookingCreationService)
    {
    }

    private function authorise(TravelPackageInquiry $inquiry): void
    {
        if ($inquiry->package->travel_agency_id !== $this->agencyId()) {
            abort(403);
        }
    }

    // ── Index — all inquiries across the agency's packages ───────────────────

    private function filteredInquiriesQuery(Request $request)
    {
        $query = TravelPackageInquiry::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $this->agencyId()))
            ->latest();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', TravelPackageInquiryStatus::from($status));
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
        $inquiries = $this->filteredInquiriesQuery($request)
            ->with('package')
            ->paginate(30)
            ->withQueryString();

        $packages = TravelPackage::where('travel_agency_id', $this->agencyId())
            ->orderBy('title_ar')
            ->get(['id', 'title_ar', 'title_en']);

        return view('travel-agency.inquiries.index', compact('inquiries', 'packages'));
    }

    public function export(Request $request): Response|StreamedResponse
    {
        $inquiries = $this->filteredInquiriesQuery($request)
            ->with('package')
            ->get();

        $headers = [
            __('travel.inquiries.export.inquirer'),
            __('travel.inquiries.export.package'),
            __('travel.inquiries.export.status'),
            __('travel.inquiries.export.date'),
        ];

        $rows = $inquiries->map(fn (TravelPackageInquiry $inquiry) => [
            $inquiry->name,
            $inquiry->package->title_en ?? '',
            $inquiry->status->value,
            $inquiry->created_at?->toDateString(),
        ]);

        $filename = 'inquiries-' . now()->toDateString();
        $format = $request->input('format', 'csv');

        return match ($format) {
            'excel' => $this->exportExcel($filename, $headers, $rows),
            'word'  => $this->exportWord($filename, __('travel.inquiries.export.sheet_title'), $rows),
            'csv'   => $this->exportCsv($filename, $headers, $rows),
            default => abort(400, __('travel.export.invalid_format')),
        };
    }

    // ── Mark Contacted ───────────────────────────────────────────────────────

    public function markContacted(TravelPackageInquiry $inquiry): RedirectResponse
    {
        $this->authorise($inquiry);

        if (!in_array($inquiry->status, [TravelPackageInquiryStatus::New])) {
            return back()->withErrors(['status' => __('travel.inquiries.status_change_forbidden')]);
        }

        $inquiry->update([
            'status'       => TravelPackageInquiryStatus::Contacted,
            'contacted_at' => now(),
        ]);

        return back()->with('success', __('travel.inquiries.marked_contacted'));
    }

    // ── Convert to Booking ───────────────────────────────────────────────────

    public function convertToBooking(TravelPackageInquiry $inquiry): RedirectResponse
    {
        $this->authorise($inquiry);

        if (!in_array($inquiry->status, [TravelPackageInquiryStatus::New, TravelPackageInquiryStatus::Contacted])) {
            return back()->withErrors(['status' => __('travel.inquiries.convert_forbidden')]);
        }

        $customer = $inquiry->email ? Customer::where('email', $inquiry->email)->first() : null;
        $customer ??= Customer::where('phone', $inquiry->phone)->first();

        if ($customer) {
            $data = [
                'travel_package_id' => $inquiry->travel_package_id,
                'travelers_count'   => $inquiry->travelers_count ?? 1,
                'customer_mode'     => 'existing',
                'customer_id'       => $customer->id,
            ];
        } elseif ($inquiry->email) {
            $data = [
                'travel_package_id' => $inquiry->travel_package_id,
                'travelers_count'   => $inquiry->travelers_count ?? 1,
                'customer_mode'     => 'new',
                'new_name'          => $inquiry->name,
                'new_phone'         => $inquiry->phone,
                'new_email'         => $inquiry->email,
            ];
        } else {
            return back()->withErrors([
                'email' => __('travel.inquiries.convert_requires_email'),
            ]);
        }

        $booking = $this->bookingCreationService->create($this->agencyId(), $data);

        $inquiry->update([
            'status'                  => TravelPackageInquiryStatus::Converted,
            'converted_to_booking_id' => $booking->id,
        ]);

        return redirect()
            ->route('travel-agency.bookings.show', $booking)
            ->with('success', __('travel.inquiries.converted_success'));
    }

    // ── Close ─────────────────────────────────────────────────────────────────

    public function close(Request $request, TravelPackageInquiry $inquiry): RedirectResponse
    {
        $this->authorise($inquiry);

        if (!in_array($inquiry->status, [TravelPackageInquiryStatus::New, TravelPackageInquiryStatus::Contacted])) {
            return back()->withErrors(['status' => __('travel.inquiries.close_forbidden')]);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $inquiry->update([
            'status'       => TravelPackageInquiryStatus::Closed,
            'close_reason' => $validated['reason'] ?? null,
        ]);

        return back()->with('success', __('travel.inquiries.closed_success'));
    }
}
