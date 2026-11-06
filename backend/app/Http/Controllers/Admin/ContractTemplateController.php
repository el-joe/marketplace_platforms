<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContractKind;
use App\Http\Controllers\Controller;
use App\Jobs\NotifyVendorsOfAgreementUpdateJob;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ClassifiedListing;
use App\Models\ContractCountryAssignment;
use App\Models\ContractDisplayDestination;
use App\Models\Country;
use App\Models\CustomerContract;
use App\Models\CustomerContractAssignment;
use App\Models\Marketer;
use App\Models\MarketerSellerContract;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorContract;
use App\Models\VendorListing;
use App\Notifications\Vendor\ContractTemplateUpdated;
use App\Services\ActivityLoggerService;
use App\Services\Marketer\MarketerCollaborationContractService;
use App\Services\Vendor\CategoryContractService;
use App\Services\Vendor\ClassifiedSectionContractService;
use App\Services\Vendor\ContractFileService;
use App\Services\Vendor\ContractResolver;
use App\Services\Vendor\CustomerContractAssignmentService;
use App\Services\Vendor\SellerAgreementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contract templates for classified categories, product categories and the platform-wide Seller Agreement.
 * A template is either written in the editor or uploaded as pdf/docx files, one per language.
 *
 * A template is a draft until published. Published content is frozen: editing it creates a new draft version,
 * and publishing that version moves the categories onto it and asks affected vendors to sign again.
 */
class ContractTemplateController extends Controller
{
    public function __construct(
        private readonly CategoryContractService $contracts,
        private readonly SellerAgreementService $agreements,
        private readonly ContractFileService $files,
        private readonly ContractResolver $resolver,
        private readonly ActivityLoggerService $activity,
        private readonly ClassifiedSectionContractService $sections,
        private readonly MarketerCollaborationContractService $collaboration,
        private readonly CustomerContractAssignmentService $customerAssignments,
    ) {}

