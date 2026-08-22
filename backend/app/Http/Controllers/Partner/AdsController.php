<?php

namespace App\Http\Controllers\Partner;

use App\Enums\AdCampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\Admin;
use App\Models\Vendor;
use App\Enums\VendorListingStatus;
use App\Models\Category;
use App\Models\VendorListing;
use App\Notifications\Admin\AdCampaignSubmittedForReview;
use App\Traits\HasDataTable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AdsController extends Controller
{
    use HasDataTable;

    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    public function index(): View
    {
        return view('partner.ads.index', ['vendorId' => $this->vendorId()]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $columns = [
            ['searchable_columns' => ['ad_campaigns.name']],
            ['orderable_column' => 'ad_campaigns.type'],
            ['orderable_column' => 'ad_campaigns.status'],
            [],
            ['orderable_column' => 'ad_campaigns.bid'],
            ['orderable_column' => 'ad_campaigns.created_at'],
            [],
        ];

        $query = AdCampaign::where('vendor_id', $this->vendorId())->with('country:id,currency_code');

        $query = $this->applyFilters($query, $request, [
            'filter_status' => fn($q, $v) => $q->where('status', $v),
        ]);

        return $this->dataTableResponse($request, $query, $columns, fn(AdCampaign $c) => [
            'name'    => '<a href="' . route('partner.ads.show', $c->id) . '" class="font-medium text-primary-600 hover:underline">' . e($c->name) . '</a>',
            'type'    => strtoupper($c->type->value),
            'status'  => $this->statusBadge($c->status->value),
            'budget'  => number_format($c->budget_total / 100, 2) . ' / ' . number_format(($c->budget_daily ?? 0) / 100, 2) . ' ' . ($c->country?->currency_code ?? ''),
            'bid'     => number_format($c->bid / 100, 2) . ' ' . ($c->country?->currency_code ?? ''),
            'date'    => $c->created_at->format('d M Y'),
            'actions' => '<a href="' . route('partner.ads.show', $c->id) . '" class="inline-flex items-center px-3 py-1.5 text-xs font-medium border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">View</a>',
        ]);
    }

    public function show(string $id): View
    {
        $campaign = AdCampaign::where('vendor_id', $this->vendorId())->findOrFail($id);

        return view('partner.ads.show', compact('campaign'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'type'                    => 'required|in:cpc,cpm',
            'targeting_type'          => 'required|in:auto,keyword,category,mixed',
            'keywords'                => 'required_if:targeting_type,keyword,mixed|array',
            'keywords.*.keyword'      => 'required_with:keywords|string|max:100',
            'keywords.*.match_type'   => 'required_with:keywords|in:broad,phrase,exact',
            'category_ids'            => 'required_if:targeting_type,category,mixed|array',
            'category_ids.*'          => 'uuid|exists:categories,id',
            'vendor_listing_ids'      => 'required|array|min:1',
            'vendor_listing_ids.*'    => 'uuid',
            'budget_total'            => 'required|numeric|min:1',
            'budget_daily'            => 'nullable|numeric|min:1',
            'bid'                     => 'required|numeric|min:0.01',
            'starts_at'               => 'required|date|after_or_equal:today',
            'ends_at'                 => 'nullable|date|after:starts_at',
        ]);

        $vendorId = $this->vendorId();

        $listings = VendorListing::where('vendor_id', $vendorId)
            ->whereIn('id', $data['vendor_listing_ids'])
            ->get(['id', 'vendor_id', 'product_variant_id'])
            ->keyBy('id');

        if ($listings->count() !== count($data['vendor_listing_ids'])) {
            return response()->json([
                'success' => false,
                'message' => __('partner.ads.messages.listings_not_owned'),
            ], 422);
        }

        $countryId = Vendor::where('id', $vendorId)->value('country_id');

        $campaign = null;

        DB::transaction(function () use ($data, $vendorId, $countryId, $listings, &$campaign) {
            $campaign = AdCampaign::create([
                'vendor_id'      => $vendorId,
                'country_id'     => $countryId,
                'name'           => $data['name'],
                'type'           => $data['type'],
                'targeting_type' => $data['targeting_type'],
                'status'         => AdCampaignStatus::PendingReview->value,
                'budget_total'   => (int) round($data['budget_total'] * 100),
                'budget_daily'   => isset($data['budget_daily']) ? (int) round($data['budget_daily'] * 100) : null,
                'bid'            => (int) round($data['bid'] * 100),
                'starts_at'      => $data['starts_at'],
                'ends_at'        => $data['ends_at'] ?? null,
                'quality_score'  => 5.00,
            ]);

            foreach ($data['vendor_listing_ids'] as $listingId) {
                $listing = $listings->get($listingId);
                $campaign->products()->create([
                    'vendor_listing_id'  => $listingId,
                    'vendor_id'          => $listing->vendor_id,
                    'product_variant_id' => $listing->product_variant_id,
                    'is_active'          => true,
                ]);
            }

            foreach ($data['keywords'] ?? [] as $kw) {
                $campaign->keywords()->create([
                    'keyword'     => $kw['keyword'],
                    'match_type'  => $kw['match_type'],
                    'is_active'   => true,
                    'is_negative' => false,
                ]);
            }

            foreach ($data['category_ids'] ?? [] as $catId) {
                $campaign->categoryTargets()->create(['category_id' => $catId, 'is_active' => true]);
            }
        });

        Notification::send(
            Admin::permission('ad_campaigns.manage')->get(),
            new AdCampaignSubmittedForReview($campaign),
        );

        return response()->json([
            'success'  => true,
            'message'  => 'تم إرسال حملتك للمراجعة بنجاح.',
            'redirect' => route('partner.ads.show', $campaign->id),
        ], 201);
    }

    public function categories(): JsonResponse
    {
        // Scoped to categories that have at least one active listing for this vendor,
        // keeping the targeting picker relevant to what they actually sell.
        $vendorId = $this->vendorId();

        $categories = Category::where('is_active', true)
            ->whereHas('vendorListings', fn($q) => $q->where('vendor_id', $vendorId)->where('status', VendorListingStatus::Active->value))
            ->orderBy('name_ar')
            ->get(['id', 'name_ar', 'name_en']);

        return response()->json(['data' => $categories]);
    }

    public function performance(Request $request, string $id): JsonResponse
    {
        $campaign = AdCampaign::where('vendor_id', $this->vendorId())->findOrFail($id);

        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $stats = $campaign->dailyStats()
            ->whereBetween('date', [$request->from, $request->to])
            ->orderBy('date')
            ->get(['date', 'impressions', 'clicks', 'conversions', 'spend', 'revenue_attributed']);

        return response()->json([
            'labels'      => $stats->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d M')),
            'impressions' => $stats->pluck('impressions'),
            'clicks'      => $stats->pluck('clicks'),
            'totals'      => [
                'impressions' => $stats->sum('impressions'),
                'clicks'      => $stats->sum('clicks'),
                'conversions' => $stats->sum('conversions'),
                'spend'       => number_format($stats->sum('spend') / 100, 2),
                'revenue'     => number_format($stats->sum('revenue_attributed') / 100, 2),
            ],
        ]);
    }

    public function qualityScore(string $id): JsonResponse
    {
        $campaign = AdCampaign::where('vendor_id', $this->vendorId())->findOrFail($id);
        $qs = $campaign->qualityScore;

        if (!$qs) {
            return response()->json(['success' => true, 'has_data' => false]);
        }

        return response()->json([
            'success'   => true,
            'has_data'  => true,
            'overall'   => (float) $qs->score,
            'ctr'       => (float) $qs->ctr_score,
            'relevance' => (float) $qs->relevance_score,
            'landing'   => (float) $qs->landing_score,
            'seller'    => (float) $qs->seller_score,
        ]);
    }

    public function pause(string $id): JsonResponse
    {
        $campaign = AdCampaign::where('vendor_id', $this->vendorId())->findOrFail($id);

        if ($campaign->status !== AdCampaignStatus::Active) {
            return response()->json(['success' => false, 'message' => 'الحملة غير نشطة حالياً.'], 422);
        }

        $campaign->update(['status' => AdCampaignStatus::Paused->value]);

        return response()->json(['success' => true, 'message' => 'تم إيقاف الحملة مؤقتاً.']);
    }

    public function resume(string $id): JsonResponse
    {
        $campaign = AdCampaign::where('vendor_id', $this->vendorId())->findOrFail($id);

        if ($campaign->status !== AdCampaignStatus::Paused) {
            return response()->json(['success' => false, 'message' => 'الحملة ليست متوقفة.'], 422);
        }

        $campaign->update(['status' => AdCampaignStatus::Active->value]);

        return response()->json(['success' => true, 'message' => 'تم استئناف الحملة.']);
    }

    private function statusBadge(string $status): string
    {
        $classes = match ($status) {
            'active'           => 'bg-green-100 text-green-700',
            'paused'           => 'bg-yellow-100 text-yellow-700',
            'pending_review'   => 'bg-blue-100 text-blue-700',
            'budget_exhausted' => 'bg-orange-100 text-orange-700',
            'ended'            => 'bg-gray-100 text-gray-500',
            'rejected'         => 'bg-red-100 text-red-600',
            default            => 'bg-gray-100 text-gray-600',
        };

        return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' . $classes . '">'
            . ucfirst(str_replace('_', ' ', $status)) . '</span>';
    }
}
