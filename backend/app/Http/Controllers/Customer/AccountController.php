<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\Classified\StoreClassifiedListingRequest;
use App\Http\Requests\Customer\Classified\UpdateClassifiedListingRequest;
use App\Http\Resources\Customer\ClassifiedInquiryAsBuyerResource;
use App\Http\Resources\Customer\CustomerClassifiedListingDetailResource;
use App\Http\Resources\Customer\CustomerClassifiedListingListResource;
use App\Http\Resources\Customer\TravelBookingResource;
use App\Http\Resources\Vendor\ClassifiedInquiryResource;
use App\Http\Responses\ApiResponse;
use App\Models\ClassifiedInquiry;
use App\Models\ClassifiedListing;
use App\Models\Country;
use App\Models\Customer;
use App\Services\Customer\AccountDashboardService;
use App\Services\Customer\TravelBookingService;
use App\Services\Shared\ClassifiedListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountDashboardService $dashboardService,
        private readonly ClassifiedListingService $listingService,
        private readonly TravelBookingService $travelService,
    ) {}

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard(Request $request,$country): JsonResponse
    {
        $country = $request->attributes->get("country");
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $data = $this->dashboardService->getDashboard($customer, $country);

        return ApiResponse::success($data);
    }

    // ── My Classified Listings (customer as seller) ───────────────────────────

    public function listingsIndex(Request $request, $country): JsonResponse
    {
        $country = $request->attributes->get("country");
        $request->validate([
            'status' => 'nullable|in:draft,pending_contract,pending_review,active,paused,sold,expired,rejected',
            'page'   => 'nullable|integer|min:1',
        ]);

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $query = $customer->classifiedListings()
            ->with(['images'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest();

        return ApiResponse::paginated($query->paginate(20), CustomerClassifiedListingListResource::class);
    }

    public function listingsShow(Request $request,$country, string $listingNumber): JsonResponse
    {
        $country = $request->attributes->get("country");
        $listing = $this->findOwnedListing($listingNumber);

        $listing->load(['classifiedCategory', 'images', 'attachments', 'inquiries']);

        return ApiResponse::success(new CustomerClassifiedListingDetailResource($listing));
    }

    public function listingsStore(StoreClassifiedListingRequest $request, $country): JsonResponse
    {
        $country = $request->attributes->get("country");
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $listing = $this->listingService->create($customer, $request->validated());

        return ApiResponse::success(new CustomerClassifiedListingDetailResource($listing), __('common.exceptions.account_listing.created'), 201);
    }

    public function listingsUpdate(UpdateClassifiedListingRequest $request, $country, string $listingNumber): JsonResponse
    {
        $country = $request->attributes->get("country");
        $listing = $this->findOwnedListing($listingNumber);

        if (! $this->listingService->canEdit($listing)) {
            return ApiResponse::error(__('common.exceptions.account_listing.edit_not_allowed'), [], 422);
        }

        $listing = $this->listingService->update($listing, $request->validated());

        return ApiResponse::success(new CustomerClassifiedListingDetailResource($listing), __('common.exceptions.account_listing.updated'));
    }

    public function listingsDestroy(Request $request,$country, string $listingNumber): JsonResponse
    {
        $country = $request->attributes->get("country");
        $listing = $this->findOwnedListing($listingNumber);

        if (! in_array($listing->status?->value, ['draft', 'rejected', 'expired'], true)) {
            return ApiResponse::error(__('common.exceptions.account_listing.delete_not_allowed'), [], 422);
        }

        $this->listingService->delete($listing);

        return ApiResponse::success(message: __('common.exceptions.account_listing.deleted'));
    }

    public function listingInquiries(Request $request,$country, string $listingNumber): JsonResponse
    {
        $country = $request->attributes->get("country");
        $listing = $this->findOwnedListing($listingNumber);

        $inquiries = $listing->inquiries()->with('customer')->latest()->paginate(20);

        return ApiResponse::paginated($inquiries, ClassifiedInquiryResource::class);
    }

    // ── My Travel Bookings ────────────────────────────────────────────────────

    public function travelBookingsIndex(Request $request, $country): JsonResponse
    {
        $country = $request->attributes->get("country");
        $request->validate([
            'status' => 'nullable|in:pending_documents,confirmed,cancelled,completed',
            'page'   => 'nullable|integer|min:1',
        ]);

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $paginator = $this->travelService->listForCustomer($customer, $request->only('status'));

        return ApiResponse::paginated($paginator, TravelBookingResource::class);
    }

    public function travelBookingsShow(Request $request,$country, string $id): JsonResponse
    {
        $country = $request->attributes->get("country");
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $booking = $this->travelService->showForCustomer($customer, $id);

        return ApiResponse::success(new TravelBookingResource($booking));
    }

    public function travelBookingsCancel(Request $request, $country, string $id): JsonResponse
    {
        $country = $request->attributes->get("country");
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $booking = $this->travelService->cancel($customer, $id, $request->reason);

        return ApiResponse::success(
            ['status' => $booking->status?->value],
            __('common.exceptions.travel.booking_cancelled')
        );
    }

    public function travelBookingsUploadPassport(Request $request, $country, string $id): JsonResponse
    {
        $country = $request->attributes->get("country");

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $booking = $this->travelService->showForCustomer($customer, $id);

        if ($booking->status !== \App\Enums\TravelBookingStatus::PendingDocuments) {
            return ApiResponse::error(
                __('common.exceptions.travel.passport_upload_not_allowed'),
                [],
                422
            );
        }

        $request->validate([
            'passport_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        if ($booking->passport_file_path) {
            \Illuminate\Support\Facades\Storage::disk('private')->delete($booking->passport_file_path);
        }

        $path = $request->file('passport_file')->store('travel-bookings/passports', 'private');

        $booking->update(['passport_file_path' => $path]);

        return ApiResponse::success(
            ['passport_uploaded' => true],
            __('common.exceptions.travel.passport_uploaded')
        );
    }

    // ── My Classified Inquiries (customer as buyer) ───────────────────────────

    public function inquiriesIndex(Request $request, $country): JsonResponse
    {
        $country = $request->attributes->get("country");
        $request->validate([
            'status' => 'nullable|in:new,replied,closed',
            'page'   => 'nullable|integer|min:1',
        ]);

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $paginator = $customer->classifiedInquiries()
            ->with(['listing:id,listing_number,title_en,title_ar,status,sketch_file_path'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return ApiResponse::paginated($paginator, ClassifiedInquiryAsBuyerResource::class);
    }

    public function inquiriesShow(Request $request,$country, string $id): JsonResponse
    {
        $country = $request->attributes->get("country");
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $inquiry = $customer->classifiedInquiries()
            ->with(['listing:id,listing_number,title_en,title_ar,status,sketch_file_path'])
            ->findOrFail($id);

        return ApiResponse::success(new ClassifiedInquiryAsBuyerResource($inquiry));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function findOwnedListing(string $listingNumber): ClassifiedListing
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        return ClassifiedListing::forCustomers()
            ->where('seller_id', $customer->id)
            ->where('listing_number', $listingNumber)
            ->firstOrFail();
    }
}
