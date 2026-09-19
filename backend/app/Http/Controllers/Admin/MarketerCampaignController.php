<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketerCampaign;
use App\Services\MarketerCampaignService;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

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
            'invitations.marketer',
        ])
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->when($request->country_id, fn($q) => $q->where('country_id', $request->country_id))
        ->latest()
        ->paginate(20);

        $pendingCount = MarketerCampaign::where('status', 'pending_admin')->count();
        $countries = \App\Models\Country::orderBy('name_en')->get();

        return view('admin.marketer_campaigns.index', compact('campaigns', 'pendingCount', 'countries'));
    }

    public function show(MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.view'), 403);
        $marketerCampaign->load([
            'vendor', 'country',
            'vendorListing.productVariant.product',
            'adminListing.productVariant.product',
            'invitations.marketer.marketerProfile',
            'tieredRules',
            'conversions.order',
            'samples.invitation.marketer',
            'samples.customAttributeValues',
        ]);

        $categoryId = $marketerCampaign->vendorListing?->productVariant?->product?->category_id
            ?? $marketerCampaign->adminListing?->productVariant?->product?->category_id;

        $commissionSetting = $categoryId
            ? \App\Models\MarketerCommissionCountrySetting::where('category_id', $categoryId)
                ->where('country_id', $marketerCampaign->country_id)
                ->first()
            : null;

        return view('admin.marketer_campaigns.show', compact('marketerCampaign', 'commissionSetting'));
    }

    public function approve(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.approve'), 403);

        abort_unless($marketerCampaign->status === 'pending_admin', 422,
            'هذه الحملة لا يمكن قبولها في حالتها الحالية.');

        $this->service->approveCampaign($marketerCampaign, auth()->guard('admin')->user());

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
        \App\Models\MarketerCampaignSample $sample
    ) {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.approve'), 403);

        abort_unless($sample->campaign_id === $marketerCampaign->id, 403);

        $request->validate([
            'status' => 'required|in:pending,dispatched,delivered,returned',
        ]);

        $allowed = ['pending' => ['dispatched'], 'dispatched' => ['delivered'], 'delivered' => ['returned'], 'returned' => []];
        abort_unless(in_array($request->status, $allowed[$sample->status] ?? [], true), 422, 'انتقال حالة العينة غير مسموح.');

        $data = ['status' => $request->status];

        if ($request->status === 'dispatched' && !$sample->dispatched_at) {
            $data['dispatched_at'] = now();
        }
        if ($request->status === 'delivered' && !$sample->delivered_at) {
            $data['delivered_at'] = now();
        }

        $sample->update($data);

        return back()->with('success', 'تم تحديث حالة العينة.');
    }

    public function markInvitationFeePaid(
        MarketerCampaign $marketerCampaign,
        \App\Models\MarketerCampaignInvitation $invitation
    ) {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.approve'), 403);

        abort_unless($invitation->campaign_id === $marketerCampaign->id, 403);

        abort_unless($invitation->platform_fee_status === 'pending', 422,
            'الرسوم مدفوعة بالفعل أو غير مطبقة.');

        $invitation->update([
            'platform_fee_status' => 'paid',
        ]);

        return back()->with('success', 'تم تسجيل دفع رسوم الإنفلوينسر.');
    }

    public function inviteMarketers(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.create'), 403);

        $request->validate([
            'marketer_ids'   => ['required', 'array', 'min:1'],
            'marketer_ids.*' => ['uuid', 'distinct', 'exists:marketers,id'],
        ]);

        $result = $this->service->inviteMarketers($marketerCampaign, $request->marketer_ids);

        $message = count($result['invited']) . ' marketer(s) invited.';
        if (!empty($result['skipped'])) {
            $message .= ' ' . count($result['skipped']) . ' skipped (already invited or inactive).';
        }

        return back()->with('success', $message);
    }

    public function create()
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.create'), 403);

        $vendors   = \App\Models\Vendor::orderBy('store_name')->get();
        $marketers = \App\Models\Marketer::where('global_status', 'active')->orderBy('name')->get();
        $countries = \App\Models\Country::orderBy('name_en')->get();
        $vendorCountries = $vendors->mapWithKeys(fn ($v) => [$v->id => $v->country_id]);
        $countryCurrencies = \App\Models\Country::pluck('currency_code', 'id');

        $oldVendorListing = null;
        if (old('vendor_listing_id')) {
            $oldVendorListing = \App\Models\VendorListing::with('productVariant.product')
                ->find(old('vendor_listing_id'));
        }

        return view('admin.marketer_campaigns.create', compact(
            'vendors', 'marketers', 'countries', 'vendorCountries', 'countryCurrencies', 'oldVendorListing'
        ));
    }

    public function store(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.create'), 403);

        $data = $request->validate([
            // enhancement.md P-14 task 2: admin can now create a platform
            // (admin-listing) campaign without choosing a vendor —
            // vendor_id is only required when admin_listing_id is absent.
            'vendor_id'              => 'required_without:admin_listing_id|nullable|uuid|exists:vendors,id',
            'vendor_listing_id'      => 'nullable|uuid|exists:vendor_listings,id',
            'admin_listing_id'       => 'nullable|uuid|exists:admin_listings,id',
            'marketer_ids'           => 'required|array|min:1',
            'marketer_ids.*'         => 'uuid|exists:marketers,id',
            'country_id'             => 'required|uuid|exists:countries,id',
            'currency'               => 'required|string|size:3|exists:currencies,code',
            'commission_type'        => 'required|in:fixed,percentage,last_click,tiered',
            'max_commission_budget'  => 'required|integer|min:0',
            'title'                  => 'nullable|string|max:255',
            'notes'                  => 'nullable|string|max:1000',
        ]);

        try {
            if (!empty($data['admin_listing_id'])) {
                $owner  = CampaignOwner::platform();
                $source = CampaignSource::adminListing($data['admin_listing_id']);
            } else {
                $vendor = \App\Models\Vendor::findOrFail($data['vendor_id']);
                $owner  = CampaignOwner::vendor($vendor);
                $source = CampaignSource::vendorListing($data['vendor_listing_id']);
            }

            $campaign = $this->service->createCampaign($owner, $source, $data);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.marketer-campaigns.show', $campaign)
            ->with('success', 'تم إنشاء الحملة بنجاح.');
    }

    public function searchVendorListings(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.create'), 403);

        $request->validate([
            'vendor_id' => 'required|uuid|exists:vendors,id',
            'search'    => 'nullable|string',
        ]);

        $listings = \App\Models\VendorListing::with('productVariant.product')
            ->where('vendor_id', $request->vendor_id)
            ->where('status', 'active')
            ->whereIn('fulfillment_model', (array) setting('marketer_campaign_allowed_fulfilment_models', ['fbn', 'fbm']))
            ->when($request->filled('search'), fn ($q) =>
                $q->whereHas('productVariant.product', fn ($q2) =>
                    $q2->where('name_en', 'like', "%{$request->search}%")
                )
            )
            ->limit(20)
            ->get()
            ->map(fn ($l) => [
                'id'   => $l->id,
                'text' => ($l->productVariant?->product?->name_en ?? '—') . ' — ' . $l->id,
            ]);

        return response()->json(['results' => $listings]);
    }

    public function financials(Request $request)
    {
        abort_unless(auth('admin')->user()->can('marketer_campaigns.view'), 403);

        $countryId = $request->input('country_id');
        $dateFrom  = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo    = $request->input('date_to', now()->toDateString());

        $query = MarketerCampaign::query()
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);

        $summaryByCurrency = \App\Models\MarketerCampaignInvitation::query()
            ->whereHas('campaign', function ($q) use ($countryId, $dateFrom, $dateTo) {
                $q->when($countryId, fn ($q) => $q->where('country_id', $countryId))
                  ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59']);
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

        $countries = \App\Models\Country::orderBy('name_en')->get();

        return view('admin.marketer_campaigns.financials', compact(
            'summaryByCurrency', 'campaigns', 'countries', 'dateFrom', 'dateTo'
        ));
    }
}
