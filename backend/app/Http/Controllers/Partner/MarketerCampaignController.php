<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignSample;
use App\Models\ProductCustomAttribute;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Services\MarketerCampaignService;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketerCampaignController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly MarketerCampaignService $marketerCampaignService) {}

    private function vendorId(): string
    {
        return Auth::guard('vendor')->user()->vendor_id;
    }

    private function vendor(): Vendor
    {
        return Auth::guard('vendor')->user()->vendor;
    }

    /**
     * Marketers already tied to another non-terminal campaign of the same
     * listing: either a pending/accepted invitation, or (for campaigns still
     * awaiting review, which have no invitations yet) a requested marketer.
     *
     * @return array<string, string> marketer id => name
     */
    private function conflictingMarketers(string $vendorListingId, array $marketerIds, ?string $exceptCampaignId = null): array
    {
        if (empty($marketerIds)) {
            return [];
        }

        $campaigns = MarketerCampaign::where('vendor_listing_id', $vendorListingId)
            ->whereNotIn('status', MarketerCampaignService::TERMINAL_STATUSES)
            ->when($exceptCampaignId, fn ($q) => $q->where('id', '!=', $exceptCampaignId))
            ->with(['invitations' => fn ($q) => $q->whereIn('status', ['pending', 'accepted'])])
            ->get();

        $taken = [];
        foreach ($campaigns as $campaign) {
            foreach ($campaign->invitations as $invitation) {
                $taken[$invitation->marketer_id] = true;
            }
            if (in_array($campaign->status, ['pending_admin', 'marketer_requested'], true)) {
                foreach ((array) $campaign->requested_marketer_vendor_ids as $id) {
                    $taken[$id] = true;
                }
            }
        }

        $conflictIds = array_values(array_intersect($marketerIds, array_keys($taken)));

        return $conflictIds
            ? Marketer::whereIn('id', $conflictIds)->pluck('name', 'id')->all()
            : [];
    }

    private function conflictMessage(array $conflicts): string
    {
        return 'الماركتر التالي لديه دعوة قائمة في حملة أخرى لنفس القائمة: '.implode('، ', $conflicts);
    }

    private function authorizeCampaign(MarketerCampaign $campaign, string $permission): void
    {
        abort_unless(auth('vendor')->user()?->hasPermissionTo($permission), 403);
        abort_unless($campaign->vendor_id === $this->vendorId(), 403);
    }

    public function create(VendorListing $vendorListing)
    {
        abort_unless($vendorListing->vendor_id === $this->vendorId(), 403);
        $allowedModels = (array) setting('marketer_campaign_allowed_fulfilment_models', ['fbn', 'fbm']);
        abort_unless(in_array($vendorListing->fulfillment_model?->value, $allowedModels, true), 403, 'حملات الماركتر غير متاحة لهذا نوع التخزين.');

        $vendorListing->load('productVariant.product');

        $marketerVendors = Marketer::where('global_status', 'active')
            ->where('country_id', $vendorListing->country_id)
            ->orderBy('name')
            ->with(['marketerJobs', 'marketerProfile'])
            ->get(['id', 'name']);

        return view('partner.marketer_campaigns.create', compact('vendorListing', 'marketerVendors'));
    }

    public function store(Request $request)
    {
        $vendor = $this->vendor();

        $request->validate([
            'vendor_listing_id' => ['required', 'uuid', 'exists:vendor_listings,id'],
            'commission_type' => ['required', 'in:fixed,tiered,last_click'],
            'max_commission_budget' => ['nullable', 'numeric', 'min:0'],
            'marketer_ids' => ['required', 'array', 'min:1'],
            'marketer_ids.*' => ['uuid', 'distinct', 'exists:marketers,id'],
            'tiered_rules' => ['nullable', 'array'],
            'selected_ad_types' => ['nullable', 'array'],
            'selected_ad_types.*' => ['string', 'in:story,post,video'],
            'vendor_ad_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $listing = VendorListing::where('id', $request->vendor_listing_id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $allowedModels = (array) setting('marketer_campaign_allowed_fulfilment_models', ['fbn', 'fbm']);
        abort_unless(in_array($listing->fulfillment_model?->value, $allowedModels, true), 403, 'حملات الماركتر غير متاحة لهذا نوع التخزين.');

        $conflicts = $this->conflictingMarketers($listing->id, $request->input('marketer_ids', []));
        if ($conflicts) {
            return back()->withInput()->withErrors(['marketer_ids' => $this->conflictMessage($conflicts)]);
        }

        try {
            $this->marketerCampaignService->createCampaign(
                CampaignOwner::vendor($vendor),
                CampaignSource::vendorListing($listing->id),
                array_merge(
                    $request->only(['commission_type', 'max_commission_budget']),
                    [
                        'country_id' => $listing->country_id,
                        'currency' => $listing->currency,
                        'marketer_ids' => $request->input('marketer_ids', []),
                        'tiered_rules' => $request->input('tiered_rules', []),
                        'selected_ad_types' => $request->input('selected_ad_types') ?: null,
                        'vendor_ad_notes' => $request->input('vendor_ad_notes') ?: null,
                    ]
                )
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'تعذر إنشاء حملة الماركتر: '.$e->getMessage());
        }

        return redirect()
            ->to(route('partner.listings.show', $listing->id).'#marketer_campaign')
            ->with('success', 'تم إنشاء حملة الماركتر بنجاح.');
    }

    public function index(Request $request)
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.view'),
            403
        );

        $campaigns = MarketerCampaign::where('vendor_id', $this->vendorId())
            ->when($request->filled('listing'), fn ($q) => $q->where('vendor_listing_id', $request->input('listing')))
            ->with([
                'country',
                'vendorListing.productVariant.product',
                'adminListing.productVariant.product',
                'invitations.marketer',
                'tieredRules',
            ])
            ->withCount('invitations')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('partner.marketer_campaigns.index', compact('campaigns'));
    }

    public function show(MarketerCampaign $marketerCampaign)
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.view'),
            403
        );
        abort_unless($marketerCampaign->vendor_id === $this->vendorId(), 403);

        $marketerCampaign->load([
            'country',
            'vendorListing.productVariant.product',
            'adminListing.productVariant.product',
            'invitations.marketer.marketerProfile',
            'tieredRules',
            'conversions.order',
            'samples.invitation.marketer',
            'samples.customAttributeValues',
        ]);

        return view('partner.marketer_campaigns.show', compact('marketerCampaign'));
    }

    public function edit(MarketerCampaign $marketerCampaign)
    {
        $this->authorizeCampaign($marketerCampaign, 'marketer_campaigns.edit');
        abort_unless(in_array($marketerCampaign->status, ['pending_admin', 'marketer_requested'], true), 403, 'لا يمكن تعديل الحملة بعد مراجعتها.');

        $marketerCampaign->load(['vendorListing.productVariant.product', 'invitations.marketer', 'tieredRules']);

        return view('partner.marketer_campaigns.edit', compact('marketerCampaign'));
    }

    public function update(Request $request, MarketerCampaign $marketerCampaign)
    {
        $this->authorizeCampaign($marketerCampaign, 'marketer_campaigns.edit');
        abort_unless(in_array($marketerCampaign->status, ['pending_admin', 'marketer_requested'], true), 403, 'لا يمكن تعديل الحملة بعد مراجعتها.');

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'commission_type' => ['required', 'in:fixed,tiered,last_click'],
            'max_commission_budget' => ['nullable', 'numeric', 'min:0'],
            'tiered_rules' => ['nullable', 'array'],
            'tiered_rules.*.from_sale_number' => ['required_with:tiered_rules', 'integer', 'min:1'],
            'tiered_rules.*.commission_amount' => ['required_with:tiered_rules', 'numeric', 'min:0'],
            'selected_ad_types' => ['nullable', 'array'],
            'selected_ad_types.*' => ['string', 'in:story,post,video'],
            'vendor_ad_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['commission_type'] === 'tiered' && empty($data['tiered_rules'])) {
            return back()->withInput()->withErrors(['tiered_rules' => 'يجب إضافة شريحة واحدة على الأقل للحملة المتدرجة.']);
        }

        try {
            $this->marketerCampaignService->updateCampaign($marketerCampaign, $data);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'تعذر تحديث الحملة: '.$e->getMessage());
        }

        return redirect()
            ->route('partner.marketer-campaigns.show', $marketerCampaign)
            ->with('success', 'تم تحديث الحملة بنجاح.');
    }

    public function destroy(MarketerCampaign $marketerCampaign)
    {
        $this->authorizeCampaign($marketerCampaign, 'marketer_campaigns.delete');
        abort_unless(
            in_array($marketerCampaign->status, ['cancelled', 'rejected', 'done', 'completed', 'pending_admin'], true),
            422,
            'لا يمكن حذف حملة نشطة. قم بإلغائها أولاً.'
        );

        $listingId = $marketerCampaign->vendor_listing_id;

        $this->marketerCampaignService->deleteCampaign($marketerCampaign);

        return ($listingId
            ? redirect()->to(route('partner.listings.show', $listingId).'#marketer_campaign')
            : redirect()->route('partner.marketer-campaigns.index'))
            ->with('success', 'تم حذف الحملة.');
    }

    public function saveSampleCustomAttributes(
        Request $request,
        MarketerCampaign $marketerCampaign,
        MarketerCampaignSample $sample
    ) {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.create'),
            403
        );
        abort_unless($marketerCampaign->vendor_id === $this->vendorId(), 403);
        abort_unless($sample->campaign_id === $marketerCampaign->id, 403);

        $product = $sample->product();
        abort_unless($product?->has_custom_attributes, 422, 'This product has no custom attributes enabled.');

        $validated = $request->validate([
            'values' => ['required', 'array'],
            'values.*.product_custom_attribute_id' => ['required', 'uuid', 'exists:product_custom_attributes,id'],
            'values.*.value' => ['required', 'string'],
        ]);

        $requiredIds = $product->customAttributes()->where('is_required', true)->pluck('id');
        $providedIds = collect($validated['values'])->pluck('product_custom_attribute_id');
        abort_if($requiredIds->diff($providedIds)->isNotEmpty(), 422, 'Missing required attribute values.');

        // Replace any prior submission for this sample (vendor can correct before dispatch)
        $sample->customAttributeValues()->delete();

        foreach ($validated['values'] as $val) {
            $attribute = ProductCustomAttribute::find($val['product_custom_attribute_id']);
            $sample->customAttributeValues()->create([
                'product_custom_attribute_id' => $attribute->id,
                'label' => $attribute->label,
                'unit' => $attribute->unit,
                'value' => $val['value'],
            ]);
        }

        return back()->with('success', __('partner.marketer_campaigns_my.sample_custom_details_saved'));
    }

    public function inviteMarketers(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.create'),
            403
        );
        abort_unless($marketerCampaign->vendor_id === $this->vendorId(), 403);

        $request->validate([
            'marketer_ids' => ['required', 'array', 'min:1'],
            'marketer_ids.*' => ['uuid', 'distinct', 'exists:marketers,id'],
        ]);

        if ($marketerCampaign->vendor_listing_id) {
            $conflicts = $this->conflictingMarketers(
                $marketerCampaign->vendor_listing_id,
                $request->input('marketer_ids', []),
                $marketerCampaign->id
            );
            if ($conflicts) {
                return back()->withErrors(['marketer_ids' => $this->conflictMessage($conflicts)]);
            }
        }

        $result = $this->marketerCampaignService->inviteMarketers(
            $marketerCampaign, $request->input('marketer_ids', [])
        );

        $message = count($result['invited']).' ماركتر تمت دعوته.';
        if (! empty($result['skipped'])) {
            $message .= ' تم تجاهل '.count($result['skipped']).' (مدعو بالفعل أو غير نشط).';
        }

        return back()->with('success', $message);
    }

    public function cancelActive(Request $request, MarketerCampaign $marketerCampaign)
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.cancel'),
            403
        );
        abort_unless($marketerCampaign->vendor_id === $this->vendorId(), 403);
        abort_unless(in_array($marketerCampaign->status, ['active', 'auto_approved', 'paused'], true), 422, 'Campaign cannot be cancelled in its current status');

        $request->validate([
            'reason' => ['required', 'string', 'min:10'],
        ]);

        $this->marketerCampaignService->cancelCampaign($marketerCampaign);

        return back()->with('success', __('partner.marketer_campaigns_my.cancel_success'));
    }

    public function cancel(MarketerCampaign $marketerCampaign)
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.cancel'),
            403
        );
        abort_unless($marketerCampaign->vendor_id === $this->vendorId(), 403);
        abort_unless($marketerCampaign->status === 'pending_admin', 403);

        $this->marketerCampaignService->cancelCampaign($marketerCampaign);

        return back()->with('success', __('partner.marketer_campaigns_my.cancel_success'));
    }

    public function searchMarketers(Request $request): JsonResponse
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.view'),
            403
        );

        $vendor = Auth::guard('vendor')->user()->vendor;
        $countryId = $request->input('country_id', $vendor->country_id);
        $search = $request->input('q', '');
        $type = $request->input('type'); // 'influencer' | 'affiliate' | null = all

        $marketers = Marketer::where('global_status', 'active')
            ->where('country_id', $countryId)
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->when($type, fn ($q) => $q->whereHas('marketerJobs', fn ($j) => $j->where('key', $type)))
            ->with('marketerJobs')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json($marketers->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'marketer_type' => $m->marketerJobs->first()?->key,
            'type_label' => $m->isInfluencer() ? 'مؤثر' : 'أفيليت',
        ]));
    }

    public function approveRequest(Request $request, MarketerCampaign $campaign)
    {
        $request->validate([
            'marketer_commission_amount' => ['required', 'integer', 'min:1'],
            'platform_commission_amount' => ['required', 'integer', 'min:0'],
        ]);

        abort_unless($campaign->status === 'marketer_requested', 422);
        abort_unless($campaign->vendor_id === $this->vendor()->id, 403);

        $this->marketerCampaignService->approveMarketerRequest(
            $campaign,
            $request->only(['marketer_commission_amount', 'platform_commission_amount'])
        );

        return back()->with('success', 'تم قبول طلب الماركتر وأصبحت الحملة نشطة.');
    }
}
