<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\Classified\AcceptClassifiedContractRequest;
use App\Http\Requests\Vendor\Classified\IndexClassifiedListingRequest;
use App\Http\Requests\Vendor\Classified\StoreClassifiedListingRequest;
use App\Http\Requests\Vendor\Classified\UpdateClassifiedListingRequest;
use App\Http\Requests\Vendor\Classified\UpdateInquiryStatusRequest;
use App\Http\Resources\Vendor\ClassifiedCategoryTreeResource;
use App\Http\Resources\Vendor\ClassifiedInquiryResource;
use App\Http\Resources\Vendor\ClassifiedListingDetailResource;
use App\Http\Resources\Vendor\ClassifiedListingListResource;
use App\Http\Responses\ApiResponse;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedInquiry;
use App\Models\ClassifiedListing;
use App\Models\Vendor;
use App\Services\Vendor\ClassifiedInquiryService;
use App\Services\Vendor\ClassifiedListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ClassifiedListingController extends Controller
{
    public function __construct(
        private readonly ClassifiedListingService $listingService,
        private readonly ClassifiedInquiryService $inquiryService,
    ) {}

    public function categories(): JsonResponse
    {
        $categories = ClassifiedCategory::where('is_active', true)
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return ApiResponse::success(ClassifiedCategoryTreeResource::collection($categories));
    }

    public function index(IndexClassifiedListingRequest $request): JsonResponse
    {
        $vendorId = auth('vendor')->user()->vendor_id;

        $query = ClassifiedListing::forVendors()
            ->where('seller_id', $vendorId)
            ->with(['images'])
            ->when($request->status,      fn ($q) => $q->where('status', $request->status))
            ->when($request->category_id, fn ($q) => $q->where('classified_category_id', $request->category_id))
            ->latest();

        return ApiResponse::paginated($query->paginate(20), ClassifiedListingListResource::class);
    }

    public function show(string $id): JsonResponse
    {
        $listing = $this->findOwnedListing($id);

        $listing->load(['classifiedCategory', 'images', 'attachments', 'inquiries']);

        return ApiResponse::success(new ClassifiedListingDetailResource($listing));
    }

    public function store(StoreClassifiedListingRequest $request): JsonResponse
    {
        /** @var \App\Models\VendorAdmin $vendorAdmin */
        $vendorAdmin = auth('vendor')->user();
        $vendor      = Vendor::findOrFail($vendorAdmin->vendor_id);

        $listing = $this->listingService->create($vendor, $request->validated());

        return ApiResponse::success(new ClassifiedListingDetailResource($listing), 'Listing created.', 201);
    }

    public function update(UpdateClassifiedListingRequest $request, string $id): JsonResponse
    {
        $listing = $this->findOwnedListing($id);

        Gate::authorize('update', $listing);

        $listing = $this->listingService->update($listing, $request->validated());

        return ApiResponse::success(new ClassifiedListingDetailResource($listing), 'Listing updated.');
    }

    public function showContract(string $id): JsonResponse
    {
        $listing = $this->findOwnedListing($id);

        Gate::authorize('viewContract', $listing);

        $template = $listing->classifiedCategory?->contractTemplate;

        if (! $template) {
            return ApiResponse::error('No contract template found for this listing.', [], 404);
        }

        return ApiResponse::success([
            'template_id' => $template->id,
            'name'        => $template->name,
            'version'     => $template->version,
            'content_en'  => $template->content_en,
            'content_ar'  => $template->content_ar,
        ]);
    }

    public function acceptContract(AcceptClassifiedContractRequest $request, string $id): JsonResponse
    {
        $listing = $this->findOwnedListing($id);

        Gate::authorize('acceptContract', $listing);

        $listing = $this->listingService->acceptContract(
            $listing,
            $request->signature_data,
        );

        return ApiResponse::success(['status' => $listing->status?->value], 'Contract accepted. Listing submitted for review.');
    }

    public function pause(string $id): JsonResponse
    {
        $listing = $this->findOwnedListing($id);

        Gate::authorize('pause', $listing);

        $listing = $this->listingService->pause($listing);

        return ApiResponse::success(['status' => $listing->status?->value], 'Listing paused.');
    }

    public function resume(string $id): JsonResponse
    {
        $listing = $this->findOwnedListing($id);

        Gate::authorize('resume', $listing);

        $listing = $this->listingService->resume($listing);

        return ApiResponse::success(['status' => $listing->status?->value], 'Listing resumed.');
    }

    public function markSold(string $id): JsonResponse
    {
        $listing = $this->findOwnedListing($id);

        Gate::authorize('markSold', $listing);

        $listing = $this->listingService->markSold($listing);

        return ApiResponse::success(['status' => $listing->status?->value], 'Listing marked as sold.');
    }

    public function destroy(string $id): JsonResponse
    {
        $listing = $this->findOwnedListing($id);

        Gate::authorize('delete', $listing);

        $this->listingService->delete($listing);

        return ApiResponse::success(message: 'Listing deleted.');
    }

    public function inquiries(string $id): JsonResponse
    {
        $listing = $this->findOwnedListing($id);

        Gate::authorize('viewInquiries', $listing);

        $inquiries = $listing->inquiries()->with('customer')->latest()->paginate(20);

        return ApiResponse::paginated($inquiries, ClassifiedInquiryResource::class);
    }

    public function updateInquiryStatus(UpdateInquiryStatusRequest $request, string $inquiryId): JsonResponse
    {
        $inquiry = ClassifiedInquiry::with('listing')->findOrFail($inquiryId);

        Gate::authorize('updateStatus', $inquiry);

        $inquiry = $this->inquiryService->updateStatus($inquiry, $request->status);

        return ApiResponse::success(new ClassifiedInquiryResource($inquiry), 'Inquiry status updated.');
    }

    private function findOwnedListing(string $id): ClassifiedListing
    {
        $vendorId = auth('vendor')->user()->vendor_id;

        return ClassifiedListing::where('id', $id)
            ->where('seller_type', Vendor::class)
            ->where('seller_id', $vendorId)
            ->firstOrFail();
    }
}
