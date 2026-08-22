<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFlashSaleRequest;
use App\Http\Requests\Admin\StoreFlashSaleSubmissionRequest;
use App\Http\Requests\Admin\UpdateFlashSaleRequest;
use App\Models\AdminListing;
use App\Models\Category;
use App\Models\Country;
use App\Models\FlashSale;
use App\Models\FlashSaleAnalytic;
use App\Models\FlashSalePriceHistory;
use App\Models\FlashSaleSubmission;
use App\Models\FlashSaleVendorInvitition;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Services\FakeDiscountDetectionService;
use App\Services\FlashSaleService;
use App\Traits\HasDataTable;
use App\Traits\HasExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FlashSaleController extends Controller
{
    use HasDataTable;
    use HasExport;

    public function __construct(
        private readonly FlashSaleService $flashSaleService,
        private readonly FakeDiscountDetectionService $fakeDiscountService
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Index / Listing
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->filled('export')) {
            return $this->exportFlashSales($request);
        }

        $countries = Country::orderBy('name_en')
            // ->where('is_launched', true)
            ->get(['id', 'name_en']);

        $stats = [
            'live_count' => FlashSale::where('status', 'live')->count(),
            'upcoming_count' => FlashSale::whereIn('status', ['approved', 'submission_open'])->count(),
            'total_this_month_units' => (int) FlashSaleAnalytic::whereMonth('date', now()->month)->sum('units_sold'),
            'total_this_month_revenue' => (float) (FlashSaleAnalytic::whereMonth('date', now()->month)->sum('gross_revenue') / 100),
        ];

        return view('admin.flash-sales.index', compact('countries', 'stats'));
    }

    /**
     * Shared base query for the datatable and export, with request-driven filters applied.
     *
     * Note: `flash_sales` has no plain `name` column — it has bilingual `name_en`/`name_ar`.
     * Search matches both. Likewise there is no generic `starts_at`; the real column is
     * `sale_starts_at` (submission windows are separate), used below for date_from/date_to.
     */
    private function buildFlashSalesQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = FlashSale::query()
            ->leftJoin('countries as c', 'c.id', '=', 'flash_sales.country_id')
            ->leftJoin(
                DB::raw('(SELECT flash_sale_id, SUM(units_sold) as total_units FROM flash_sale_analytics GROUP BY flash_sale_id) as fsa'),
                'fsa.flash_sale_id',
                '=',
                'flash_sales.id'
            )
            ->select([
                'flash_sales.id',
                'flash_sales.name_en',
                'flash_sales.name_ar',
                'flash_sales.status',
                'flash_sales.submission_opens_at',
                'flash_sales.submission_closes_at',
                'flash_sales.sale_starts_at',
                'flash_sales.sale_ends_at',
                'flash_sales.min_discount_pct',
                'flash_sales.approved_slots_count',
                'flash_sales.max_total_slots',
                'c.name_en as country_name',
                DB::raw('COALESCE(fsa.total_units, 0) as units_sold'),
            ]);

        return $this->applyFilters($query, $request, [
            'status' => fn($q, $v) => $q->where('flash_sales.status', $v),
            'country_id' => fn($q, $v) => $q->where('flash_sales.country_id', $v),
            'date_from' => fn($q, $v) => $q->whereDate('flash_sales.sale_starts_at', '>=', $v),
            'date_to' => fn($q, $v) => $q->whereDate('flash_sales.sale_ends_at', '<=', $v),
            'search' => fn($q, $v) => $q->where(function ($sub) use ($v) {
                $sub->where('flash_sales.name_en', 'like', "%{$v}%")
                    ->orWhere('flash_sales.name_ar', 'like', "%{$v}%");
            }),
        ]);
    }

    private function exportFlashSales(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $sales = $this->buildFlashSalesQuery($request)->orderByDesc('flash_sales.sale_starts_at')->get();

        $saleIds = $sales->pluck('id');
        $submissionCounts = FlashSaleSubmission::whereIn('flash_sale_id', $saleIds)
            ->selectRaw('flash_sale_id, COUNT(*) as cnt')
            ->groupBy('flash_sale_id')
            ->pluck('cnt', 'flash_sale_id');

        $headers = ['Name', 'Status', 'Start', 'End', 'Submissions', 'Active Listings'];

        $rows = $sales->map(fn($row) => [
            $row->name_en,
            $row->status?->value,
            optional($row->sale_starts_at)->format('d M Y H:i'),
            optional($row->sale_ends_at)->format('d M Y H:i'),
            (int) ($submissionCounts[$row->id] ?? 0),
            (int) $row->approved_slots_count,
        ]);

        return match ($request->input('export')) {
            'excel' => $this->exportExcel('flash-sales', $headers, $rows),
            'csv' => $this->exportCsv('flash-sales', $headers, $rows),
            'word' => $this->exportWord('flash-sales', 'Flash Sales', $rows),
            default => abort(400, 'Invalid export format.'),
        };
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = $this->buildFlashSalesQuery($request);

        return $this->dataTableResponse($request, $query, $this->indexColumns(), function ($row) {
            $submissionPeriod = $row->submission_opens_at && $row->submission_closes_at
                ? \Carbon\Carbon::parse($row->submission_opens_at)->format('M j') . '–' . \Carbon\Carbon::parse($row->submission_closes_at)->format('j')
                : '—';
            $salePeriod = $row->sale_starts_at && $row->sale_ends_at
                ? \Carbon\Carbon::parse($row->sale_starts_at)->format('M j H:i') . ' – ' . \Carbon\Carbon::parse($row->sale_ends_at)->format('H:i')
                : '—';

            return [
                'id' => $row->id,
                'name_en' => e($row->name_en),
                'country_name' => e($row->country_name ?? 'All'),
                'status' => $row->status?->value,
                'submission_period' => $submissionPeriod,
                'sale_period' => $salePeriod,
                'slots' => ($row->approved_slots_count ?? 0) . '/' . ($row->max_total_slots ?? '∞'),
                'min_discount_pct' => $row->min_discount_pct . '%+',
                'units_sold' => (int) $row->units_sold,
                'edit_url' => route('admin.flash-sales.edit', $row->id),
                'show_url' => route('admin.flash-sales.show', $row->id),
            ];
        });
    }

    private function indexColumns(): array
    {
        return [
            ['data' => 'name_en', 'name' => 'flash_sales.name_en'],
            ['data' => 'country_name', 'name' => 'c.name_en', 'orderable' => false],
            ['data' => 'status', 'name' => 'flash_sales.status'],
            ['data' => 'submission_period', 'name' => 'flash_sales.submission_opens_at', 'orderable' => false],
            ['data' => 'sale_period', 'name' => 'flash_sales.sale_starts_at'],
            ['data' => 'slots', 'name' => 'flash_sales.approved_slots_count', 'orderable' => false],
            ['data' => 'min_discount_pct', 'name' => 'flash_sales.min_discount_pct'],
            ['data' => 'units_sold', 'name' => 'units_sold'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create / Store
    // ─────────────────────────────────────────────────────────────────────────

    public function create(): View
    {
        return view('admin.flash-sales.create', [
            'countries' => Country::orderBy('name_en')->where('is_launched', true)->get(),
            'categories' => Category::orderBy('name_en')->get(),
        ]);
    }

    public function store(StoreFlashSaleRequest $request): JsonResponse
    {
        $sale = $this->flashSaleService->create($request->validated(), auth('admin')->user());

        return response()->json([
            'success' => true,
            'message' => __('admin.flash_sales.created_message'),
            'redirect' => route('admin.flash-sales.edit', $sale->id),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────────────────────

    public function show(FlashSale $flashSale): View
    {
        $flashSale->load(['country', 'createdByAdmin', 'updatedByAdmin']);

        $submissionStats = FlashSaleSubmission::where('flash_sale_id', $flashSale->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $invitationStatuses = ['pending', 'accepted', 'declined', 'submitted'];
        $invitationCounts = FlashSaleVendorInvitition::where('flash_sale_id', $flashSale->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $invitationStats = collect($invitationStatuses)
            ->mapWithKeys(fn($s) => [$s => (int) ($invitationCounts[$s] ?? 0)]);

        $invitationCount = $invitationStats->sum();

        $nextStatuses = $flashSale->getNextStatuses();

        return view('admin.flash-sales.show', compact(
            'flashSale',
            'submissionStats',
            'invitationStats',
            'invitationCount',
            'nextStatuses',
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Edit / Update
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(FlashSale $flashSale): View
    {
        $flashSale->load(['country', 'createdByAdmin']);

        $invitationStatuses = ['pending', 'accepted', 'declined', 'submitted'];
        $invitationCounts = FlashSaleVendorInvitition::where('flash_sale_id', $flashSale->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');
        $invitationStats = collect($invitationStatuses)
            ->mapWithKeys(fn($s) => [$s => (int) ($invitationCounts[$s] ?? 0)]);
        $invitationCount = $invitationStats->sum();

        $submissionStats = FlashSaleSubmission::where('flash_sale_id', $flashSale->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return view('admin.flash-sales.edit', [
            'sale' => $flashSale,
            'countries' => Country::orderBy('name_en')->where('is_launched', true)->get(),
            'categories' => Category::orderBy('name_en')->get(),
            'invitationStats' => $invitationStats,
            'invitationCount' => $invitationCount,
            'submissionStats' => $submissionStats,
        ]);
    }

    public function update(UpdateFlashSaleRequest $request, FlashSale $flashSale): JsonResponse
    {
        $this->flashSaleService->update($flashSale, $request->validated(), auth('admin')->user());

        return response()->json(['success' => true, 'message' => __('admin.flash_sales.updated_message')]);
    }

    public function destroy(FlashSale $flashSale): JsonResponse
    {
        if ($flashSale->status?->value !== 'draft') {
            return response()->json(['message' => __('admin.flash_sales.only_draft_deletable')], 422);
        }
        $flashSale->delete();
        return response()->json(['success' => true, 'message' => __('admin.flash_sales.deleted_message')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Status transitions
    // ─────────────────────────────────────────────────────────────────────────

    public function transition(Request $request, FlashSale $flashSale): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:open_submissions,close_submissions,move_to_review,start_sale,end_sale,mark_approved,cancel',
            'reason' => 'required_if:action,cancel|nullable|string|max:500',
        ]);

        $actionToStatus = [
            'open_submissions' => 'submission_open',
            'close_submissions' => 'submission_closed',
            'move_to_review' => 'under_review',
            'mark_approved' => 'approved',
            'start_sale' => 'live',
            'end_sale' => 'ended',
            'cancel' => 'cancelled',
        ];

        $newStatus = $actionToStatus[$request->action];
        $admin = auth('admin')->user();
        $statusLabels = [
            'draft'             => __('admin.flash_sales.status_draft'),
            'submission_open'   => __('admin.flash_sales.status_submission_open'),
            'submission_closed' => __('admin.flash_sales.status_submission_closed'),
            'under_review'      => __('admin.flash_sales.status_under_review'),
            'approved'          => __('admin.flash_sales.status_approved'),
            'live'              => __('admin.flash_sales.status_live'),
            'ended'             => __('admin.flash_sales.status_ended'),
            'cancelled'         => __('admin.flash_sales.status_cancelled'),
        ];

        try {
            if ($request->action === 'open_submissions') {
                $this->flashSaleService->openSubmissions($flashSale, $admin);
            } else {
                $this->flashSaleService->transition($flashSale, $newStatus, $admin, $request->reason ?? '');
            }

            return response()->json([
                'success' => true,
                'message' => __('admin.flash_sales.status_updated_to', ['status' => $statusLabels[$newStatus] ?? $newStatus]),
                'new_status' => $flashSale->fresh()->status?->value,
            ]);
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Vendor invitations
    // ─────────────────────────────────────────────────────────────────────────

    public function inviteVendors(Request $request, FlashSale $flashSale): JsonResponse
    {
        if ($request->filled('vendor_ids')) {
            $request->validate([
                'vendor_ids' => 'required|array',
                'vendor_ids.*' => 'string',
            ]);

            $invited = 0;
            $skipped = 0;
            foreach ($request->vendor_ids as $vendorId) {
                $vendor = \App\Models\Vendor::find($vendorId);
                if (!$vendor) {
                    $skipped++;
                    continue;
                }
                $this->flashSaleService->inviteVendorManually($flashSale, $vendorId);
                $invited++;
            }

            return response()->json([
                'success' => true,
                'count' => $invited,
                'message' => $skipped
                    ? __('admin.flash_sales.vendors_invited_with_skipped', ['invited' => $invited, 'skipped' => $skipped])
                    : __('admin.flash_sales.vendors_invited_result', ['count' => $invited]),
            ]);
        }

        $count = $this->flashSaleService->inviteEligibleVendors($flashSale);
        return response()->json([
            'success' => true,
            'count' => $count,
            'message' => __('admin.flash_sales.vendors_invited_result', ['count' => $count]),
        ]);
    }

    public function eligibleVendorCount(FlashSale $flashSale): JsonResponse
    {
        $count = $this->flashSaleService->countEligibleVendors($flashSale);
        return response()->json(['data' => ['count' => $count]]);
    }

    public function invitationsDatatable(Request $request, FlashSale $flashSale): JsonResponse
    {
        $query = FlashSaleVendorInvitition::where('flash_sale_vendor_invititions.flash_sale_id', $flashSale->id)
            ->leftJoin('vendors', 'vendors.id', '=', 'flash_sale_vendor_invititions.vendor_id')
            ->select([
                'flash_sale_vendor_invititions.id',
                'flash_sale_vendor_invititions.vendor_id',
                'flash_sale_vendor_invititions.invitation_type',
                'flash_sale_vendor_invititions.status',
                'flash_sale_vendor_invititions.invited_at',
                'flash_sale_vendor_invititions.notified_at',
                'flash_sale_vendor_invititions.responded_at',
                'flash_sale_vendor_invititions.decline_reason',
                'flash_sale_vendor_invititions.slots_allocated',
                'vendors.store_name',
            ]);

        if ($request->filled('status')) {
            $query->where('flash_sale_vendor_invititions.status', $request->status);
        }

        return $this->dataTableResponse($request, $query, [], function ($row) {
            return [
                'id' => $row->id,
                'vendor_id' => $row->vendor_id,
                'store_name' => e($row->store_name ?? '(unknown vendor)'),
                'invitation_type' => $row->invitation_type,
                'status' => $row->status?->value,
                'slots_allocated' => $row->slots_allocated,
                'invited_at' => $row->invited_at?->toDateTimeString(),
                'notified_at' => $row->notified_at?->toDateTimeString(),
                'responded_at' => $row->responded_at?->toDateTimeString(),
                'decline_reason' => e($row->decline_reason ?? ''),
                'can_resend' => in_array($row->status?->value, ['pending', 'declined'], true),
            ];
        });
    }

    public function resendInvitation(FlashSale $flashSale, FlashSaleVendorInvitition $invitation): JsonResponse
    {
        if ($invitation->flash_sale_id !== $flashSale->id) {
            return response()->json(['message' => __('admin.flash_sales.invitation_not_belong')], 403);
        }

        if (!in_array($invitation->status?->value, ['pending', 'declined'], true)) {
            return response()->json(['message' => __('admin.flash_sales.notifications_resend_restricted')], 422);
        }

        $invitation->update(['notified_at' => now()]);
        \App\Jobs\FlashSaleInviteBulkJob::dispatch($flashSale->id, [$invitation->vendor_id]);

        return response()->json(['success' => true, 'message' => __('admin.flash_sales.notification_queued_resend')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Submissions
    // ─────────────────────────────────────────────────────────────────────────

    public function submissionsDatatable(Request $request, FlashSale $flashSale): JsonResponse
    {
        $query = FlashSaleSubmission::where('flash_sale_id', $flashSale->id)
            ->with([
                'vendorListing.productVariant.product.images',
                'adminListing.productVariant.product.images',
            ])
            ->leftJoin('vendors', 'vendors.id', '=', 'flash_sale_submissions.vendor_id')
            ->select('flash_sale_submissions.*', 'vendors.store_name as vendor_store_name');

        if ($request->filled('status')) {
            $query->where('flash_sale_submissions.status', $request->status);
        }
        if ($request->filled('vendor_id')) {
            $query->where('flash_sale_submissions.vendor_id', $request->vendor_id);
        }

        return $this->dataTableResponse($request, $query, [], function ($row) use ($flashSale) {
            $analysis = $this->fakeDiscountService->analyze($row);
            $isAdminListing = $row->admin_listing_id !== null;
            $listing = $isAdminListing ? $row->adminListing : $row->vendorListing;
            $variant = $listing?->productVariant;
            $product = $variant?->product;
            $productImage = $product?->images?->where('is_primary', true)->first();

            $discountPct = (float) $row->calculated_discount_pct;
            $minPct = (float) $flashSale->min_discount_pct;

            return [
                'id' => $row->id,
                'vendor_listing_id' => $row->vendor_listing_id,
                'admin_listing_id' => $row->admin_listing_id,
                'type' => $isAdminListing ? 'admin' : 'vendor',
                'product_name' => e($product?->name_en ?? 'Unknown'),
                'variant_name' => e($variant?->name_en ?? ''),
                'platform_sku' => $isAdminListing ? e($listing?->platform_sku ?? '') : null,
                'product_image_url' => $productImage?->url ?? null,
                'vendor_store_name' => $isAdminListing ? null : e($row->vendor_store_name),
                'vendor_id' => $row->vendor_id,
                'original_price_formatted' => number_format($row->original_price / 100, 2) . ' ' . $row->flash_price_currency,
                'flash_price_formatted' => number_format($row->flash_price / 100, 2) . ' ' . $row->flash_price_currency,
                'flash_price_raw' => $row->flash_price,
                'calculated_discount_pct' => $discountPct,
                'discount_ok' => $discountPct >= $minPct,
                'min_discount_pct' => $minPct,
                'is_suspect' => $analysis['is_suspect'],
                'suspect_reasons' => $analysis['reasons'],
                'quantity_sold' => $row->quantity_sold,
                'max_quantity_total' => $row->max_quantity_total,
                'quantity_remaining' => $row->quantity_remaining,  // READ virtual column
                'status' => $row->status?->value,
                'submitted_at_human' => $row->submitted_at?->diffForHumans(),
                'admin_notes' => $row->admin_notes,
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Manual submission creation (admin adds a vendor listing or admin listing)
    // ─────────────────────────────────────────────────────────────────────────

    public function storeSubmission(StoreFlashSaleSubmissionRequest $request, FlashSale $flashSale): JsonResponse
    {
        $data = $request->validated();
        $flashPrice = (int) $data['flash_price'];
        $originalPrice = (int) $data['original_price'];

        if ($flashPrice >= $originalPrice) {
            return response()->json(['message' => __('admin.flash_sales.flash_price_below_original')], 422);
        }

        $discountPct = round((($originalPrice - $flashPrice) / $originalPrice) * 100, 2);
        if ($discountPct < (float) $flashSale->min_discount_pct) {
            return response()->json(['message' => __('admin.flash_sales.discount_below_minimum')], 422);
        }

        $payload = [
            'flash_sale_id' => $flashSale->id,
            'status' => 'submitted',
            'flash_price' => $flashPrice,
            'original_price' => $originalPrice,
            'calculated_discount_pct' => $discountPct,
            'max_quantity_total' => (int) $data['max_quantity_total'],
            'max_quantity_per_customer' => $data['max_quantity_per_customer'] ?? null,
            'admin_notes' => $data['admin_notes'] ?? null,
            'submitted_at' => now(),
        ];

        if ($data['submission_type'] === 'admin') {
            $listing = AdminListing::findOrFail($data['admin_listing_id']);

            $payload['admin_listing_id'] = $listing->id;
            $payload['vendor_listing_id'] = null;
            $payload['vendor_id'] = null;
            $payload['flash_price_currency'] = $listing->currency;

            $avg30d = FlashSalePriceHistory::where('admin_listing_id', $listing->id)
                ->where('recorded_at', '>=', now()->subDays(30))
                ->avg('price');
            $payload['reference_price_30d'] = $avg30d ? (int) round($avg30d) : $originalPrice;
        } else {
            $listing = VendorListing::where('id', $data['vendor_listing_id'])
                ->where('vendor_id', $data['vendor_id'])
                ->firstOrFail();

            $payload['vendor_listing_id'] = $listing->id;
            $payload['admin_listing_id'] = null;
            $payload['vendor_id'] = $data['vendor_id'];
            $payload['flash_price_currency'] = $listing->currency;

            $avg30d = FlashSalePriceHistory::where('vendor_listing_id', $listing->id)
                ->where('recorded_at', '>=', now()->subDays(30))
                ->avg('price');
            $payload['reference_price_30d'] = $avg30d ? (int) round($avg30d) : $originalPrice;
        }

        $submission = FlashSaleSubmission::create($payload);

        return response()->json([
            'success' => true,
            'message' => __('admin.flash_sales.submission_created_message'),
            'data' => ['id' => $submission->id],
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin listing search (Select2 AJAX)
    // ─────────────────────────────────────────────────────────────────────────

    public function searchVendorListings(Request $request): JsonResponse
    {
        $term = $request->input('q', '');
        $vendorId = $request->input('vendor_id');

        $listings = VendorListing::query()
            ->join('product_variants as pv', 'pv.id', '=', 'vendor_listings.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->when($vendorId, fn($q) => $q->where('vendor_listings.vendor_id', $vendorId))
            ->where(function ($q) use ($term) {
                $q->where('p.name_en', 'like', "%{$term}%")
                    ->orWhere('pv.sku', 'like', "%{$term}%");
            })
            ->orderBy('p.name_en')
            ->limit(30)
            ->get([
                'vendor_listings.id',
                'vendor_listings.price',
                'vendor_listings.currency',
                'pv.sku',
                'p.name_en',
            ]);

        return response()->json([
            'results' => $listings->map(fn($l) => [
                'id' => $l->id,
                'text' => "{$l->name_en} — {$l->sku}",
                'price' => $l->price,
                'currency' => $l->currency,
            ]),
        ]);
    }

    public function searchVendors(Request $request): JsonResponse
    {
        $term = $request->input('q', '');

        $vendors = Vendor::query()
            ->where('store_name', 'like', "%{$term}%")
            ->orderBy('store_name')
            ->limit(30)
            ->get(['id', 'store_name']);

        return response()->json([
            'results' => $vendors->map(fn($v) => ['id' => $v->id, 'text' => $v->store_name]),
        ]);
    }

    public function searchAdminListings(Request $request): JsonResponse
    {
        $term = $request->input('q', '');

        $listings = AdminListing::query()
            ->join('product_variants as pv', 'pv.id', '=', 'admin_listings.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->whereNull('admin_listings.deleted_at')
            ->where('p.name_en', 'like', "%{$term}%")
            ->orderBy('p.name_en')
            ->limit(30)
            ->get([
                'admin_listings.id',
                'admin_listings.price',
                'admin_listings.currency',
                'p.name_en',
            ]);

        return response()->json([
            'results' => $listings->map(fn($l) => [
                'id' => $l->id,
                'text' => $l->name_en,
                'price' => $l->price,
                'currency' => $l->currency,
            ]),
        ]);
    }

    public function submissionStats(FlashSale $flashSale): JsonResponse
    {
        $counts = FlashSaleSubmission::where('flash_sale_id', $flashSale->id)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return response()->json([
            'data' => [
                'submitted' => (int) ($counts['submitted'] ?? 0),
                'under_review' => (int) ($counts['under_review'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
                'pending' => (int) (($counts['submitted'] ?? 0) + ($counts['under_review'] ?? 0)),
            ]
        ]);
    }

    public function reviewSubmission(Request $request, FlashSaleSubmission $submission): JsonResponse
    {
        $request->validate([
            'decision' => 'required|in:approved,rejected',
            'rejection_code' => 'required_if:decision,rejected|nullable|string|max:50',
            'rejection_reason' => 'nullable|string|max:500',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $this->flashSaleService->reviewSubmission(
                $submission,
                $request->decision,
                $request->only('rejection_code', 'rejection_reason', 'admin_notes'),
                auth('admin')->user()
            );

            $decisionLabel = $request->decision === 'approved'
                ? __('admin.flash_sales.status_approved')
                : __('admin.flash_sales.status_rejected');

            return response()->json(['success' => true, 'message' => __('admin.flash_sales.submission_decision_message', ['decision' => $decisionLabel])]);
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function bulkReviewSubmissions(Request $request, FlashSale $flashSale): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:flash_sale_submissions,id',
            'decision' => 'required|in:approved,rejected',
            'rejection_code' => 'required_if:decision,rejected|nullable|string|max:50',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $result = $this->flashSaleService->bulkReview(
            $request->ids,
            $request->decision,
            $request->only('rejection_code', 'rejection_reason'),
            auth('admin')->user()
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Submission detail (for review modal)
    // ─────────────────────────────────────────────────────────────────────────

    public function submissionDetail(FlashSaleSubmission $submission): JsonResponse
    {
        $submission->load([
            'vendorListing.productVariant.product.images',
            'adminListing.productVariant.product.images',
            'histories',
            'reviewedByAdmin',
        ]);

        $analysis = $this->fakeDiscountService->analyze($submission);
        $isAdminListing = $submission->admin_listing_id !== null;

        $priceHistory = FlashSalePriceHistory::query()
            ->when(
                $isAdminListing,
                fn($q) => $q->where('admin_listing_id', $submission->admin_listing_id),
                fn($q) => $q->where('vendor_listing_id', $submission->vendor_listing_id)
            )
            ->where('recorded_at', '>=', now()->subDays(30))
            ->orderBy('recorded_at')
            ->get()
            ->map(fn($r) => [
                'date' => $r->recorded_at->toDateString(),
                'price_raw' => $r->price,
                'price_formatted' => number_format($r->price / 100, 2),
            ]);

        $listing = $isAdminListing ? $submission->adminListing : $submission->vendorListing;
        $variant = $listing?->productVariant;
        $product = $variant?->product;
        $image = $product?->images?->where('is_primary', true)->first();

        $stockLevels = [
            'max_quantity_total' => $submission->max_quantity_total,
            'quantity_sold' => $submission->quantity_sold,
            'quantity_remaining' => $submission->quantity_remaining,
            'max_quantity_per_customer' => $submission->max_quantity_per_customer,
        ];

        return response()->json([
            'data' => [
                'id' => $submission->id,
                'status' => $submission->status?->value,
                'type' => $isAdminListing ? 'admin' : 'vendor',
                'product_name' => e($product?->name_en ?? 'Unknown'),
                'product_image_url' => $image?->url ?? null,
                'vendor_listing_id' => $submission->vendor_listing_id,
                'admin_listing_id' => $submission->admin_listing_id,
                'flash_price_raw' => $submission->flash_price,
                'original_price_raw' => $submission->original_price,
                'flash_price_formatted' => number_format($submission->flash_price / 100, 2) . ' ' . $submission->flash_price_currency,
                'original_price_formatted' => number_format($submission->original_price / 100, 2) . ' ' . $submission->flash_price_currency,
                'calculated_discount_pct' => (float) $submission->calculated_discount_pct,
                'admin_notes' => $submission->admin_notes,
                'vendor_notes' => $submission->vendor_notes,
                'rejection_code' => $submission->rejection_code,
                'rejection_reason' => $submission->rejection_reason,
                'analysis' => $analysis,
                'price_history' => $priceHistory,
                'stock' => $stockLevels,
            ]
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Price history (for review modal chart)
    // ─────────────────────────────────────────────────────────────────────────

    public function priceHistory(Request $request): JsonResponse
    {
        $request->validate([
            'vendor_listing_id' => 'required_without:admin_listing_id|nullable|exists:vendor_listings,id',
            'admin_listing_id' => 'required_without:vendor_listing_id|nullable|exists:admin_listings,id',
        ]);

        $rows = FlashSalePriceHistory::query()
            ->when(
                $request->filled('admin_listing_id'),
                fn($q) => $q->where('admin_listing_id', $request->admin_listing_id),
                fn($q) => $q->where('vendor_listing_id', $request->vendor_listing_id)
            )
            ->where('recorded_at', '>=', now()->subDays(30))
            ->orderBy('recorded_at')
            ->get();

        return response()->json([
            'data' => $rows->map(fn($r) => [
                'date' => $r->recorded_at->toDateString(),
                'price_raw' => $r->price,
                'price_formatted' => number_format($r->price / 100, 2),
            ])
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Live monitor
    // ─────────────────────────────────────────────────────────────────────────

    public function liveMonitorData(FlashSale $flashSale): JsonResponse
    {
        if ($flashSale->status?->value !== 'live') {
            return response()->json(['message' => __('admin.flash_sales.sale_not_live')], 422);
        }

        // A flash sale is scoped to one country_id and therefore one currency; all submissions
        // share the same flash_price_currency. GROUP BY currency is added as an explicit
        // safeguard so that any future schema change allowing multi-currency sales produces
        // separate rows instead of silently blending amounts.
        $totalsRows = FlashSaleSubmission::where('flash_sale_id', $flashSale->id)
            ->selectRaw('flash_price_currency, SUM(quantity_sold) as units, SUM(flash_price * quantity_sold) as revenue, COUNT(CASE WHEN status="sold_out" THEN 1 END) as sold_out')
            ->groupBy('flash_price_currency')
            ->get();

        // Collapse to a single row (expected exactly one currency per flash sale).
        $totals = (object) [
            'units'    => $totalsRows->sum('units'),
            'revenue'  => $totalsRows->sum('revenue'),
            'sold_out' => $totalsRows->sum('sold_out'),
        ];

        $top = FlashSaleSubmission::where('flash_sale_id', $flashSale->id)
            ->with(['vendorListing.productVariant.product', 'adminListing.productVariant.product'])
            ->orderByDesc('quantity_sold')
            ->limit(5)
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'product_name' => e($s->listing()?->productVariant?->product?->name_en ?? 'Unknown'),
                'vendor_name' => '',
                'quantity_sold' => $s->quantity_sold,
                'quantity_remaining' => $s->quantity_remaining,  // virtual column
                'revenue_formatted' => number_format(($s->flash_price * $s->quantity_sold) / 100, 2),
            ]);

        return response()->json([
            'data' => [
                'total_units_sold' => (int) ($totals->units ?? 0),
                'total_revenue' => (float) (($totals->revenue ?? 0) / 100),
                'sold_out_count' => (int) ($totals->sold_out ?? 0),
                'time_remaining_seconds' => max(0, now()->diffInSeconds($flashSale->sale_ends_at, false)),
                'top_submissions' => $top,
            ]
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Analytics
    // ─────────────────────────────────────────────────────────────────────────

    public function analyticsData(FlashSale $flashSale): JsonResponse
    {
        if (!in_array($flashSale->status?->value, ['live', 'ended'])) {
            return response()->json(['message' => __('admin.flash_sales.analytics_available_after_live')], 422);
        }

        $byDay = FlashSaleAnalytic::where('flash_sale_id', $flashSale->id)
            ->orderBy('date')
            ->get();

        $summary = [
            'units_sold' => $byDay->sum('units_sold'),
            'gross_revenue' => $byDay->sum('gross_revenue'),
            'discount_given' => $byDay->sum('discount_given'),
            'platform_commission' => $byDay->sum('platform_commission'),
            'vendor_payout' => $byDay->sum('vendor_payout'),
            'avg_conversion_pct' => round($byDay->avg('conversion_rate') * 100, 2),
        ];

        return response()->json([
            'data' => [
                'summary' => $summary,
                'by_day' => $byDay->map(fn($r) => [
                    'date' => $r->date->toDateString(),
                    'units_sold' => $r->units_sold,
                    'gross_revenue' => $r->gross_revenue,
                    'discount_given' => $r->discount_given,
                ]),
            ]
        ]);
    }
}
