<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\DisputeEvidenceRequest;
use App\Http\Requests\Vendor\DisputeMessageRequest;
use App\Http\Resources\Vendor\DisputeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Dispute;
use App\Services\Vendor\DisputeService;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    public function __construct(private readonly DisputeService $disputeService) {}

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $vendorId = auth('vendor')->user()->vendor_id;

        // 'status' may be a single value or a comma-separated list (e.g. an
        // "open" tab grouping open,seller_responded,under_review,escalated).
        $disputes = Dispute::where('vendor_id', $vendorId)
            ->when($request->query('status'), fn ($q, $v) => $q->whereIn('status', explode(',', $v)))
            ->latest()
            ->paginate((int) $request->query('per_page', 20));

        return ApiResponse::paginated($disputes, DisputeResource::class);
    }

    public function show(string $disputeNumber): \Illuminate\Http\JsonResponse
    {
        $dispute = $this->resolveDispute($disputeNumber);

        $dispute->load([
            'subOrder:id,sub_order_number',
            'messages' => fn ($q) => $q->where('is_internal_note', false)->oldest(),
            'evidence',
        ]);

        return ApiResponse::success(new DisputeResource($dispute));
    }

    public function addMessage(DisputeMessageRequest $request, string $disputeNumber): \Illuminate\Http\JsonResponse
    {
        $dispute = $this->resolveDispute($disputeNumber);
        $admin   = auth('vendor')->user();

        $message = $this->disputeService->addMessage(
            $dispute,
            $admin,
            $request->message,
            $request->file('attachment')
        );

        return ApiResponse::success(
            ['id' => $message->id, 'created_at' => $message->created_at->toIso8601String()],
            'Message sent.',
            201
        );
    }

    public function addEvidence(DisputeEvidenceRequest $request, string $disputeNumber): \Illuminate\Http\JsonResponse
    {
        $dispute = $this->resolveDispute($disputeNumber);
        $admin   = auth('vendor')->user();

        $this->disputeService->addEvidence(
            $dispute,
            $admin,
            $request->file('file'),
            $request->description
        );

        return ApiResponse::success(null, 'Evidence uploaded.', 201);
    }

    private function resolveDispute(string $disputeNumber): Dispute
    {
        $vendorId = auth('vendor')->user()->vendor_id;

        return Dispute::where('dispute_number', $disputeNumber)
            ->where('vendor_id', $vendorId)
            ->firstOrFail();
    }
}