    public function index(Request $request): View
    {
        $scope = $this->scopeFilter($request);
        $status = in_array($request->query('status'), ['draft', 'published', 'scheduled', 'retired'], true) ? $request->query('status') : null;
        $countryFilter = $this->countryFilter($request);
        $sectionFilter = $this->sectionFilter($request);
        $kindFilter = ContractKind::tryFrom((string) $request->query('kind'))?->value;
        $destinationFilter = ContractDisplayDestination::query()->where('key', (string) $request->query('destination'))->value('id');

        $templates = ClassifiedContractTemplate::query()
            ->when($kindFilter, fn (Builder $query) => $query->where('contract_kind', $kindFilter))
            ->when($destinationFilter, fn (Builder $query) => $query->whereHas('displayDestinations', fn (Builder $d) => $d->whereKey($destinationFilter)))
            ->when($sectionFilter, fn (Builder $query) => $this->limitToSection($query, $sectionFilter))
            ->when($scope, fn (Builder $query) => $query->where('category_scope', $scope))
            ->when($countryFilter === 'legacy', fn (Builder $query) => $query->whereNull('country_id'))
            ->when($countryFilter && $countryFilter !== 'legacy', fn (Builder $query) => $query->where('country_id', $countryFilter))
            ->when($status === 'draft', fn (Builder $query) => $query->where('is_published', false)->where('is_active', true))
            ->when($status === 'retired', fn (Builder $query) => $query->where('is_published', false)->where('is_active', false))
            ->when($status === 'published', fn (Builder $query) => $query->where('is_published', true)->where(fn (Builder $q) => $q->whereNull('effective_at')->orWhere('effective_at', '<=', now())))
            ->when($status === 'scheduled', fn (Builder $query) => $query->where('is_published', true)->where('effective_at', '>', now()))
            ->with(['category:id,name_en,name_ar', 'productCategory:id,name_en', 'country:id,name_en,name_ar,iso_code_2', 'displayDestinations'])
            ->withCount(['assignments', 'marketers'])
            ->orderBy('name')
            ->orderBy('country_id')
            ->orderByDesc('version')
            ->paginate(50)
            ->withQueryString();

        $assignedCounts = $this->assignedCounts();
        $templateIds = $templates->pluck('id');
        $signatureCounts = [];
        foreach ([VendorContract::class, MarketerSellerContract::class, CustomerContract::class] as $signedModel) {
            $signedModel::query()
                ->whereIn('contract_template_id', $templateIds)
                ->selectRaw('contract_template_id, COUNT(*) as total')
                ->groupBy('contract_template_id')
                ->pluck('total', 'contract_template_id')
                ->each(function ($total, $templateId) use (&$signatureCounts) {
                    $signatureCounts[$templateId] = ($signatureCounts[$templateId] ?? 0) + (int) $total;
                });
        }
        $countries = Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'iso_code_2']);
        $missingAgreementCountries = $this->resolver->countriesWithoutPlatformAgreement();
        $sectionBadges = $this->sections->sectionsByTemplate($templates->getCollection()) + $this->customerAssignments->sectionsByTemplate($templates->getCollection());
        $sectionOptions = $this->sectionTree();
        $uncoveredSections = in_array($scope, [null, ClassifiedContractTemplate::SCOPE_CLASSIFIED], true)
            ? $this->sections->uncoveredSections()
            : collect();

        $destinations = ContractDisplayDestination::active()->orderBy('sort_order')->get();
        $uncoveredMarketerDestinations = in_array($scope, [null, ClassifiedContractTemplate::SCOPE_MARKETER], true)
            ? $this->collaboration->uncoveredMarketerDestinations()
            : collect();

        return view('admin.contracts.templates.index', compact(
            'templates', 'scope', 'status', 'countryFilter', 'assignedCounts', 'signatureCounts', 'countries', 'missingAgreementCountries',
            'sectionFilter', 'sectionBadges', 'sectionOptions', 'uncoveredSections', 'kindFilter', 'destinationFilter', 'destinations', 'uncoveredMarketerDestinations',
        ));
    }

    public function create(): View
    {
        return view('admin.contracts.templates.form', [
            'template' => null,
            'countries' => $this->activeCountries(),
            'variables' => ClassifiedContractTemplate::availableVariables(),
            'maxMb' => ContractFileService::MAX_KB / 1024,
        ] + $this->sectionFormData(null) + $this->marketerFormData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $template = DB::transaction(function () use ($request, $validated) {
            $template = ClassifiedContractTemplate::create($this->attributes($validated) + [
                'version' => $this->nextVersion($validated['name'], $validated['category_scope'], $validated['country_id'] ?? null),
                'is_active' => true,
                'is_published' => false,
                'variables_schema' => ClassifiedContractTemplate::variableKeys(),
                'created_by_admin_id' => auth('admin')->id(),
            ]);

            $this->applyFiles($template, $request);
            $this->syncSections($template, $request, $validated);
            $this->syncMarketerSettings($template, $validated);

            return $template;
        });

        return redirect()
            ->route('admin.contracts.templates.index', ['scope' => $template->category_scope])
            ->with('success', __('admin.contracts.draft_created'));
    }

    public function edit(ClassifiedContractTemplate $contractTemplate): View
    {
        return view('admin.contracts.templates.form', [
            'template' => $contractTemplate,
            'countries' => $this->activeCountries(),
            'variables' => ClassifiedContractTemplate::availableVariables(),
            'maxMb' => ContractFileService::MAX_KB / 1024,
        ] + $this->sectionFormData($contractTemplate) + $this->marketerFormData($contractTemplate));
    }

    /**
     * Drafts are edited in place. Published content is never edited: changed content, mode or files become a new draft version.
     */
    public function update(Request $request, ClassifiedContractTemplate $contractTemplate): RedirectResponse
    {
        $validated = $this->validated($request, $contractTemplate);

        if (! $this->documentChanged($request, $validated, $contractTemplate)) {
            $changes = ['name' => $validated['name']] + $this->marketerAttributes($validated);

            if (! $contractTemplate->is_published) {
                $changes += $this->countryAttributes($validated, $contractTemplate);
            }

            DB::transaction(function () use ($contractTemplate, $changes, $request, $validated) {
                $this->detachOnCountryChange($contractTemplate, $changes, $request);
                $contractTemplate->update($changes);
                $this->syncSections($contractTemplate->refresh(), $request, $validated);
                $this->syncMarketerSettings($contractTemplate, $validated);
            });

            return $this->backToIndex($contractTemplate, __('admin.contracts.updated'));
        }

        if (! $contractTemplate->is_published) {
            DB::transaction(function () use ($request, $validated, $contractTemplate) {
                $changes = $this->attributes($validated) + $this->countryAttributes($validated, $contractTemplate);
                $this->detachOnCountryChange($contractTemplate, $changes, $request);
                $contractTemplate->update($changes);
                $this->applyFiles($contractTemplate, $request);
                $this->syncSections($contractTemplate->refresh(), $request, $validated);
                $this->syncMarketerSettings($contractTemplate, $validated);
            });

            return $this->backToIndex($contractTemplate, __('admin.contracts.updated'));
        }

        $nextVersion = $this->nextVersion($contractTemplate->name, $contractTemplate->category_scope, $contractTemplate->country_id);

        $draft = DB::transaction(function () use ($request, $validated, $contractTemplate, $nextVersion) {
            $draft = ClassifiedContractTemplate::create($this->attributes($validated) + [
                'name' => $contractTemplate->name,
                'category_scope' => $contractTemplate->category_scope,
                'classified_category_id' => $contractTemplate->classified_category_id,
                'product_category_id' => $contractTemplate->product_category_id,
                'country_id' => $contractTemplate->country_id,
                'is_country_default' => $contractTemplate->is_country_default,
                'version' => $nextVersion,
                'is_active' => true,
                'is_published' => false,
                'variables_schema' => ClassifiedContractTemplate::variableKeys(),
                'created_by_admin_id' => auth('admin')->id(),
            ]);

            $draft->update(['files' => $this->files->copyFiles($contractTemplate->files, $draft->id) ?: null]);
            $this->applyFiles($draft, $request);
            $this->syncSections($draft, $request, $validated);
            $this->syncMarketerSettings($draft, $validated);

            return $draft;
        });

        return $this->backToIndex($draft, __('admin.contracts.new_draft_version', ['version' => $nextVersion]));
    }

    /**
     * Makes this version the one vendors sign in its country, retires the previous published version of the same
     * country (never another country's), and notifies vendors who must re-sign.
     */
    public function publish(ClassifiedContractTemplate $contractTemplate): RedirectResponse
    {
        if ($contractTemplate->is_published) {
            return $this->backToIndex($contractTemplate, __('admin.contracts.already_published'));
        }

        if ($contractTemplate->isFileMode() && empty($contractTemplate->files)) {
            return back()->with('error', __('admin.contracts.file_required_to_publish'));
        }

        $previousDefaultId = $this->currentDefault($contractTemplate)?->id;

        if ($contractTemplate->isPlatformScope()) {
            $this->agreements->publish($contractTemplate);
            $contractTemplate->refresh();

            NotifyVendorsOfAgreementUpdateJob::dispatch($contractTemplate->id);
            $this->audit('contract_template.published', $contractTemplate, ['mode' => $contractTemplate->acceptance_mode]);

            return $this->backToIndex($contractTemplate, $contractTemplate->isScheduled()
                ? __('admin.contracts.published_scheduled', ['version' => $contractTemplate->version, 'date' => $contractTemplate->effective_at->format('d/m/Y')])
                : __('admin.contracts.published_platform', ['version' => $contractTemplate->version]));
        }

        if ($contractTemplate->isMarketerScope()) {
            if ($contractTemplate->displayDestinations()->doesntExist() && $contractTemplate->marketers()->doesntExist()) {
                return back()->with('error', __('contracts.marketer.needs_destination'));
            }

            $this->collaboration->publish($contractTemplate);
            $contractTemplate->refresh();
            $this->audit('contract_template.published', $contractTemplate, [
                'kind' => $contractTemplate->contract_kind?->value,
                'destinations' => $contractTemplate->displayDestinations()->pluck('key')->all(),
                'marketers' => $contractTemplate->marketers()->count(),
            ]);

            return $this->backToIndex($contractTemplate, __('contracts.marketer.published', ['version' => $contractTemplate->version]));
        }

        if ($contractTemplate->isCustomerScope()) {
            $this->customerAssignments->publish($contractTemplate);
            $contractTemplate->refresh();
            $this->audit('contract_template.published', $contractTemplate, [
                'kind' => $contractTemplate->contract_kind?->value,
                'sections' => $this->customerAssignments->categoryIdsFor($contractTemplate),
            ]);

            return $this->backToIndex($contractTemplate, __('contracts.customer.published', ['version' => $contractTemplate->version]));
        }

        $vendorsToResign = $this->contracts->publish($contractTemplate);
        $contractTemplate->update(['published_at' => now(), 'effective_at' => now()]);
        $this->sections->activateLinkedSections($contractTemplate);

        $vendorsToResign->each(function (Vendor $vendor) use ($contractTemplate) {
            $vendor->vendorAdmins->each(fn (VendorAdmin $vendorAdmin) => $vendorAdmin->notify(
                new ContractTemplateUpdated($contractTemplate, $vendorAdmin->id),
            ));
        });

        $this->audit('contract_template.published', $contractTemplate, ['vendors_to_resign' => $vendorsToResign->count()]);

        if ($contractTemplate->is_country_default && $previousDefaultId && $previousDefaultId !== $contractTemplate->id && ! $this->contracts->lineageIds($contractTemplate)->contains($previousDefaultId)) {
            $this->audit('contract_template.country_default_changed', $contractTemplate, ['previous_default_template_id' => $previousDefaultId]);
        }

        return $this->backToIndex($contractTemplate, __('admin.contracts.published', [
            'version' => $contractTemplate->version,
            'vendors' => $vendorsToResign->count(),
        ]));
    }

    /**
     * Takes a published contract out of use (retires it). Signatures it already collected are never touched: they
     * stay valid and visible. When vendors have signed it the admin must confirm explicitly. Afterwards the
     * country is served by the legacy country-agnostic contract if there is one, otherwise by none.
     */
    public function retire(Request $request, ClassifiedContractTemplate $contractTemplate): RedirectResponse
    {
        if (! $contractTemplate->is_published && ! $contractTemplate->is_active) {
            return back()->with('error', __('contracts.admin.already_retired'));
        }

        $signatures = match (true) {
            $contractTemplate->isMarketerScope() => MarketerSellerContract::where('contract_template_id', $contractTemplate->id)->count(),
            $contractTemplate->isCustomerScope() => CustomerContract::where('contract_template_id', $contractTemplate->id)->count(),
            default => VendorContract::where('contract_template_id', $contractTemplate->id)->count(),
        };

        if ($signatures > 0 && ! $request->boolean('confirm_signatures')) {
            return back()->with('error', __('contracts.admin.retire_needs_confirmation', ['count' => $signatures]));
        }

        $contractTemplate->update(['is_published' => false, 'is_active' => false, 'is_country_default' => false]);

        $this->audit('contract_template.retired', $contractTemplate, ['signatures' => $signatures]);

        return $this->backToIndex($contractTemplate, __('contracts.admin.retired', ['name' => $contractTemplate->name, 'version' => $contractTemplate->version]));
    }

    /**
     * Brings a retired version back as a draft (it is not in use until it is published again).
     */
    public function reactivate(ClassifiedContractTemplate $contractTemplate): RedirectResponse
    {
        if ($contractTemplate->is_active) {
            return back();
        }

        $contractTemplate->update(['is_active' => true]);
        $this->audit('contract_template.reactivated', $contractTemplate);

        return $this->backToIndex($contractTemplate, __('contracts.admin.reactivated'));
    }

    /**
     * Makes a published country contract the default individual contract of its country and scope (replacing the
     * previous default), or removes the flag. Only one published default per country and scope can exist.
     */
    public function setDefault(Request $request, ClassifiedContractTemplate $contractTemplate): RedirectResponse
    {
        if ($contractTemplate->isPlatformScope() || $contractTemplate->isMarketerScope() || $contractTemplate->isCustomerScope() || ! $contractTemplate->country_id) {
            return back()->with('error', __('contracts.admin.default_needs_country'));
        }

        $makeDefault = $request->boolean('default', true);
        $previous = $makeDefault ? $this->currentDefault($contractTemplate) : null;

        DB::transaction(function () use ($contractTemplate, $makeDefault) {
            if ($makeDefault && $contractTemplate->is_published) {
                ClassifiedContractTemplate::where('country_id', $contractTemplate->country_id)
                    ->where('category_scope', $contractTemplate->category_scope)
                    ->where('is_country_default', true)
                    ->where('id', '!=', $contractTemplate->id)
                    ->update(['is_country_default' => false]);
            }

            $contractTemplate->update(['is_country_default' => $makeDefault]);
        });

        $this->audit('contract_template.country_default_changed', $contractTemplate, [
            'default' => $makeDefault,
            'previous_default_template_id' => $previous?->id,
        ]);

        return $this->backToIndex($contractTemplate, $makeDefault ? __('contracts.admin.default_set') : __('contracts.admin.default_cleared'));
    }

    public function destroy(ClassifiedContractTemplate $contractTemplate): RedirectResponse
    {
        $assigned = Category::where('contract_template_id', $contractTemplate->id)->exists()
            || Product::where('contract_template_id', $contractTemplate->id)->exists()
            || ContractCountryAssignment::where('contract_template_id', $contractTemplate->id)->where(fn ($query) => $query->whereNotNull('product_category_id')->orWhereNotNull('product_id'))->exists();

        if ($contractTemplate->is_published || $assigned || VendorContract::where('contract_template_id', $contractTemplate->id)->exists() || MarketerSellerContract::where('contract_template_id', $contractTemplate->id)->exists() || CustomerContract::where('contract_template_id', $contractTemplate->id)->exists()) {
            return back()->with('error', __('admin.contracts.cannot_delete'));
        }

        DB::transaction(function () use ($contractTemplate) {
            // A draft only holds inert section links; they go with it.
            $this->sections->detach($contractTemplate);
            $this->customerAssignments->detach($contractTemplate);
            $this->files->deleteTemplateFiles($contractTemplate->id);
            $contractTemplate->delete();
        });

        return redirect()
            ->route('admin.contracts.templates.index', ['scope' => $contractTemplate->category_scope])
            ->with('success', __('admin.contracts.deleted'));
    }

    /**
     * Preview (PDF inline) or download (original) of an uploaded template document.
     */
    public function file(Request $request, ClassifiedContractTemplate $contractTemplate, string $language): StreamedResponse
    {
        abort_unless(in_array($language, ['ar', 'en'], true), 404);

        $file = ($contractTemplate->files ?? [])[$language] ?? null;
        abort_unless($file, 404);

        return $this->files->respond($file, $request->boolean('download'));
    }

    /**
     * Vendors with live listings in this template's categories, and whether they signed the current version.
     */
    public function signatures(Request $request, ClassifiedContractTemplate $contractTemplate): View
    {
        if ($contractTemplate->isMarketerScope()) {
            return $this->marketerSignatures($request, $contractTemplate);
        }

        if ($contractTemplate->isCustomerScope()) {
            return $this->customerSignatures($request, $contractTemplate);
        }

        $rows = $this->signatureRows($contractTemplate);

        $summary = [
            'total' => $rows->count(),
            'signed' => $rows->whereNotNull('signature')->count(),
            'pending' => $rows->whereNull('signature')->count(),
        ];

        $filter = in_array($request->query('status'), ['signed', 'pending'], true) ? $request->query('status') : 'all';

        $rows = match ($filter) {
            'signed' => $rows->whereNotNull('signature')->values(),
            'pending' => $rows->whereNull('signature')->values(),
            default => $rows,
        };

        return view('admin.contracts.templates.signatures', compact('contractTemplate', 'rows', 'summary', 'filter'));
    }

    /**
     * Seller signatures on a collaboration contract (all versions of its lineage), newest first.
     */
    private function marketerSignatures(Request $request, ClassifiedContractTemplate $template): View
    {
        $status = in_array($request->query('status'), ['active', 'superseded', 'revoked'], true) ? $request->query('status') : 'all';

        $signatures = MarketerSellerContract::query()
            ->whereIn('contract_template_id', $this->contracts->lineageIds($template)->push($template->id)->unique())
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->with(['vendor:id,store_name', 'marketer:id,name', 'campaign:id,title'])
            ->orderByDesc('signed_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.contracts.templates.marketer-signatures', ['contractTemplate' => $template, 'signatures' => $signatures, 'status' => $status]);
    }

    /**
     * Customer acceptances of a customer purchase contract (all versions of its lineage), newest first.
     */
    private function customerSignatures(Request $request, ClassifiedContractTemplate $template): View
    {
        $status = in_array($request->query('status'), [CustomerContract::STATUS_ACTIVE, CustomerContract::STATUS_SUPERSEDED, CustomerContract::STATUS_CANCELLED], true) ? $request->query('status') : 'all';

        $signatures = CustomerContract::query()
            ->whereIn('contract_template_id', $this->customerAssignments->lineageIds($template))
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->with(['customer:id,name,email', 'classifiedCategory:id,name_en,name_ar', 'country:id,name_en,name_ar,iso_code_2'])
            ->orderByDesc('signed_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.contracts.templates.customer-signatures', ['contractTemplate' => $template, 'signatures' => $signatures, 'status' => $status]);
    }

    /**
     * Marketers matching a search (for the "specific marketers" picker of the template form).
     */
    public function marketerSearch(Request $request): JsonResponse
    {
        abort_unless(auth('admin')->user()->can('marketers.view'), 403);

        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(Marketer::query()
            ->where(fn (Builder $q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email'])
            ->map(fn (Marketer $marketer) => ['id' => $marketer->id, 'text' => $marketer->name.' ('.$marketer->email.')'])
            ->values());
    }

    /**
     * @return Collection<int, array{vendor: Vendor, listings: int, signature: ?VendorContract}>
     */
    private function signatureRows(ClassifiedContractTemplate $template): Collection
    {
        if ($template->isPlatformScope()) {
            return $this->platformSignatureRows($template);
        }

        $isProduct = $template->category_scope === CategoryContractService::SCOPE_PRODUCT;

        if ($isProduct) {
            $categoryIds = Category::where('contract_template_id', $template->id)->pluck('id')
                ->merge(ContractCountryAssignment::where('contract_template_id', $template->id)->whereNotNull('product_category_id')->pluck('product_category_id'))
                ->unique();
            $productIds = Product::where('contract_template_id', $template->id)->pluck('id')
                ->merge(ContractCountryAssignment::where('contract_template_id', $template->id)->whereNotNull('product_id')->pluck('product_id'))
                ->unique();

            $listingCounts = VendorListing::query()
                ->join('product_variants as variant', 'variant.id', '=', 'vendor_listings.product_variant_id')
                ->join('products as product', 'product.id', '=', 'variant.product_id')
                ->where(fn ($query) => $query->whereIn('product.category_id', $categoryIds)->orWhereIn('product.id', $productIds))
                ->selectRaw('vendor_listings.vendor_id as vendor_id, COUNT(*) as total')
                ->groupBy('vendor_listings.vendor_id')
                ->pluck('total', 'vendor_id');
        } else {
            $categoryIds = ClassifiedCategory::where('contract_template_id', $template->id)->pluck('id')
                ->merge(ContractCountryAssignment::where('contract_template_id', $template->id)->whereNotNull('classified_category_id')->pluck('classified_category_id'))
                ->unique();

            $listingCounts = ClassifiedListing::forVendors()
                ->whereIn('classified_category_id', $categoryIds)
                ->whereNotIn('status', ['draft', 'sold', 'expired', 'rejected'])
                ->selectRaw('seller_id, COUNT(*) as total')
                ->groupBy('seller_id')
                ->pluck('total', 'seller_id');
        }

        $signatures = VendorContract::where('contract_template_id', $template->id)
            ->where('template_version', $template->version)
            ->where('status', 'active')
            ->with('signedByVendorAdmin:id,name')
            ->get()
            ->keyBy('vendor_id');

        return Vendor::whereIn('id', $listingCounts->keys())
            ->orderBy('store_name')
            ->get(['id', 'store_name', 'email'])
            ->map(fn (Vendor $vendor) => [
                'vendor' => $vendor,
                'listings' => (int) $listingCounts[$vendor->id],
                'signature' => $signatures[$vendor->id] ?? null,
            ]);
    }

    /**
     * The Seller Agreement applies to every vendor, so every vendor is listed, with their listing count left empty.
     *
     * @return Collection<int, array{vendor: Vendor, listings: ?int, signature: ?VendorContract}>
     */
    private function platformSignatureRows(ClassifiedContractTemplate $template): Collection
    {
        $signatures = VendorContract::where('contract_template_id', $template->id)
            ->where('status', 'active')
            ->with('signedByVendorAdmin:id,name')
            ->get()
            ->keyBy('vendor_id');

        return $this->agreements->vendorsServedBy($template)
            ->whereIn('global_status', ['active', 'under_review'])
            ->orderBy('store_name')
            ->get(['id', 'store_name', 'email'])
            ->map(fn (Vendor $vendor) => [
                'vendor' => $vendor,
                'listings' => null,
                'signature' => $signatures[$vendor->id] ?? null,
            ]);
    }

    /**
     * @return array<string, int> assigned category count keyed by template id
     */
    private function assignedCounts(): array
    {
        $counts = [];
        $add = function ($rows) use (&$counts): void {
            foreach ($rows as $row) {
                $counts[$row->contract_template_id] = ($counts[$row->contract_template_id] ?? 0) + (int) $row->total;
            }
        };

        foreach ([ClassifiedCategory::class, Category::class, Product::class, ContractCountryAssignment::class, CustomerContractAssignment::class] as $model) {
            $add($model::query()
                ->whereNotNull('contract_template_id')
                ->selectRaw('contract_template_id, COUNT(*) as total')
                ->groupBy('contract_template_id')
                ->get());
        }

        return $counts;
    }

    private function scopeFilter(Request $request): ?string
    {
        return in_array($request->query('scope'), [CategoryContractService::SCOPE_CLASSIFIED, CategoryContractService::SCOPE_PRODUCT, CategoryContractService::SCOPE_PLATFORM, ClassifiedContractTemplate::SCOPE_MARKETER, ClassifiedContractTemplate::SCOPE_CUSTOMER], true)
            ? $request->query('scope')
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ClassifiedContractTemplate $existing = null): array
    {
        $isFile = $request->input('content_mode') === ClassifiedContractTemplate::MODE_FILE;
        $scope = $existing?->category_scope ?? $request->input('category_scope');
        $fileRule = ['nullable', 'file', 'mimes:'.implode(',', ContractFileService::EXTENSIONS), 'max:'.ContractFileService::MAX_KB];

        $rules = [
            'name' => 'required|string|max:200',
            'content_mode' => ['required', Rule::in([ClassifiedContractTemplate::MODE_EDITOR, ClassifiedContractTemplate::MODE_FILE])],
            'content_en' => [$isFile ? 'nullable' : 'required', 'string'],
            'content_ar' => [$isFile ? 'nullable' : 'required', 'string'],
            'file_ar' => $fileRule,
            'file_en' => $fileRule,
            'remove_file' => 'nullable|array',
            'remove_file.*' => Rule::in(['ar', 'en']),
            'country_id' => ['nullable', 'uuid', Rule::exists('countries', 'id')],
            'is_country_default' => 'nullable|boolean',
            'classified_category_ids' => 'nullable|array',
            'classified_category_ids.*' => ['uuid', Rule::exists('classified_categories', 'id')],
        ];

        if (! $existing) {
            $rules['category_scope'] = ['required', Rule::in([
                CategoryContractService::SCOPE_CLASSIFIED,
                CategoryContractService::SCOPE_PRODUCT,
                CategoryContractService::SCOPE_PLATFORM,
                ClassifiedContractTemplate::SCOPE_MARKETER,
                ClassifiedContractTemplate::SCOPE_CUSTOMER,
            ])];
        }

        if ($scope === ClassifiedContractTemplate::SCOPE_MARKETER) {
            $rules['destination_ids'] = 'nullable|array';
            $rules['destination_ids.*'] = ['uuid', Rule::exists('contract_display_destinations', 'id')->where('is_active', true)];
            $rules['marketer_ids'] = 'nullable|array';
            $rules['marketer_ids.*'] = ['uuid', 'distinct', Rule::exists('marketers', 'id')];
            $rules['classified_category_id'] = ['nullable', 'uuid', Rule::exists('classified_categories', 'id')];
            $rules['show_on_public_profile'] = 'nullable|boolean';
        }

        if ($scope === CategoryContractService::SCOPE_PLATFORM) {
            $rules['acceptance_mode'] = ['required', Rule::in([ClassifiedContractTemplate::ACCEPTANCE_CLICK_WRAP, ClassifiedContractTemplate::ACCEPTANCE_NOTICE])];
            $rules['notice_days'] = 'nullable|integer|min:1|max:90';
            $rules['change_summary'] = 'nullable|string|max:2000';
        }

        $validated = $request->validate($rules);

        if ($isFile && ! $this->hasAnyFile($request, $existing)) {
            throw ValidationException::withMessages(['file_ar' => __('admin.contracts.file_required')]);
        }

        $validated['category_scope'] = $scope;
        $validated['country_id'] = $existing?->is_published ? $existing->country_id : ($validated['country_id'] ?? null);
        $validated['is_country_default'] = $existing?->is_published
            ? $existing->is_country_default
            : ($scope !== CategoryContractService::SCOPE_PLATFORM && $request->boolean('is_country_default'));

        if ($scope === ClassifiedContractTemplate::SCOPE_MARKETER) {
            $validated['is_country_default'] = false;
            $validated['destination_ids'] = array_values(array_unique($validated['destination_ids'] ?? []));
            $validated['marketer_ids'] = array_values(array_unique($validated['marketer_ids'] ?? []));

            if ($validated['destination_ids'] === [] && $validated['marketer_ids'] === []) {
                throw ValidationException::withMessages(['destination_ids' => __('contracts.marketer.needs_destination')]);
            }
        }

        if ($scope === ClassifiedContractTemplate::SCOPE_CUSTOMER) {
            $validated['is_country_default'] = false;
        }

        if ($validated['is_country_default'] && ! $validated['country_id']) {
            throw ValidationException::withMessages(['is_country_default' => __('contracts.admin.default_needs_country')]);
        }

        if ($existing) {
            $validated['name'] = $existing->name === $validated['name'] || $existing->is_published
                ? $existing->name
                : $validated['name'];
        }

        return $validated;
    }

    /**
     * A file-mode template needs at least one document: a new upload, or an existing one that is not being removed.
     */
    private function hasAnyFile(Request $request, ?ClassifiedContractTemplate $existing): bool
    {
        $removed = (array) $request->input('remove_file', []);

        foreach (['ar', 'en'] as $language) {
            if ($request->hasFile('file_'.$language)) {
                return true;
            }

            if (($existing?->files[$language] ?? null) && ! in_array($language, $removed, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this save changes what vendors read or sign (text, mode, files), as opposed to a rename only.
     *
     * @param  array<string, mixed>  $validated
     */
    private function documentChanged(Request $request, array $validated, ClassifiedContractTemplate $existing): bool
    {
        if ($validated['content_mode'] !== $existing->content_mode) {
            return true;
        }

        if ($validated['content_mode'] === ClassifiedContractTemplate::MODE_FILE) {
            return $request->hasFile('file_ar') || $request->hasFile('file_en') || ! empty($request->input('remove_file'))
                || $this->settingsChanged($validated, $existing);
        }

        return ($validated['content_en'] ?? '') !== $existing->content_en
            || ($validated['content_ar'] ?? '') !== $existing->content_ar
            || $this->settingsChanged($validated, $existing);
    }

    /**
     * Amendment settings only matter on a draft; on a published template they take effect with the next version.
     *
     * @param  array<string, mixed>  $validated
     */
    private function settingsChanged(array $validated, ClassifiedContractTemplate $existing): bool
    {
        if (! $existing->isPlatformScope() || $existing->is_published) {
            return false;
        }

        return ($validated['acceptance_mode'] ?? $existing->acceptance_mode) !== $existing->acceptance_mode
            || (int) ($validated['notice_days'] ?? $existing->notice_days) !== $existing->notice_days
            || ($validated['change_summary'] ?? $existing->change_summary) !== $existing->change_summary;
    }

    /**
     * Columns written from the form. File metadata is written separately by applyFiles().
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        $attributes = [
            'name' => $validated['name'],
            'category_scope' => $validated['category_scope'],
            'content_mode' => $validated['content_mode'],
            'country_id' => $validated['country_id'] ?? null,
            'is_country_default' => (bool) ($validated['is_country_default'] ?? false),
            'content_en' => $validated['content_mode'] === ClassifiedContractTemplate::MODE_FILE ? '' : $validated['content_en'],
            'content_ar' => $validated['content_mode'] === ClassifiedContractTemplate::MODE_FILE ? '' : $validated['content_ar'],
        ];

        $attributes += $this->marketerAttributes($validated);

        if ($validated['category_scope'] === CategoryContractService::SCOPE_PLATFORM) {
            $attributes += [
                'acceptance_mode' => $validated['acceptance_mode'],
                'notice_days' => $validated['notice_days'] ?? 14,
                'change_summary' => $validated['change_summary'] ?? null,
            ];
        }

        return $attributes;
    }

    /**
     * Stores uploads and removals for a draft, and drops every file when the template is back in editor mode.
     */
    private function applyFiles(ClassifiedContractTemplate $template, Request $request): void
    {
        if (! $template->isFileMode()) {
            $this->files->deleteTemplateFiles($template->id);
            $template->update(['files' => null]);

            return;
        }

        $files = $template->files ?? [];
        $removed = (array) $request->input('remove_file', []);

        foreach (['ar', 'en'] as $language) {
            $replacing = $request->hasFile('file_'.$language);

            if (($replacing || in_array($language, $removed, true)) && isset($files[$language])) {
                $this->files->delete($files[$language]);
                unset($files[$language]);
            }

            if ($replacing) {
                $files[$language] = $this->files->store($request->file('file_'.$language), $template->id, $language);
            }
        }

        $template->update(['files' => $files ?: null]);
    }

    /**
     * Next version number inside one lineage: same scope, same country (null = legacy country-agnostic) and,
     * for category contracts, same name. Versions of different countries are independent.
     */
    private function nextVersion(string $name, string $scope, ?string $countryId = null): int
    {
        return (int) ClassifiedContractTemplate::where('category_scope', $scope)
            ->when($countryId, fn (Builder $query) => $query->where('country_id', $countryId), fn (Builder $query) => $query->whereNull('country_id'))
            ->when($scope !== CategoryContractService::SCOPE_PLATFORM, fn (Builder $query) => $query->where('name', $name))
            ->max('version') + 1;
    }

    /**
     * Country columns written from the form. Published templates keep the country they were published for.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function countryAttributes(array $validated, ClassifiedContractTemplate $template): array
    {
        $attributes = [
            'country_id' => $validated['country_id'] ?? null,
            'is_country_default' => (bool) ($validated['is_country_default'] ?? false),
        ];

        if (($attributes['country_id'] ?? null) !== $template->country_id) {
            $attributes['version'] = $this->nextVersion($template->name, $template->category_scope, $attributes['country_id']);
        }

        return $attributes;
    }

    private function currentDefault(ClassifiedContractTemplate $template): ?ClassifiedContractTemplate
    {
        if (! $template->country_id || $template->isPlatformScope()) {
            return null;
        }

        return ClassifiedContractTemplate::enforceable()
            ->where('country_id', $template->country_id)
            ->where('category_scope', $template->category_scope)
            ->where('is_country_default', true)
            ->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Country>
     */
    private function activeCountries()
    {
        return Country::where('is_active', true)->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'iso_code_2']);
    }

    /**
     * Section ids (classified scope only) the form selected; null when the form did not send the selector.
     *
     * @param  array<string, mixed>  $validated
     * @return list<string>|null
     */
    private function selectedSections(Request $request, array $validated, ClassifiedContractTemplate $template): ?array
    {
        if (! in_array($template->category_scope, [ClassifiedContractTemplate::SCOPE_CLASSIFIED, ClassifiedContractTemplate::SCOPE_CUSTOMER], true)) {
            return null;
        }

        if (! $request->has('classified_category_ids') && ! $request->boolean('sections_submitted')) {
            return null;
        }

        return array_values($validated['classified_category_ids'] ?? []);
    }

    /**
     * Auto-associates the template with the selected sections (inside the caller's transaction).
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncSections(ClassifiedContractTemplate $template, Request $request, array $validated): void
    {
        $selected = $this->selectedSections($request, $validated, $template);

        if ($selected === null) {
            return;
        }

        if ($template->isCustomerScope()) {
            $this->customerAssignments->sync($template, $selected);
        } else {
            $this->sections->sync($template, $selected);
        }
    }

    /**
     * A draft that moves to another country leaves the links of its old country behind.
     *
     * @param  array<string, mixed>  $changes
     */
    private function detachOnCountryChange(ClassifiedContractTemplate $template, array $changes, Request $request): void
    {
        if (! $template->is_published && array_key_exists('country_id', $changes) && ($changes['country_id'] ?? null) !== $template->country_id) {
            $this->sections->detach($template);
            $this->customerAssignments->detach($template);
        }
    }

    /**
     * Columns only seller-marketer templates have.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function marketerAttributes(array $validated): array
    {
        if (($validated['category_scope'] ?? null) !== ClassifiedContractTemplate::SCOPE_MARKETER) {
            return [];
        }

        return [
            'classified_category_id' => $validated['classified_category_id'] ?? null,
            'show_on_public_profile' => (bool) ($validated['show_on_public_profile'] ?? false),
        ];
    }

    /**
     * Stores the Display On destinations and the specific marketers of a seller-marketer template (inside the caller's
     * transaction) and audits a change.
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncMarketerSettings(ClassifiedContractTemplate $template, array $validated): void
    {
        if (! $template->isMarketerScope()) {
            return;
        }

        $before = [
            'destinations' => $template->displayDestinations()->pluck('key')->sort()->values()->all(),
            'marketers' => $template->marketers()->pluck('marketers.id')->sort()->values()->all(),
        ];

        $template->displayDestinations()->sync($validated['destination_ids'] ?? []);
        $template->marketers()->sync($validated['marketer_ids'] ?? []);

        $after = [
            'destinations' => $template->displayDestinations()->pluck('key')->sort()->values()->all(),
            'marketers' => $template->marketers()->pluck('marketers.id')->sort()->values()->all(),
        ];

        if ($before !== $after) {
            $this->audit('contract_template.display_changed', $template, ['before' => $before, 'after' => $after]);
        }
    }

    /**
     * Data for the Display On / marketer pickers of the template form.
     *
     * @return array<string, mixed>
     */
    private function marketerFormData(?ClassifiedContractTemplate $template): array
    {
        $selectedMarketers = old('marketer_ids') !== null
            ? Marketer::whereIn('id', (array) old('marketer_ids'))->get(['id', 'name', 'email'])
            : ($template ? $template->marketers()->get(['marketers.id', 'name', 'email']) : collect());

        return [
            'destinations' => ContractDisplayDestination::active()->orderBy('sort_order')->get(),
            'selectedDestinationIds' => $template ? $template->displayDestinations()->pluck('contract_display_destinations.id')->all() : [],
            'selectedMarketers' => $selectedMarketers->map(fn (Marketer $marketer) => ['id' => $marketer->id, 'text' => $marketer->name.' ('.$marketer->email.')'])->values()->all(),
        ];
    }

    /**
     * Data for the section selector of the template form.
     *
     * @return array<string, mixed>
     */
    private function sectionFormData(?ClassifiedContractTemplate $template): array
    {
        $lineage = $template ? $this->contracts->lineageIds($template)->push($template->id)->unique()->values()->all() : [];
        $customerLineage = $template?->isCustomerScope() ? $this->customerAssignments->lineageIds($template)->all() : [];

        return [
            'sectionTree' => $this->sectionTree(),
            'selectedSections' => $template ? ($template->isCustomerScope() ? $this->customerAssignments->categoryIdsFor($template) : $this->sections->categoryIdsFor($template)) : [],
            'takenSections' => $this->sections->takenSections($lineage),
            'takenCustomerSections' => $this->customerAssignments->takenSections($customerLineage),
        ];
    }

    /**
     * @return Collection<int, ClassifiedCategory> top-level sections with their sub-sections
     */
    private function sectionTree(): Collection
    {
        $all = ClassifiedCategory::orderBy('sort_order')->orderBy('name_en')->get(['id', 'name_en', 'name_ar', 'parent_id', 'is_active']);
        $byParent = $all->groupBy('parent_id');

        return $all->whereNull('parent_id')
            ->each(fn (ClassifiedCategory $root) => $root->setRelation('children', $byParent->get($root->id, collect())->values()))
            ->values();
    }

    private function sectionFilter(Request $request): ?string
    {
        $value = $request->query('section');

        return is_string($value) && ClassifiedCategory::whereKey($value)->exists() ? $value : null;
    }

    /**
     * Templates that apply to a section: linked to it or to a parent (all versions of those contracts).
     *
     * @param  Builder<ClassifiedContractTemplate>  $query
     * @return Builder<ClassifiedContractTemplate>
     */
    private function limitToSection(Builder $query, string $sectionId): Builder
    {
        $linked = ClassifiedContractTemplate::whereIn('id', $this->sections->templateIdsForSection($sectionId)->merge($this->customerAssignments->templateIdsForSection($sectionId)))
            ->get(['name', 'category_scope', 'country_id']);

        return $query->where(function (Builder $outer) use ($linked) {
            $outer->whereRaw('1 = 0');

            foreach ($linked as $template) {
                $outer->orWhere(fn (Builder $match) => $match
                    ->where('name', $template->name)
                    ->where('category_scope', $template->category_scope)
                    ->when($template->country_id, fn (Builder $q) => $q->where('country_id', $template->country_id), fn (Builder $q) => $q->whereNull('country_id')));
            }
        });
    }

    /**
     * Country filter value: a country id, 'legacy' (country-agnostic templates) or null (all).
     */
    private function countryFilter(Request $request): ?string
    {
        $value = $request->query('country');

        if ($value === 'legacy') {
            return 'legacy';
        }

        return is_string($value) && Country::whereKey($value)->exists() ? $value : null;
    }

    /**
     * Audit trail for contract changes (publish, retire, country default).
     *
     * @param  array<string, mixed>  $properties
     */
    private function audit(string $event, ClassifiedContractTemplate $template, array $properties = []): void
    {
        $this->activity->log(
            description: $event.': '.$template->name.' v'.$template->version,
            subject: $template,
            causer: auth('admin')->user(),
            properties: $properties + [
                'template_id' => $template->id,
                'scope' => $template->category_scope,
                'country_id' => $template->country_id,
                'version' => $template->version,
            ],
            logName: 'contracts',
            event: $event,
        );
    }

    private function backToIndex(ClassifiedContractTemplate $template, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.contracts.templates.index', ['scope' => $template->category_scope])
            ->with('success', $message);
    }
}
