<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedListing;
use App\Models\Marketer;
use App\Models\MarketerCampaign;
use App\Models\MarketerCampaignSample;
use App\Models\ProductCustomAttribute;
use App\Models\Vendor;
use App\Models\VendorListing;
use App\Services\Campaign\CampaignSampleService;
use App\Services\Inventory\InventoryService;
use App\Services\MarketerCampaignQuoteService;
use App\Services\Marketer\MarketerCommissionCalculator;
use App\Services\MarketerCampaignService;
use App\Services\MarketingFeeReportService;
use App\Support\Marketer\CampaignOwner;
use App\Support\Marketer\CampaignSubject;
use App\Enums\MarketerType;
use App\Enums\CommissionVertical;
use App\Support\Money;
use Carbon\Carbon;
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
    private function conflictingMarketers(string $itemColumn, string $itemId, array $marketerIds, ?string $exceptCampaignId = null): array
    {
        if (empty($marketerIds)) {
            return [];
        }

        $campaigns = MarketerCampaign::where($itemColumn, $itemId)
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

    /** Column of marketer_campaigns holding the promoted item for a subject. */
    private function itemColumn(CampaignSubject $subject): string
    {
        return match ($subject->vertical) {
            CommissionVertical::Classified => 'classified_listing_id',
            CommissionVertical::Travel => 'travel_package_id',
            default => 'vendor_listing_id',
        };
    }

    /**
     * The item a campaign request is about, resolved from the vendor's own listings only: a product listing
     * (product vendors) or a classified listing (classified vendors).
     */
    private function ownedSubject(?string $productListingId, ?string $classifiedListingId): CampaignSubject
    {
        if ($classifiedListingId) {
            $listing = ClassifiedListing::forVendors()
                ->where('seller_id', $this->vendorId())
                ->with('images')
                ->findOrFail($classifiedListingId);

            return CampaignSubject::of($listing);
        }

        $listing = VendorListing::where('id', $productListingId)
            ->where('vendor_id', $this->vendorId())
            ->with('productVariant.product.category')
            ->firstOrFail();

        return CampaignSubject::of($listing);
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
        $allowedModels = (array) setting('marketer_campaign_allowed_fulfilment_models', MarketerCampaignService::DEFAULT_ALLOWED_FULFILMENT_MODELS);
        abort_unless(in_array($vendorListing->fulfillment_model?->value, $allowedModels, true), 403, __('partner.marketer_campaigns.fulfilment_not_allowed'));

        $vendorListing->load('productVariant.product.category');

        $quoteService = app(MarketerCampaignQuoteService::class);
        // Country-filtered influencers, each with the fixed amount he would be paid for this listing's category.
        $influencers = $quoteService->influencerOptions($vendorListing);
        $affiliateCount = $quoteService->openAudienceCount($vendorListing);
        // Seller wallet (same wallet the Wallet page and fee collection use) is part of the quote.
        $quote = $quoteService->withWallet($quoteService->quote($vendorListing, []), $this->vendorId(), $vendorListing->currency);
        $walletBalance = $quote['wallet']['balance'];
        $walletCurrency = $quote['wallet']['currency'];

        // Same rule MarketerCampaignService enforces on submit, surfaced here.
        $minStock = (int) ($vendorListing->productVariant?->product?->category?->min_stock_for_campaign ?? 10);
        $availableStock = app(InventoryService::class)->availableStock($vendorListing);
        // Stock that stays sellable once the fixed admin sample (and samples already
        // promised to this listing's other campaigns) are set aside.
        $sampleService = app(CampaignSampleService::class);
        $stockAfterFixedSample = $availableStock
            - $sampleService->pendingReservedQty($vendorListing)
            - $sampleService->adminQty($vendorListing->productVariant?->product?->category);

        return view('partner.marketer_campaigns.create', compact('vendorListing', 'influencers', 'affiliateCount', 'walletBalance', 'walletCurrency', 'quote', 'minStock', 'availableStock', 'stockAfterFixedSample'));
    }

    public function createClassified(ClassifiedListing $classifiedListing)
    {
        abort_unless((string) $classifiedListing->seller_id === (string) $this->vendorId() && $classifiedListing->is_vendor_listing, 403);
        abort_unless($classifiedListing->status === \App\Enums\ClassifiedListingStatus::Active && ! $classifiedListing->isExpired(), 403, __('marketer_campaigns.errors.classified_not_active'));

        $classifiedListing->load('images');
        $subject = CampaignSubject::of($classifiedListing);

        $quoteService = app(MarketerCampaignQuoteService::class);
        $influencers = $quoteService->influencerOptions($subject);
        $affiliateCount = $quoteService->openAudienceCount($subject);
        $quote = $quoteService->withWallet($quoteService->quote($subject, []), $this->vendorId(), $classifiedListing->currency);

        return view('partner.marketer_campaigns.create_classified', compact('classifiedListing', 'subject', 'influencers', 'affiliateCount', 'quote'));
    }

    /** Live cost summary of the wizard: everything is computed here, the page does no money math. */
    public function quote(Request $request): JsonResponse
    {
        abort_unless(auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.create'), 403);

        $request->validate([
            'listing_id' => ['nullable', 'required_without:classified_id', 'uuid'],
            'classified_id' => ['nullable', 'required_without:listing_id', 'uuid'],
            'marketer_ids' => ['nullable', 'array'],
            'marketer_ids.*' => ['uuid'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'open_to_affiliates' => ['nullable', 'boolean'],
        ]);

        $subject = $this->ownedSubject($request->input('listing_id'), $request->input('classified_id'));

        $quoteService = app(MarketerCampaignQuoteService::class);

        return response()->json($quoteService->withWallet(
            $quoteService->quote(
                $subject,
                array_values(array_filter((array) $request->input('marketer_ids', []), 'is_string')),
                (float) $request->input('budget', 0),
                $request->boolean('open_to_affiliates'),
            ),
            $this->vendorId(),
            $subject->currency,
        ));
    }

    public function store(Request $request)
    {
        $vendor = $this->vendor();

        // Commission is NOT vendor-typed: influencers get a fixed amount (shown in the wizard), affiliates and brokers
        // earn per sale by their own rule. The vendor sets the Max Commission Budget that pays all of them.
        $request->validate([
            'vendor_listing_id' => ['nullable', 'required_without:classified_listing_id', 'uuid', 'exists:vendor_listings,id'],
            'classified_listing_id' => ['nullable', 'required_without:vendor_listing_id', 'uuid', 'exists:classified_listings,id'],
            'max_commission_budget' => ['required', 'numeric', 'min:0.01', 'max:999999999', 'decimal:0,2'],
            'ends_at' => ['nullable', 'date', 'after:today'],
            'marketer_ids' => ['nullable', 'array'],
            'marketer_ids.*' => ['uuid', 'distinct', 'exists:marketers,id'],
            'open_to_affiliates' => ['nullable', 'boolean'],
            'selected_ad_types' => ['nullable', 'array'],
            'selected_ad_types.*' => ['string', 'in:story,post,video'],
            'vendor_ad_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $subject = $this->ownedSubject($request->input('vendor_listing_id'), $request->input('classified_listing_id'));
        $listing = $subject->model;
        $itemColumn = $this->itemColumn($subject);

        if ($subject->isProduct()) {
            $allowedModels = (array) setting('marketer_campaign_allowed_fulfilment_models', MarketerCampaignService::DEFAULT_ALLOWED_FULFILMENT_MODELS);
            abort_unless(in_array($listing->fulfillment_model?->value, $allowedModels, true), 403, __('partner.marketer_campaigns.fulfilment_not_allowed'));
        }

        $quoteService = app(MarketerCampaignQuoteService::class);
        $eligible = $quoteService->eligibleMarketers($subject)->keyBy('id');
        $influencerOptions = $quoteService->influencerOptions($subject)->keyBy('id');

        // Picked marketers must be eligible influencers of the listing's country.
        $picked = array_values(array_unique((array) $request->input('marketer_ids', [])));
        if (array_diff($picked, $influencerOptions->keys()->all())) {
            return back()->withInput()->withErrors(['marketer_ids' => __('partner.marketer_campaigns.marketer_not_eligible')]);
        }

        $conflicts = $this->conflictingMarketers($itemColumn, $listing->id, $picked);
        if ($conflicts) {
            return back()->withInput()->withErrors(['marketer_ids' => $this->conflictMessage($conflicts)]);
        }

        // "Open to all" reaches every eligible affiliate / broker of the country (never an influencer slot).
        $marketerIds = $picked;
        if ($request->boolean('open_to_affiliates')) {
            $openIds = $eligible
                ->filter(fn ($m) => array_intersect([MarketerType::Affiliate, MarketerType::Broker], MarketerType::heldBy($m)) !== []
                    && ! in_array($m->id, $picked, true))
                ->keys()->all();
            $openIds = array_values(array_diff($openIds, array_keys($this->conflictingMarketers($itemColumn, $listing->id, $openIds))));
            $marketerIds = array_values(array_unique(array_merge($marketerIds, $openIds)));
        }

        if (empty($marketerIds)) {
            return back()->withInput()->withErrors(['marketer_ids' => __('partner.marketer_campaigns.select_at_least_one')]);
        }

        $budget = round((float) $request->input('max_commission_budget'), 2);
        $quote = $quoteService->quote($subject, $picked, $budget, $request->boolean('open_to_affiliates'));
        if (! $quote['fits_budget']) {
            return back()->withInput()->withErrors(['max_commission_budget' => __('marketer_campaigns.errors.fixed_exceeds_budget', [
                'fixed' => Money::format($quote['influencer_fixed_total']), 'budget' => Money::format($budget),
            ])]);
        }

        try {
            $this->marketerCampaignService->createCampaign(
                CampaignOwner::vendor($vendor),
                $subject->source(),
                [
                    'max_commission_budget' => $budget,
                    'country_id' => $subject->countryId,
                    'currency' => $subject->currency,
                    'marketer_ids' => $marketerIds,
                    'influencer_ids' => $picked,
                    'open_to_affiliates' => $request->boolean('open_to_affiliates'),
                    'ends_at' => $request->filled('ends_at') ? Carbon::parse($request->input('ends_at'))->endOfDay() : null,
                    'selected_ad_types' => $request->input('selected_ad_types') ?: null,
                    'vendor_ad_notes' => $request->input('vendor_ad_notes') ?: null,
                ]
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', __('partner.marketer_campaigns.create_failed', ['error' => $e->getMessage()]));
        }

        return redirect()
            ->to($this->listingUrl($subject->isProduct() ? 'product' : 'classified', $listing->id))
            ->with('success', __('marketer_campaigns.wizard.created', [
                'total' => Money::format($quote['campaign_grand_total']), 'currency' => $quote['currency'],
            ]));
    }

    /** Where a vendor lands after acting on a campaign: the promoted item's own page, on its campaigns section. */
    private function listingUrl(string $category, string $listingId): string
    {
        return $category === 'classified'
            ? route('partner.classifieds.show', $listingId).'#marketer_campaign'
            : route('partner.listings.show', $listingId).'#marketer_campaign';
    }

    public function index(Request $request)
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_campaigns.view'),
            403
        );

        $campaigns = MarketerCampaign::where('vendor_id', $this->vendorId())
            ->when($request->filled('listing'), fn ($q) => $q->where('vendor_listing_id', $request->input('listing')))
            ->when($request->filled('classified'), fn ($q) => $q->where('classified_listing_id', $request->input('classified')))
            ->with([
                'country',
                'vendorListing.productVariant.product',
                'adminListing.productVariant.product',
                'classifiedListing',
                'invitations.marketer',
            ])
            ->withCount('invitations')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $marketingTotals = app(MarketingFeeReportService::class)->vendorTotals($this->vendorId());

        return view('partner.marketer_campaigns.index', compact('campaigns', 'marketingTotals'));
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
            'classifiedListing',
            'invitations.marketer.marketerProfile',
            'conversions.order',
            'samples.invitation.marketer',
            'samples.customAttributeValues',
        ]);

        // How each marketer is paid on this campaign, explained with the current price of the promoted item
        // as the worked example (read-only, resolved by the calculator from admin settings).
        $calculator = app(MarketerCommissionCalculator::class);
        $examplePrice = (float) ($marketerCampaign->getPromotedItem()?->price ?? 0);
        $calculations = $marketerCampaign->invitations
            ->filter(fn ($i) => $i->marketer && in_array($i->status, ['pending', 'accepted'], true))
            ->mapWithKeys(fn ($i) => [$i->id => $calculator->compute($i->marketer, $marketerCampaign, $examplePrice, 1, $i)]);

        return view('partner.marketer_campaigns.show', compact('marketerCampaign', 'calculations', 'examplePrice'));
    }

    public function edit(MarketerCampaign $marketerCampaign)
    {
        $this->authorizeCampaign($marketerCampaign, 'marketer_campaigns.edit');
        abort_unless(in_array($marketerCampaign->status, ['pending_admin', 'marketer_requested'], true), 403, __('partner.marketer_campaigns.cannot_edit_reviewed'));

        $marketerCampaign->load(['vendorListing.productVariant.product', 'classifiedListing', 'invitations.marketer']);

        return view('partner.marketer_campaigns.edit', compact('marketerCampaign'));
    }

    public function update(Request $request, MarketerCampaign $marketerCampaign)
    {
        $this->authorizeCampaign($marketerCampaign, 'marketer_campaigns.edit');
        abort_unless(in_array($marketerCampaign->status, ['pending_admin', 'marketer_requested'], true), 403, 'لا يمكن تعديل الحملة بعد مراجعتها.');

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'max_commission_budget' => ['nullable', 'numeric', 'min:0.01', 'max:999999999', 'decimal:0,2'],
            'ends_at' => ['nullable', 'date', 'after:today'],
            'selected_ad_types' => ['nullable', 'array'],
            'selected_ad_types.*' => ['string', 'in:story,post,video'],
            'vendor_ad_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['ends_at'] = $request->filled('ends_at') ? Carbon::parse($data['ends_at'])->endOfDay() : null;

        try {
            $this->marketerCampaignService->updateCampaign($marketerCampaign, $data);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', __('partner.marketer_campaigns.update_failed', ['error' => $e->getMessage()]));
        }

        return redirect()
            ->route('partner.marketer-campaigns.show', $marketerCampaign)
            ->with('success', __('partner.marketer_campaigns.update_success'));
    }

    public function destroy(MarketerCampaign $marketerCampaign)
    {
        $this->authorizeCampaign($marketerCampaign, 'marketer_campaigns.delete');
        abort_unless(
            in_array($marketerCampaign->status, ['cancelled', 'rejected', 'done', 'completed', 'pending_admin'], true),
            422,
            'لا يمكن حذف حملة نشطة. قم بإلغائها أولاً.'
        );

        $listingId = $marketerCampaign->classified_listing_id ?? $marketerCampaign->vendor_listing_id;
        $category = $marketerCampaign->campaign_category === 'classified' ? 'classified' : 'product';

        $this->marketerCampaignService->deleteCampaign($marketerCampaign);

        return ($listingId
            ? redirect()->to($this->listingUrl($category, $listingId))
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

        $itemId = $marketerCampaign->classified_listing_id ?? $marketerCampaign->vendor_listing_id;
        if ($itemId) {
            $conflicts = $this->conflictingMarketers(
                $marketerCampaign->classified_listing_id ? 'classified_listing_id' : 'vendor_listing_id',
                $itemId,
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
        $type = $request->input('type'); // 'influencer' | 'affiliate' | 'broker' | null = all

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
            'type_label' => $m->marketerJobs->first() ? \App\Enums\MarketerType::tryFrom($m->marketerJobs->first()->key)?->labelBilingual()['ar'] : null,
        ]));
    }

    public function approveRequest(Request $request, MarketerCampaign $campaign)
    {
        $request->validate([
            'max_commission_budget' => ['required', 'numeric', 'min:0.01', 'max:999999999', 'decimal:0,2'],
        ]);

        abort_unless($campaign->status === 'marketer_requested', 422);
        abort_unless($campaign->vendor_id === $this->vendor()->id, 403);

        try {
            $this->marketerCampaignService->approveMarketerRequest($campaign, $request->only(['max_commission_budget']));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('partner.marketer_campaigns.request_approved'));
    }
}
