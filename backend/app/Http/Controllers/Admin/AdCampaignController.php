<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdCampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdCampaignProduct;
use App\Models\AdminListing;
use App\Models\VendorListing;
use App\Notifications\Vendor\AdCampaignApproved;
use App\Notifications\Vendor\AdCampaignRejected;
use Illuminate\Support\Facades\Notification;
use App\Models\AdDailyStat;
use App\Models\AdFraudPattern;
use App\Models\Country;
use App\Models\Vendor;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdCampaignController extends Controller
{
    use HasDataTable;
    use HasExport;

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): \Illuminate\View\View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

        if ($request->filled('export')) {
            return $this->exportCampaigns($request);
        }

        $today = today();

        $stats = [
            'pending' => AdCampaign::where('status', AdCampaignStatus::PendingReview->value)->count(),
            'active' => AdCampaign::where('status', AdCampaignStatus::Active->value)->count(),
            'paused' => AdCampaign::where('status', AdCampaignStatus::Paused->value)->count(),
            'spend_today' => (int) AdDailyStat::whereDate('date', $today)->sum('spend'),
        ];

        $countries = Country::orderBy('name_en')->get(['id', 'name_en', 'flag_emoji']);

        return view('admin.ad-campaigns.index', compact('stats', 'countries'));
    }

    // ─── DataTable ────────────────────────────────────────────────────────────

    /**
     * Shared base query for the datatable and export, with request-driven filters applied.
     * Search matches the campaign name and the joined vendor's store name.
     */
    private function buildCampaignsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = AdCampaign::query()
            ->select('ad_campaigns.*')
            ->with(['vendor', 'country'])
            ->join('vendors', 'vendors.id', '=', 'ad_campaigns.vendor_id');

        return $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('ad_campaigns.status', $v),
            'type' => fn($q, $v) => $q->where('ad_campaigns.type', $v),
            'vendor_id' => fn($q, $v) => $q->where('ad_campaigns.vendor_id', $v),
            'country_id' => fn($q, $v) => $q->where('ad_campaigns.country_id', $v),
            'date_from' => fn($q, $v) => $q->whereDate('ad_campaigns.starts_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('ad_campaigns.ends_at', '<=', $v),
            'search' => fn($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('ad_campaigns.name', 'like', "%{$v}%")
                    ->orWhere('vendors.store_name', 'like', "%{$v}%");
            }),
        ]);
    }

    /**
     * Note: `ad_campaigns` has no `currency` column; budgets are stored as bigint minor-unit
     * amounts in the campaign's country currency. The country's `currency_code` is used as the
     * "Currency" column, and amounts are never summed across rows/currencies.
     */
    private function exportCampaigns(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $campaigns = $this->buildCampaignsQuery($request)->orderByDesc('ad_campaigns.starts_at')->get();

        $headers = ['Campaign', 'Vendor', 'Status', 'Budget', 'Currency', 'Start', 'End'];

        $rows = $campaigns->map(fn(AdCampaign $row) => [
            $row->name,
            $row->vendor?->store_name,
            $row->status?->value,
            number_format($row->budget_total, 2),
            $row->country?->currency_code,
            optional($row->starts_at)->format('d M Y H:i'),
            $row->ends_at ? Carbon::parse($row->ends_at)->format('d M Y H:i') : null,
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('ad-campaigns', $headers, $rows),
            'csv' => $this->exportCsv('ad-campaigns', $headers, $rows),
            'word' => $this->exportWord('ad-campaigns', 'Ad Campaigns', $rows),
            default => abort(400, __('admin.invalid_export_format')),
        };
    }

    public function datatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

        $query = $this->buildCampaignsQuery($request);

        $columns = [
            ['searchable_columns' => ['vendors.store_name'], 'orderable_column' => 'vendors.store_name'],
            ['searchable_columns' => ['ad_campaigns.name'], 'orderable_column' => 'ad_campaigns.name'],
            ['searchable_columns' => [], 'orderable_column' => 'ad_campaigns.type'],
            ['searchable_columns' => [], 'orderable_column' => 'ad_campaigns.status'],
            ['searchable_columns' => [], 'orderable_column' => 'ad_campaigns.budget_total'],
            ['searchable_columns' => [], 'orderable_column' => 'ad_campaigns.budget_spent_total'],
            ['searchable_columns' => [], 'orderable_column' => null], // utilization
            ['searchable_columns' => [], 'orderable_column' => 'ad_campaigns.quality_score'],
            ['searchable_columns' => [], 'orderable_column' => 'ad_campaigns.starts_at'],
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        $statusColors = [
            'pending_review' => 'warning',
            'active' => 'success',
            'paused' => 'gray',
            'rejected' => 'danger',
            'ended' => 'gray',
            'draft' => 'gray',
        ];

        $typeColors = ['cpc' => 'primary', 'cpm' => 'info'];

        $canEdit = $admin->hasPermissionTo('ad_campaigns.edit');

        return $this->dataTableResponse($request, $query, $columns, function (AdCampaign $row) use ($statusColors, $typeColors, $canEdit) {
            $budgetTotal = $row->budget_total;
            $budgetSpent = $row->budget_spent_total;
            $utilization = $row->budget_total > 0
                ? min(100, round($row->budget_spent_total / $row->budget_total * 100, 1))
                : 0;

            $barColor = $utilization >= 90 ? 'bg-red-500' : ($utilization >= 70 ? 'bg-yellow-500' : 'bg-green-500');
            $progressBar = <<<HTML
                <div class="w-24">
                    <div class="flex justify-between text-xs text-gray-500 mb-0.5">
                        <span>{$utilization}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                        <div class="{$barColor} h-1.5 rounded-full" style="width:{$utilization}%"></div>
                    </div>
                </div>
                HTML;

            $qScore = (float) $row->quality_score;
            $qColor = $qScore >= 7 ? 'success' : ($qScore >= 4 ? 'warning' : 'danger');
            $qualityBadge = "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-{$qColor}-100 text-{$qColor}-700\">{$qScore}</span>";

            $statusColor = $statusColors[$row->status->value] ?? 'gray';
            $statusLabel = $row->status->label();
            $statusBadge = "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{$statusColor}-100 text-{$statusColor}-700\">{$statusLabel}</span>";

            $typeColor = $typeColors[$row->type->value] ?? 'gray';
            $typeLabel = strtoupper($row->type->value);
            $typeBadge = "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-{$typeColor}-100 text-{$typeColor}-700\">{$typeLabel}</span>";

            $dateRange = Carbon::parse($row->starts_at)->format('d M') . ' – ' . ($row->ends_at ? Carbon::parse($row->ends_at)->format('d M Y') : '∞');

            return [
                'vendor' => e($row->vendor?->store_name ?? '—'),
                'name' => e($row->name),
                'type' => $typeBadge,
                'status' => $statusBadge,
                'budget' => '$' . number_format($budgetTotal, 2),
                'spend' => '$' . number_format($budgetSpent, 2),
                'utilization' => $progressBar,
                'quality' => $qualityBadge,
                'date_range' => $dateRange,
                'actions' => $this->buildCampaignRowActions($row, $canEdit),
                'DT_RowData' => ['id' => $row->id, 'status' => $row->status?->value],
            ];
        });
    }

    private function buildCampaignRowActions(AdCampaign $campaign, bool $canEdit): string
    {
        $showUrl = route('admin.ad-campaigns.show', $campaign->id);
        $approveUrl = route('admin.ad-campaigns.approve', $campaign->id);
        $rejectUrl = route('admin.ad-campaigns.reject', $campaign->id);
        $pauseUrl = route('admin.ad-campaigns.pause', $campaign->id);
        $resumeUrl = route('admin.ad-campaigns.resume', $campaign->id);

        $html = '<div class="flex items-center gap-1">';
        $html .= "<a href=\"{$showUrl}\" class=\"btn btn-xs btn-secondary\">" . __('admin.ad_campaigns.view_action') . "</a>";

        if ($canEdit) {
            if ($campaign->status?->value === AdCampaignStatus::PendingReview->value) {
                $html .= "<button type=\"button\" class=\"btn btn-xs btn-success js-approve-btn\" data-url=\"{$approveUrl}\" data-name=\"" . e($campaign->name) . "\">" . __('admin.ad_campaigns.approve_action') . "</button>";
                $html .= "<button type=\"button\" class=\"btn btn-xs btn-danger js-reject-btn\" data-url=\"{$rejectUrl}\" data-name=\"" . e($campaign->name) . "\">" . __('admin.ad_campaigns.reject_action') . "</button>";
            } elseif ($campaign->status?->value === AdCampaignStatus::Active->value) {
                $html .= "<button type=\"button\" class=\"btn btn-xs btn-warning js-pause-btn\" data-url=\"{$pauseUrl}\" data-name=\"" . e($campaign->name) . "\">" . __('admin.ad_campaigns.pause') . "</button>";
            } elseif ($campaign->status?->value === AdCampaignStatus::Paused->value) {
                $html .= "<button type=\"button\" class=\"btn btn-xs btn-success js-resume-btn\" data-url=\"{$resumeUrl}\" data-name=\"" . e($campaign->name) . "\">" . __('admin.ad_campaigns.resume') . "</button>";
            }
        }
        $html .= '</div>';
        return $html;
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(AdCampaign $campaign): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

        $campaign->load(['vendor', 'country', 'approvedByAdmin', 'products.vendorListing', 'products.adminListing', 'keywords', 'categoryTargets.category', 'fraudPatterns']);

        // Last 7-day performance
        $perf7 = $campaign->dailyStats()
            ->orderBy('date', 'desc')
            ->take(7)
            ->get();

        $perfSummary = [
            'impressions' => $perf7->sum('impressions'),
            'clicks' => $perf7->sum('clicks'),
            'conversions' => $perf7->sum('conversions'),
            'spend' => $perf7->sum('spend'),
            'revenue_attributed' => $perf7->sum('revenue_attributed'),
            'ctr' => $perf7->avg('ctr'),
            'acos' => $perf7->avg('acos'),
        ];

        // Quality score breakdown (most recent)
        $qualityScore = $campaign->qualityScore;

        // Performance chart data (last 30 days)
        $chartStats = $campaign->dailyStats()
            ->orderBy('date')
            ->take(30)
            ->get(['date', 'impressions', 'clicks', 'spend']);

        $chartLabels = $chartStats->pluck('date')->map(fn($d) => Carbon::parse($d)->format('d M'))->values();
        $chartImpressions = $chartStats->pluck('impressions')->values();
        $chartClicks = $chartStats->pluck('clicks')->values();

        // All daily stats for table
        $dailyStats = $campaign->dailyStats()->orderBy('date', 'desc')->take(30)->get();

        $currency = $campaign->country?->currency_code ?? '';

        return view('admin.ad-campaigns.show', compact(
            'campaign',
            'perfSummary',
            'qualityScore',
            'chartLabels',
            'chartImpressions',
            'chartClicks',
            'dailyStats',
            'currency'
        ));
    }

    // ─── Products ─────────────────────────────────────────────────────────────

    public function productsDatatable(Request $request, AdCampaign $campaign): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

        $query = AdCampaignProduct::query()
            ->with([
                'vendorListing.productVariant.product',
                'adminListing.productVariant.product',
            ])
            ->where('ad_campaign_id', $campaign->id);

        $columns = [
            [], // listing type
            [], // product name
            [], // variant
            [], // price
            [], // active
            [], // actions
        ];

        return $this->dataTableResponse($request, $query, $columns, function (AdCampaignProduct $p) {
            $isAdmin = (bool) $p->admin_listing_id;
            $listing = $p->listing;

            $typeBadge = $isAdmin
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">' . __('admin.ad_campaigns.admin_listing_badge') . '</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-primary-100 text-primary-700">' . __('admin.ad_campaigns.vendor_listing_badge') . '</span>';

            return [
                'type' => $typeBadge,
                'product' => e($listing?->productVariant?->product?->name_en ?? '—'),
                'variant' => e($listing?->productVariant?->variant_name ?? ($p->product_variant_id ?? '—')),
                'price' => $listing ? '$' . number_format($listing->price, 2) : '—',
                'active' => $p->is_active
                    ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-700">' . __('admin.ad_campaigns.active') . '</span>'
                    : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">' . __('admin.ad_campaigns.inactive') . '</span>',
                'actions' => '<button type="button" class="btn btn-xs btn-danger js-remove-campaign-product" data-id="' . $p->id . '">' . __('common.remove') . '</button>',
                'DT_RowData' => ['id' => $p->id],
            ];
        });
    }

    public function storeProduct(Request $request, AdCampaign $campaign): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        $validated = $request->validate([
            'listing_type' => ['required', 'in:vendor,admin'],
            'vendor_listing_id' => ['required_if:listing_type,vendor', 'nullable', 'exists:vendor_listings,id'],
            'admin_listing_id' => ['required_if:listing_type,admin', 'nullable', 'exists:admin_listings,id'],
        ]);

        $isAdmin = $validated['listing_type'] === 'admin';

        $exists = AdCampaignProduct::where('ad_campaign_id', $campaign->id)
            ->when(
                $isAdmin,
                fn($q) => $q->where('admin_listing_id', $validated['admin_listing_id']),
                fn($q) => $q->where('vendor_listing_id', $validated['vendor_listing_id'])
            )
            ->exists();

        if ($exists) {
            return response()->json(['message' => __('admin.ad_campaigns.listing_already_added')], 422);
        }

        $vendorListing = $isAdmin ? null : VendorListing::find($validated['vendor_listing_id']);

        $product = AdCampaignProduct::create([
            'ad_campaign_id' => $campaign->id,
            'vendor_id' => $isAdmin ? null : $vendorListing?->vendor_id,
            'vendor_listing_id' => $isAdmin ? null : $validated['vendor_listing_id'],
            'admin_listing_id' => $isAdmin ? $validated['admin_listing_id'] : null,
            'is_active' => true,
        ]);

        return response()->json(['message' => __('admin.ad_campaigns.product_added'), 'id' => $product->id]);
    }

    public function destroyProduct(AdCampaign $campaign, AdCampaignProduct $product): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        abort_if($product->ad_campaign_id !== $campaign->id, 404);

        $product->delete();

        return response()->json(['message' => __('admin.ad_campaigns.product_removed')]);
    }

    public function searchVendorListings(Request $request, AdCampaign $campaign): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

        $q = $request->input('q', '');

        $listings = VendorListing::where('vendor_id', $campaign->vendor_id)
            ->where('status', 'active')
            ->when($q !== '', fn($query) => $query->whereHas(
                'productVariant.product',
                fn($p) => $p->where('name_en', 'like', "%{$q}%")
            ))
            ->with('productVariant.product')
            ->limit(20)
            ->get()
            ->map(fn(VendorListing $l) => [
                'id' => $l->id,
                'text' => $l->productVariant?->product?->name_en ?? 'Unknown',
                'price' => $l->price,
                'currency' => $l->currency,
            ]);

        return response()->json(['results' => $listings]);
    }

    public function searchAdminListings(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

        $q = $request->input('q', '');

        $listings = AdminListing::active()
            ->when($q !== '', fn($query) => $query->where(
                fn($w) => $w->where('platform_sku', 'like', "%{$q}%")
                    ->orWhereHas('productVariant.product', fn($p) => $p->where('name_en', 'like', "%{$q}%"))
            ))
            ->with('productVariant.product')
            ->limit(20)
            ->get()
            ->map(fn(AdminListing $l) => [
                'id' => $l->id,
                'text' => ($l->productVariant?->product?->name_en ?? 'Unknown') . ' (' . $l->platform_sku . ')',
                'price' => $l->price,
                'currency' => $l->currency,
            ]);

        return response()->json(['results' => $listings]);
    }

    // ─── Approve ──────────────────────────────────────────────────────────────

    public function approve(AdCampaign $campaign): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        if (!in_array($campaign->status?->value, [AdCampaignStatus::PendingReview->value, AdCampaignStatus::Paused->value])) {
            return response()->json(['message' => __('admin.ad_campaigns.not_pending_review')], 422);
        }

        $campaign->update([
            'status' => AdCampaignStatus::Active->value,
            'approved_by_admin_id' => $admin->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        Notification::send($campaign->vendor->vendorAdmins, new AdCampaignApproved($campaign));

        return response()->json(['message' => __('admin.ad_campaigns.approved_active')]);
    }

    // ─── Reject ───────────────────────────────────────────────────────────────

    public function reject(Request $request, AdCampaign $campaign): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $campaign->update([
            'status' => AdCampaignStatus::Rejected->value,
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        Notification::send($campaign->vendor->vendorAdmins, new AdCampaignRejected($campaign, $request->input('rejection_reason')));

        return response()->json(['message' => __('admin.ad_campaigns.rejected_msg')]);
    }

    // ─── Pause ────────────────────────────────────────────────────────────────

    public function pauseCampaign(AdCampaign $campaign): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        if ($campaign->status?->value !== AdCampaignStatus::Active->value) {
            return response()->json(['message' => __('admin.ad_campaigns.not_active')], 422);
        }

        $campaign->update(['status' => AdCampaignStatus::Paused->value]);

        return response()->json(['message' => __('admin.ad_campaigns.paused_msg')]);
    }

    // ─── Resume ───────────────────────────────────────────────────────────────

    public function resumeCampaign(AdCampaign $campaign): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        if ($campaign->status?->value !== AdCampaignStatus::Paused->value) {
            return response()->json(['message' => __('admin.ad_campaigns.not_paused')], 422);
        }

        $campaign->update(['status' => AdCampaignStatus::Active->value]);

        return response()->json(['message' => __('admin.ad_campaigns.resumed_msg')]);
    }

    // ─── Fraud Alerts ─────────────────────────────────────────────────────────

    public function fraudAlerts(): \Illuminate\View\View
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

        $unblocked = AdFraudPattern::where('is_blocked', false)->count();
        $blocked = AdFraudPattern::where('is_blocked', true)->count();

        $stats = [
            'unblocked' => $unblocked,
            'blocked' => $blocked,
            'total' => $unblocked + $blocked,
        ];

        return view('admin.ad-campaigns.fraud-alerts', compact('stats'));
    }

    public function fraudDatatable(Request $request): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.view'), 403);

        $query = AdFraudPattern::query()
            ->with(['campaign.vendor']);

        $query = $this->applyFilters($query, $request, [
            'is_blocked' => fn($q, $v) => $q->where('is_blocked', (int) $v),
            'campaign_id' => fn($q, $v) => $q->where('ad_campaign_id', $v),
        ]);

        $columns = [
            ['searchable_columns' => ['ad_fraud_patterns.ip_address'], 'orderable_column' => 'ad_fraud_patterns.ip_address'],
            ['searchable_columns' => [], 'orderable_column' => null], // vendor/campaign
            ['searchable_columns' => [], 'orderable_column' => 'clicks_last_hour'],
            ['searchable_columns' => [], 'orderable_column' => 'clicks_last_24h'],
            ['searchable_columns' => [], 'orderable_column' => 'is_blocked'],
            ['searchable_columns' => [], 'orderable_column' => 'blocked_at'],
            ['searchable_columns' => [], 'orderable_column' => null], // block_reason
            ['searchable_columns' => [], 'orderable_column' => null], // actions
        ];

        $canEdit = $admin->hasPermissionTo('ad_campaigns.edit');

        return $this->dataTableResponse($request, $query, $columns, function (AdFraudPattern $row) use ($canEdit) {
            $blockedBadge = $row->is_blocked
                ? '<span class="badge badge-danger text-xs">' . __('admin.ad_campaigns.blocked') . '</span>'
                : '<span class="badge badge-warning text-xs">' . __('admin.ad_campaigns.suspicious') . '</span>';

            $blockUrl = route('admin.ad-campaigns.fraud.block', $row->id);
            $actions = '';
            if ($canEdit && !$row->is_blocked) {
                $actions = "<button type=\"button\" class=\"btn btn-xs btn-danger js-block-ip-btn\" data-url=\"{$blockUrl}\" data-ip=\"" . e($row->ip_address) . "\">" . __('admin.ad_campaigns.block_ip') . "</button>";
            }

            return [
                'ip_address' => e($row->ip_address),
                'campaign' => e($row->campaign?->name ?? '—') . '<br><span class="text-xs text-gray-400">' . e($row->campaign?->vendor?->store_name ?? '') . '</span>',
                'clicks_last_hour' => $row->clicks_last_hour,
                'clicks_last_24h' => $row->clicks_last_24h,
                'is_blocked' => $blockedBadge,
                'blocked_at' => $row->blocked_at ? Carbon::parse($row->blocked_at)->format('d M Y H:i') : '—',
                'block_reason' => $row->block_reason ? e($row->block_reason) : '—',
                'actions' => $actions,
                'DT_RowData' => ['id' => $row->id],
            ];
        });
    }

    public function blockFraudPattern(Request $request, AdFraudPattern $pattern): JsonResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin->hasPermissionTo('ad_campaigns.edit'), 403);

        $request->validate([
            'block_reason' => ['nullable', 'string', 'max:100'],
        ]);

        $pattern->update([
            'is_blocked' => true,
            'blocked_at' => now(),
            'block_reason' => $request->input('block_reason', __('admin.ad_campaigns.blocked_by_admin_default')),
        ]);

        return response()->json(['message' => __('admin.ad_campaigns.ip_blocked_msg')]);
    }
}
