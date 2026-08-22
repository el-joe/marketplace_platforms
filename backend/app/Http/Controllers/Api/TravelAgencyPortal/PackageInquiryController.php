<?php

namespace App\Http\Controllers\Api\TravelAgencyPortal;

use App\Enums\TravelPackageInquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TravelAgencyPortal\TravelPackageInquiryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\TravelPackageInquiry;
use App\Services\TravelAgency\BookingCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PackageInquiryController extends Controller
{
    public function __construct(private readonly BookingCreationService $bookingCreationService)
    {
    }

    private function agencyId(): string
    {
        return Auth::guard('travel_agencies')->id();
    }

    private function authorise(TravelPackageInquiry $inquiry): void
    {
        abort_if($inquiry->package->travel_agency_id !== $this->agencyId(), 403);
    }

    // ── Index — all inquiries across the agency's packages ───────────────────

    public function index(Request $request): JsonResponse
    {
        $query = TravelPackageInquiry::query()
            ->whereHas('package', fn ($q) => $q->where('travel_agency_id', $this->agencyId()))
            ->with('package')
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', TravelPackageInquiryStatus::from($status));
        }

        if ($packageId = $request->query('package_id')) {
            $query->where('travel_package_id', $packageId);
        }

        $paginator = $query->paginate($request->integer('per_page', 30));

        return ApiResponse::paginated($paginator, TravelPackageInquiryResource::class);
    }

    // ── Mark Contacted ───────────────────────────────────────────────────────

    public function markContacted(TravelPackageInquiry $inquiry): JsonResponse
    {
        $this->authorise($inquiry);

        if ($inquiry->status !== TravelPackageInquiryStatus::New) {
            return ApiResponse::error('This inquiry status cannot be changed.', [], 422);
        }

        $inquiry->update([
            'status' => TravelPackageInquiryStatus::Contacted,
            'contacted_at' => now(),
        ]);

        return ApiResponse::success(new TravelPackageInquiryResource($inquiry), 'Marked as contacted.');
    }

    // ── Convert to Booking ───────────────────────────────────────────────────

    public function convertToBooking(TravelPackageInquiry $inquiry): JsonResponse
    {
        $this->authorise($inquiry);

        if (! in_array($inquiry->status, [TravelPackageInquiryStatus::New, TravelPackageInquiryStatus::Contacted])) {
            return ApiResponse::error('This inquiry cannot be converted to a booking.', [], 422);
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
            return ApiResponse::error('This inquiry has no email on file, so a new customer cannot be created.', [], 422);
        }

        $booking = $this->bookingCreationService->create($this->agencyId(), $data);

        $inquiry->update([
            'status'                  => TravelPackageInquiryStatus::Converted,
            'converted_to_booking_id' => $booking->id,
        ]);

        return ApiResponse::success(new TravelPackageInquiryResource($inquiry), 'Inquiry converted to booking.');
    }

    // ── Close ─────────────────────────────────────────────────────────────────

    public function close(Request $request, TravelPackageInquiry $inquiry): JsonResponse
    {
        $this->authorise($inquiry);

        if (! in_array($inquiry->status, [TravelPackageInquiryStatus::New, TravelPackageInquiryStatus::Contacted])) {
            return ApiResponse::error('This inquiry cannot be closed.', [], 422);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $inquiry->update([
            'status'       => TravelPackageInquiryStatus::Closed,
            'close_reason' => $validated['reason'] ?? null,
        ]);

        return ApiResponse::success(new TravelPackageInquiryResource($inquiry), 'Inquiry closed.');
    }
}
