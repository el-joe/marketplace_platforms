<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\FlashSaleSubmissionRequest;
use App\Http\Resources\Vendor\FlashSaleResource;
use App\Http\Resources\Vendor\FlashSaleSubmissionResource;
use App\Http\Responses\ApiResponse;
use App\Models\FlashSale;
use App\Models\FlashSaleSubmission;
use App\Models\FlashSaleVendorInvitition;
use App\Models\Vendor;
use App\Services\Vendor\FlashSaleSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlashSaleController extends Controller
{
    public function __construct(private readonly FlashSaleSubmissionService $submissionService) {}

    private function vendor(): Vendor
    {
        /** @var \App\Models\VendorAdmin $auth */
        $auth = auth('vendor')->user();
        /** @var Vendor $vendor */
        $vendor = $auth->vendor;
        return $vendor;
    }

    private function vendorId(): string
    {
        /** @var \App\Models\VendorAdmin $auth */
        $auth = auth('vendor')->user();
        return (string) $auth->vendor_id;
    }

    public function index(Request $request): JsonResponse
    {
        $vendorId = $this->vendorId();

        $statusMap = [
            'upcoming' => ['draft', 'submission_open', 'submission_closed', 'under_review', 'approved'],
            'active'   => ['live'],
            'ended'    => ['ended', 'cancelled'],
        ];

        $filter = $request->query('status');
        $statuses = is_string($filter) ? ($statusMap[$filter] ?? null) : null;

        $sales = FlashSale::when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->where(function ($q) use ($vendorId) {
                $q->whereNull('eligible_seller_tiers')
                  ->orWhereHas('invititions', fn ($iq) => $iq->where('vendor_id', $vendorId));
            })
            ->orderBy('sale_starts_at')
            ->paginate((int) ($request->query('per_page', 20)));

        $sales->getCollection()->each(function (FlashSale $sale) use ($vendorId) {
            $sale->setRelation(
                'vendorInvitation',
                FlashSaleVendorInvitition::where('flash_sale_id', $sale->id)
                    ->where('vendor_id', $vendorId)
                    ->first()
            );
        });

        return ApiResponse::paginated($sales, FlashSaleResource::class);
    }

    public function show(string $id): JsonResponse
    {
        $vendorId = $this->vendorId();
        $sale     = FlashSale::findOrFail($id);

        $sale->setRelation(
            'vendorInvitation',
            FlashSaleVendorInvitition::where('flash_sale_id', $sale->id)
                ->where('vendor_id', $vendorId)
                ->first()
        );

        $submissions = FlashSaleSubmission::where('flash_sale_id', $sale->id)
            ->where('vendor_id', $vendorId)
            ->get();

        return ApiResponse::success([
            'flash_sale'  => new FlashSaleResource($sale),
            'submissions' => FlashSaleSubmissionResource::collection($submissions)->resolve(),
        ]);
    }

    public function storeSubmission(FlashSaleSubmissionRequest $request, string $id): JsonResponse
    {
        $vendor = $this->vendor();
        $sale   = FlashSale::findOrFail($id);

        $submission = $this->submissionService->submit($vendor, $sale, $request->validated());

        return ApiResponse::success(new FlashSaleSubmissionResource($submission), 'Submission created.', 201);
    }

    public function updateSubmission(FlashSaleSubmissionRequest $request, string $id, string $submissionId): JsonResponse
    {
        $vendor     = $this->vendor();
        $sale       = FlashSale::findOrFail($id);
        $submission = FlashSaleSubmission::where('flash_sale_id', $id)
            ->where('vendor_id', $vendor->id)
            ->findOrFail($submissionId);

        $updated = $this->submissionService->update($sale, $submission, $request->validated());

        return ApiResponse::success(new FlashSaleSubmissionResource($updated));
    }

    public function destroySubmission(string $id, string $submissionId): JsonResponse
    {
        $vendor     = $this->vendor();
        $sale       = FlashSale::findOrFail($id);
        $submission = FlashSaleSubmission::where('flash_sale_id', $id)
            ->where('vendor_id', $vendor->id)
            ->findOrFail($submissionId);

        $this->submissionService->withdraw($sale, $submission);

        return ApiResponse::success(null, 'Submission withdrawn.');
    }
}
