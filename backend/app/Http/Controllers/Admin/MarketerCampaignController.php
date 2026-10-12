<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCampaignWhatsAppNotificationJob;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\Country;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignCategoryRule;
use App\Models\MarketerCampaignInvitation;
use App\Models\MarketerCampaignSample;
use App\Models\MarketerCommissionCountrySetting;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Services\Campaign\CampaignSampleService;
use App\Services\Inventory\InsufficientStockException;
use App\Services\MarketerCampaignService;
use App\Services\ReferralCodeResolver;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketerCampaignController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private MarketerCampaignService $service) {}

    public function index(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.view'), 403);

        $campaigns = MarketerCampaign::with([
            'vendor', 'country',
            'vendorListing.productVariant.product',
            'adminListing.productVariant.product',
            'travelPackage', 'classifiedListing',
            'marketerListing.productVariant.product', 'ownerMarketer', 'promoter',
            'invitations.marketer',
        ])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->source, fn ($q) => match ($request->source) {
                'peer' => $q->whereNotNull('marketer_listing_id'),
                'admin' => $q->whereNotNull('admin_listing_id'),
                'vendor' => $q->whereNotNull('vendor_listing_id'),
                default => $q,
            })
            ->when($request->country_id, fn ($q) => $q->where('country_id', $request->country_id))
            ->latest()
            ->paginate(20);

        $pendingCount = MarketerCampaign::where('status', 'pending_admin')->count();
        $countries = Country::orderBy('name_en')->get();

        return view('admin.marketer_campaigns.index', compact('campaigns', 'pendingCount', 'countries'));
    }

    public function show(MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.view'), 403);
        $marketerCampaign->load([
            'vendor', 'country',
            'vendorListing.productVariant.product',
            'adminListing.productVariant.product',
            'marketerListing.productVariant.product', 'ownerMarketer', 'promoter',
            'invitations.marketer.marketerProfile',
            'tieredRules',
            'conversions.order',
            'samples.invitation.marketer',
            'samples.customAttributeValues',
            'categoryRules.category',
            'categoryRules.classifiedCategory',
        ]);

        $categoryId = $marketerCampaign->vendorListing?->productVariant?->product?->category_id
            ?? $marketerCampaign->adminListing?->productVariant?->product?->category_id
            ?? $marketerCampaign->marketerListing?->productVariant?->product?->category_id;

        $commissionSetting = $categoryId
            ? MarketerCommissionCountrySetting::where('category_id', $categoryId)
                ->where('country_id', $marketerCampaign->country_id)
                ->first()
            : null;

        $categories = Category::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);
        $classifiedCategories = ClassifiedCategory::orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);

        $referralChains = app(ReferralCodeResolver::class)->chainsFor($marketerCampaign);

        return view('admin.marketer_campaigns.show', compact('marketerCampaign', 'commissionSetting', 'categories', 'classifiedCategories', 'referralChains'));
    }

    public function approve(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.approve'), 403);

        abort_unless($marketerCampaign->status === 'pending_admin', 422,
            'هذه الحملة لا يمكن قبولها في حالتها الحالية.');

        try {
            $this->service->approveCampaign($marketerCampaign, auth()->guard('admin')->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم قبول الحملة وأصبحت نشطة.');
    }

    public function reject(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.reject'), 403);
        $request->validate(['rejection_reason' => 'required|string|max:500']);

        $this->service->rejectCampaign(
            $marketerCampaign,
            auth()->guard('admin')->user(),
            $request->rejection_reason
        );

        return back()->with('success', 'تم رفض الحملة.');
    }

    public function updateSampleStatus(
        Request $request,
        MarketerCampaign $marketerCampaign,
        MarketerCampaignSample $sample
    ) {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.approve'), 403);

        abort_unless($sample->campaign_id === $marketerCampaign->id, 403);

        $request->validate([
            'status' => 'required|in:pending,dispatched,delivered,returned',
        ]);

        $allowed = ['pending' => ['dispatched'], 'dispatched' => ['delivered'], 'delivered' => ['returned'], 'returned' => [], 'cancelled' => []];
        abort_unless(in_array($request->status, $allowed[$sample->status] ?? [], true), 422, 'انتقال حالة العينة غير مسموح.');

        // An influencer's sample can only go out once he accepted the invitation
        // (a pending / rejected / timed-out invitation never receives stock).
        if ($request->status === 'dispatched' && $sample->invitation_id) {
            abort_unless($sample->invitation?->status === 'accepted', 422, __('admin.marketer_campaigns.sample_invitation_not_accepted'));
        }

        // Dispatch = stock-out like a normal sale + admin commission snapshot,
        // both exactly once (a repeated request is a no-op).
        if ($request->status === 'dispatched') {
            try {
                app(CampaignSampleService::class)->dispatch($sample);
            } catch (InsufficientStockException $e) {
                return back()->with('error', $e->getMessage());
            }

            return back()->with('success', 'تم تحديث حالة العينة.');
        }

        $data = ['status' => $request->status];
        if ($request->status === 'delivered' && ! $sample->delivered_at) {
            $data['delivered_at'] = now();
        }

        $sample->update($data);

        return back()->with('success', 'تم تحديث حالة العينة.');
    }

    public function markInvitationFeePaid(
        MarketerCampaign $marketerCampaign,
        MarketerCampaignInvitation $invitation
    ) {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.approve'), 403);

        abort_unless($invitation->campaign_id === $marketerCampaign->id, 403);

        // Same lock + status guard as the wallet collector and the weekly statement:
        // a fee already settled there can't be marked paid again (and vice versa).
        $marked = DB::transaction(function () use ($invitation) {
            $locked = MarketerCampaignInvitation::whereKey($invitation->id)->lockForUpdate()->first();
            if (! $locked || $locked->platform_fee_status !== 'pending' || $locked->payoutItems()->exists()) {
                return false;
            }
            $locked->update([
                'platform_fee_status' => 'paid',
                'platform_fee_paid_via' => 'admin',
                'platform_fee_paid_at' => now(),
            ]);

            return true;
        });

        abort_unless($marked, 422, 'الرسوم مدفوعة بالفعل أو غير مطبقة.');

        return back()->with('success', 'تم تسجيل دفع رسوم الإنفلوينسر.');
    }

    /**
     * P10 follow-up: re-send the WhatsApp invitation (same signed accept/reject links) of a PENDING invitation.
     * Cooldown: `marketer_whatsapp_resend_cooldown_minutes` (default 10) since the last resend, enforced with one
     * atomic conditional UPDATE so two concurrent clicks cannot both pass.
     */
    public function resendInvitationWhatsApp(MarketerCampaign $marketerCampaign, MarketerCampaignInvitation $invitation)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.approve'), 403);
        abort_unless($invitation->campaign_id === $marketerCampaign->id, 404);

        if (! $invitation->isPending() || $invitation->isTimedOut()) {
            return back()->with('error', __('admin.marketer_campaigns.whatsapp_resend_not_pending'));
        }

        $cooldown = max(0, (int) setting('marketer_whatsapp_resend_cooldown_minutes', 10));
        $claimed = MarketerCampaignInvitation::whereKey($invitation->id)
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('whatsapp_resent_at')->orWhere('whatsapp_resent_at', '<=', now()->subMinutes($cooldown)))
            ->update(['whatsapp_resent_at' => now(), 'whatsapp_resend_count' => DB::raw('whatsapp_resend_count + 1')]);

        if (! $claimed) {
            $readyAt = $invitation->fresh()->whatsapp_resent_at?->copy()->addMinutes($cooldown);
            $wait = $readyAt ? max(1, (int) ceil(now()->diffInSeconds($readyAt, false) / 60)) : 1;

            return back()->with('error', __('admin.marketer_campaigns.whatsapp_resend_cooldown', ['minutes' => $wait]));
        }

        SendCampaignWhatsAppNotificationJob::dispatch($invitation->id, true);

        return back()->with('success', __('admin.marketer_campaigns.whatsapp_resend_queued'));
    }

    public function inviteMarketers(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.create'), 403);

        $request->validate([
            'marketer_ids' => ['required', 'array', 'min:1'],
            'marketer_ids.*' => ['uuid', 'distinct', 'exists:marketers,id'],
        ]);

        $result = $this->service->inviteMarketers($marketerCampaign, $request->marketer_ids);

        $message = count($result['invited']).' marketer(s) invited.';
        if (! empty($result['skipped'])) {
            $message .= ' '.count($result['skipped']).' skipped (already invited or inactive).';
        }

        return back()->with('success', $message);
    }

    public function create()
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.create'), 403);

        $vendors = Vendor::orderBy('store_name')->get();
        $marketers = Marketer::where('global_status', 'active')->orderBy('name')->get();
        $countries = Country::orderBy('name_en')->get();
        $vendorCountries = $vendors->mapWithKeys(fn ($v) => [$v->id => $v->country_id]);
        $countryCurrencies = Country::pluck('currency_code', 'id');

        $oldVendorListing = null;
        if (old('vendor_listing_id')) {
            $oldVendorListing = VendorListing::with('productVariant.product')
                ->find(old('vendor_listing_id'));
        }

        $categories = Category::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);
        $classifiedCategories = ClassifiedCategory::orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);

        return view('admin.marketer_campaigns.create', compact(
            'vendors', 'marketers', 'countries', 'vendorCountries', 'countryCurrencies', 'oldVendorListing',
            'categories', 'classifiedCategories'
        ));
    }

    public function store(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.create'), 403);

        $data = $request->validate([
            // enhancement.md P-14 task 2: admin can now create a platform
            // (admin-listing) campaign without choosing a vendor —
            // vendor_id is only required when admin_listing_id is absent.
            'vendor_id' => 'required_without:admin_listing_id|nullable|uuid|exists:vendors,id',
            'vendor_listing_id' => 'nullable|uuid|exists:vendor_listings,id',
            'admin_listing_id' => 'nullable|uuid|exists:admin_listings,id',
            'marketer_ids' => 'required|array|min:1',
            'marketer_ids.*' => 'uuid|exists:marketers,id',
            'country_id' => 'required|uuid|exists:countries,id',
            'currency' => 'required|string|size:3|exists:currencies,code',
            'commission_type' => 'required|in:fixed,percentage,last_click,tiered',
            'max_commission_budget' => 'required|integer|min:0',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'product_category_selection_mode' => 'nullable|in:all,include,exclude',
            'product_category_ids' => 'nullable|array',
            'product_category_ids.*' => 'uuid|exists:categories,id',
            'classified_category_selection_mode' => 'nullable|in:all,include,exclude',
            'classified_category_ids' => 'nullable|array',
            'classified_category_ids.*' => 'uuid|exists:classified_categories,id',
        ]);

        try {
            if (! empty($data['admin_listing_id'])) {
                $owner = CampaignOwner::platform();
                $source = CampaignSource::adminListing($data['admin_listing_id']);
            } else {
                $vendor = Vendor::findOrFail($data['vendor_id']);
                $owner = CampaignOwner::vendor($vendor);
                $source = CampaignSource::vendorListing($data['vendor_listing_id']);
            }

            $data['product_category_selection_mode'] = $data['product_category_selection_mode'] ?? 'all';
            $data['classified_category_selection_mode'] = $data['classified_category_selection_mode'] ?? 'all';

            $campaign = $this->service->createCampaign($owner, $source, $data);

            $this->syncCategoryRuleRows($campaign, 'category_id', $data['product_category_ids'] ?? [], $data['product_category_selection_mode']);
            $this->syncCategoryRuleRows($campaign, 'classified_category_id', $data['classified_category_ids'] ?? [], $data['classified_category_selection_mode']);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.marketer-campaigns.show', $campaign)
            ->with('success', 'تم إنشاء الحملة بنجاح.');
    }

    /**
     * Replace a campaign's include/exclude category rules for one category
     * source (product|classified) and update its selection mode. Used both
     * at creation time and from the campaign show page.
     */
    public function syncCategoryRules(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.create'), 403);

        $validated = $request->validate([
            'category_type' => 'required|in:product,classified',
            'selection_mode' => 'required|in:all,include,exclude',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'uuid',
        ]);

        $column = $validated['category_type'] === 'product' ? 'category_id' : 'classified_category_id';

        $marketerCampaign->update([
            ($validated['category_type'] === 'product' ? 'product_category_selection_mode' : 'classified_category_selection_mode') => $validated['selection_mode'],
        ]);

        $this->syncCategoryRuleRows($marketerCampaign, $column, $validated['category_ids'] ?? [], $validated['selection_mode']);

        return back()->with('success', 'تم تحديث نطاق الأقسام لهذه الحملة.');
    }

    /**
     * @param  'category_id'|'classified_category_id'  $column
     * @param  array<string>  $categoryIds
     */
    private function syncCategoryRuleRows(MarketerCampaign $campaign, string $column, array $categoryIds, string $mode): void
    {
        $campaign->categoryRules()->whereNotNull($column)->delete();

        if ($mode === 'all' || empty($categoryIds)) {
            return;
        }

        foreach (array_filter($categoryIds) as $categoryId) {
            MarketerCampaignCategoryRule::create([
                'marketer_campaign_id' => $campaign->id,
                $column => $categoryId,
                'mode' => $mode,
            ]);
        }
    }

    public function updateCommission(Request $request, MarketerCampaign $marketerCampaign): JsonResponse
    {
        $request->validate([
            'marketer_commission_amount' => ['required', 'integer', 'min:1'],
            'platform_commission_amount' => ['required', 'integer', 'min:0'],
        ]);

        abort_unless($marketerCampaign->status === 'pending_admin', 422, 'Campaign is not pending admin review');

        $marketerCampaign->marketer_commission_amount = $request->marketer_commission_amount;
        $marketerCampaign->platform_commission_amount = $request->platform_commission_amount;
        $marketerCampaign->save();

        return response()->json(['success' => true]);
    }

    public function searchVendorListings(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.create'), 403);

        $request->validate([
            'vendor_id' => 'required|uuid|exists:vendors,id',
            'search' => 'nullable|string',
        ]);

        $listings = VendorListing::with('productVariant.product')
            ->where('vendor_id', $request->vendor_id)
            ->where('status', 'active')
            ->whereIn('fulfillment_model', (array) setting('marketer_campaign_allowed_fulfilment_models', ['fbn', 'fbm']))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('productVariant.product', fn ($q2) => $q2->where('name_en', 'like', "%{$request->search}%")
            )
            )
            ->limit(20)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'text' => ($l->productVariant?->product?->name_en ?? '—').' — '.$l->id,
            ]);

        return response()->json(['results' => $listings]);
    }

    public function financials(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.view'), 403);

        $countryId = $request->input('country_id');
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $query = MarketerCampaign::query()
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->whereBetween('created_at', [$dateFrom, $dateTo.' 23:59:59']);

        $summaryByCurrency = MarketerCampaignInvitation::query()
            ->whereHas('campaign', function ($q) use ($countryId, $dateFrom, $dateTo) {
                $q->when($countryId, fn ($q) => $q->where('country_id', $countryId))
                    ->whereBetween('created_at', [$dateFrom, $dateTo.' 23:59:59']);
            })
            ->where('status', 'accepted')
            ->whereIn('platform_fee_status', ['pending', 'paid'])
            ->select('platform_fee_currency as currency')
            ->selectRaw('COUNT(*) as total_influencers_accepted')
            ->selectRaw('SUM(platform_fee_amount) as total_fees_expected')
            ->selectRaw('SUM(CASE WHEN platform_fee_status = "paid" THEN platform_fee_amount ELSE 0 END) as collected_fees')
            ->selectRaw('SUM(CASE WHEN platform_fee_status = "pending" THEN platform_fee_amount ELSE 0 END) as pending_fees')
            ->groupBy('platform_fee_currency')
            ->get();

        $campaigns = $query->clone()
            ->with(['vendor', 'country', 'invitations.marketer'])
            ->withCount('conversions')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $countries = Country::orderBy('name_en')->get();

        return view('admin.marketer_campaigns.financials', compact(
            'summaryByCurrency', 'campaigns', 'countries', 'dateFrom', 'dateTo'
        ));
    }

    public function approveRequest(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.approve'), 403);

        $request->validate([
            'marketer_commission_amount' => ['required', 'integer', 'min:1'],
            'platform_commission_amount' => ['required', 'integer', 'min:0'],
        ]);

        abort_unless($marketerCampaign->status === 'marketer_requested', 422,
            'هذه الحملة لا يمكن قبول طلبها في حالتها الحالية.');

        $this->service->approveMarketerRequest(
            $marketerCampaign,
            $request->only(['marketer_commission_amount', 'platform_commission_amount'])
        );

        return redirect()
            ->route('admin.marketer-campaigns.index')
            ->with('success', 'تم قبول طلب الماركتر وأصبحت الحملة نشطة.');
    }
}
