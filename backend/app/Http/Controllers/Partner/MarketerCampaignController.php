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

    private function hasActiveCampaign(string $vendorListingId): bool
    {
        return MarketerCampaign::where('vendor_listing_id', $vendorListingId)
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed'])
            ->exists();
    }

    public function create(VendorListing $vendorListing)
    {
        abort_unless($vendorListing->vendor_id === $this->vendorId(), 403);
        $allowedModels = (array) setting('marketer_campaign_allowed_fulfilment_models', ['fbn', 'fbm']);
        abort_unless(in_array($vendorListing->fulfillment_model, $allowedModels, true), 403, 'حملات الماركتر غير متاحة لهذا نوع التخزين.');

        $existing = MarketerCampaign::where('vendor_listing_id', $vendorListing->id)
            ->whereNotIn('status', ['cancelled', 'rejected', 'completed'])
            ->first();

        if ($existing) {
            return redirect()
                ->route('partner.marketer-campaigns.show', $existing)
                ->with('info', 'هذه القائمة لديها حملة نشطة بالفعل. يمكنك دعوة المزيد من الماركترز من هنا.');
        }

        $vendorListing->load('productVariant.product');

        $marketerVendors = Marketer::where('global_status', 'active')
            ->where('country_id', $vendorListing->country_id)
            ->orderBy('name')
            ->with('marketerJobs')
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
        ]);

        $listing = VendorListing::where('id', $request->vendor_listing_id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $allowedModels = (array) setting('marketer_campaign_allowed_fulfilment_models', ['fbn', 'fbm']);
        abort_unless(in_array($listing->fulfillment_model, $allowedModels, true), 403, 'حملات الماركتر غير متاحة لهذا نوع التخزين.');
        abort_if($this->hasActiveCampaign($listing->id), 403, 'هذه القائمة لديها حملة نشطة أو قيد المراجعة بالفعل.');

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
                    ]
                )
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'تعذر إنشاء حملة الماركتر: '.$e->getMessage());
        }

        return redirect()
            ->route('partner.marketer-campaigns.index')
            ->with('success', 'تم إنشاء حملة الماركتر بنجاح.');
    }

    public function index()
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.view'),
            403
        );

        $campaigns = MarketerCampaign::where('vendor_id', $this->vendorId())
            ->with([
                'country',
                'vendorListing.productVariant.product',
                'adminListing.productVariant.product',
                'invitations.marketer',
                'tieredRules',
            ])
            ->withCount('invitations')
            ->latest()
            ->paginate(15);

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

        $result = $this->marketerCampaignService->inviteMarketers(
            $marketerCampaign, $request->input('marketer_ids', [])
        );

        $message = count($result['invited']).' ماركتر تمت دعوته.';
        if (! empty($result['skipped'])) {
            $message .= ' تم تجاهل '.count($result['skipped']).' (مدعو بالفعل أو غير نشط).';
        }

        return back()->with('success', $message);
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
}
