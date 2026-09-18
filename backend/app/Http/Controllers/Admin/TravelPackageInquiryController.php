<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TravelPackageInquiryStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\TravelPackageInquiry;
use App\Services\TravelAgency\BookingCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TravelPackageInquiryController extends Controller
{
    public function __construct(private readonly BookingCreationService $bookingCreationService)
    {
    }

    public function index(Request $request): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('travel.view'), 403);

        $query = TravelPackageInquiry::query()
            ->with(['package.agency'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($agencyId = $request->query('agency_id')) {
            $query->whereHas('package', fn ($q) => $q->where('travel_agency_id', $agencyId));
        }

        $inquiries = $query->paginate(40)->withQueryString();

        return view('admin.travel.inquiries.index', compact('inquiries'));
    }

    // ── Convert to Booking ───────────────────────────────────────────────────
    // Admin-side equivalent of Api\TravelAgencyPortal\PackageInquiryController::convertToBooking,
    // reusing the same BookingCreationService so booking-creation logic is not duplicated.
    public function convertToBooking(TravelPackageInquiry $inquiry): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('travel.approve'), 403);

        if (! in_array($inquiry->status, [TravelPackageInquiryStatus::New, TravelPackageInquiryStatus::Contacted])) {
            return back()->with('error', __('admin.travel.inquiry_cannot_be_converted'));
        }

        $inquiry->loadMissing('package');

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
            return back()->with('error', __('admin.travel.inquiry_no_email_on_file'));
        }

        $booking = $this->bookingCreationService->create($inquiry->package->travel_agency_id, $data);

        $inquiry->update([
            'status'                  => TravelPackageInquiryStatus::Converted,
            'converted_to_booking_id' => $booking->id,
        ]);

        return back()->with('success', __('admin.travel.inquiry_converted_success'));
    }
}
