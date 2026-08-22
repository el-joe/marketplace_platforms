<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\AdCampaign\CreateAdCampaignRequest;
use App\Http\Requests\Vendor\AdCampaign\PerformanceRequest;
use App\Http\Requests\Vendor\AdCampaign\UpdateAdCampaignRequest;
use App\Http\Resources\Vendor\AdCampaignDetailResource;
use App\Http\Resources\Vendor\AdCampaignListResource;
use App\Http\Resources\Vendor\AdPerformanceResource;
use App\Http\Resources\Vendor\QualityScoreResource;
use App\Http\Responses\ApiResponse;
use App\Models\AdCampaign;
use App\Services\Vendor\AdCampaignService;
use App\Services\Vendor\AdPerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdCampaignController extends Controller
{
    public function __construct(
        private readonly AdCampaignService $campaignService,
        private readonly AdPerformanceService $performanceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AdCampaign::class);

        $vendorId = auth('vendor')->user()->vendor_id;

        $query = AdCampaign::where('vendor_id', $vendorId)
            ->with(['todayStats' => fn ($q) => $q->whereDate('date', today())])
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest();

        return ApiResponse::paginated($query->paginate(20), AdCampaignListResource::class);
    }

    public function store(CreateAdCampaignRequest $request): JsonResponse
    {
        $this->authorize('create', AdCampaign::class);

        $vendorAdmin = auth('vendor')->user();

        if (!$vendorAdmin->can('campaigns.create')) {
            return ApiResponse::error('You do not have permission to create campaigns.', [], 403);
        }

        $campaign = $this->campaignService->create($vendorAdmin, $request->validated());

        return ApiResponse::success(new AdCampaignDetailResource($campaign), 'تم إنشاء الحملة وهي بانتظار المراجعة.', 201);
    }

    public function show(string $id): JsonResponse
    {
        $campaign = $this->resolveCampaign($id);
        $this->authorize('view', $campaign);

        $campaign->load(['keywords', 'categoryTargets.category', 'products.vendorListing', 'qualityScore']);

        return ApiResponse::success(new AdCampaignDetailResource($campaign));
    }

    public function update(UpdateAdCampaignRequest $request, string $id): JsonResponse
    {
        $campaign = $this->resolveCampaign($id);
        $this->authorize('update', $campaign);

        $vendorAdmin = auth('vendor')->user();
        $campaign = $this->campaignService->update($campaign, $vendorAdmin, $request->validated());

        return ApiResponse::success(new AdCampaignDetailResource($campaign));
    }

    public function destroy(string $id): JsonResponse
    {
        $campaign = $this->resolveCampaign($id);
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return ApiResponse::success(message: 'تم حذف الحملة.');
    }

    public function pause(string $id): JsonResponse
    {
        $campaign = $this->resolveCampaign($id);
        $this->authorize('pause', $campaign);

        $campaign->update(['status' => 'paused']);

        return ApiResponse::success(message: 'تم إيقاف الحملة مؤقتاً.');
    }

    public function resume(string $id): JsonResponse
    {
        $campaign = $this->resolveCampaign($id);
        $this->authorize('resume', $campaign);

        $campaign->update(['status' => 'active']);

        return ApiResponse::success(message: 'تم استئناف الحملة.');
    }

    public function performance(PerformanceRequest $request, string $id): JsonResponse
    {
        $campaign = $this->resolveCampaign($id);
        $this->authorize('view', $campaign);

        $data = $this->performanceService->getStats(
            $campaign,
            $request->input('date_from'),
            $request->input('date_to'),
            (bool) $request->input('per_listing', false),
        );

        return ApiResponse::success([
            'date_from' => $data['date_from'],
            'date_to'   => $data['date_to'],
            'totals'    => $data['totals'],
            'rows'      => AdPerformanceResource::collection($data['rows'])->resolve(),
        ]);
    }

    public function qualityScore(string $id): JsonResponse
    {
        $campaign = $this->resolveCampaign($id);
        $this->authorize('view', $campaign);

        $score = $this->performanceService->getQualityScore($campaign);

        if (!$score) {
            return ApiResponse::success(null, 'لا يوجد تقييم جودة بعد.');
        }

        return ApiResponse::success(new QualityScoreResource($score));
    }

    private function resolveCampaign(string $id): AdCampaign
    {
        return AdCampaign::where('vendor_id', auth('vendor')->user()->vendor_id)
            ->findOrFail($id);
    }
}
