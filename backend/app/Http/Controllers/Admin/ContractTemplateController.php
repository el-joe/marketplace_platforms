<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ClassifiedCategory;
use App\Models\ClassifiedContractTemplate;
use App\Models\ClassifiedListing;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Models\VendorContract;
use App\Models\VendorListing;
use App\Notifications\Vendor\ContractTemplateUpdated;
use App\Services\Vendor\CategoryContractService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Contract templates for classified and product categories.
 *
 * A template is a draft until published. Published content is frozen: editing it creates a new draft version,
 * and publishing that version moves the categories onto it and asks affected vendors to sign again.
 */
class ContractTemplateController extends Controller
{
    public function __construct(private readonly CategoryContractService $contracts) {}

    public function index(Request $request): View
    {
        $scope = $this->scopeFilter($request);

        $templates = ClassifiedContractTemplate::query()
            ->when($scope, fn (Builder $query) => $query->where('category_scope', $scope))
            ->with(['category:id,name_en', 'productCategory:id,name_en'])
            ->orderBy('name')
            ->orderByDesc('version')
            ->get();

        $assignedCounts = $this->assignedCounts();

        return view('admin.contracts.templates.index', compact('templates', 'scope', 'assignedCounts'));
    }

    public function create(): View
    {
        return view('admin.contracts.templates.form', [
            'template' => null,
            'variables' => ClassifiedContractTemplate::availableVariables(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $template = ClassifiedContractTemplate::create($validated + [
            'version' => 1,
            'is_active' => true,
            'is_published' => false,
            'variables_schema' => ClassifiedContractTemplate::variableKeys(),
            'created_by_admin_id' => auth('admin')->id(),
        ]);

        return redirect()
            ->route('admin.contracts.templates.index', ['scope' => $template->category_scope])
            ->with('success', __('admin.contracts.draft_created'));
    }

    public function edit(ClassifiedContractTemplate $contractTemplate): View
    {
        return view('admin.contracts.templates.form', [
            'template' => $contractTemplate,
            'variables' => ClassifiedContractTemplate::availableVariables(),
        ]);
    }

    /**
     * Drafts are edited in place. Published content is never edited: changed content becomes a new draft version.
     */
    public function update(Request $request, ClassifiedContractTemplate $contractTemplate): RedirectResponse
    {
        $validated = $this->validated($request, $contractTemplate);

        $contentChanged = $validated['content_en'] !== $contractTemplate->content_en
            || $validated['content_ar'] !== $contractTemplate->content_ar;

        if (! $contentChanged) {
            $contractTemplate->update(['name' => $validated['name']]);

            return $this->backToIndex($contractTemplate, __('admin.contracts.updated'));
        }

        if (! $contractTemplate->is_published) {
            $contractTemplate->update($validated);

            return $this->backToIndex($contractTemplate, __('admin.contracts.updated'));
        }

        $nextVersion = (int) ClassifiedContractTemplate::where('name', $contractTemplate->name)
            ->where('category_scope', $contractTemplate->category_scope)
            ->max('version') + 1;

        $draft = ClassifiedContractTemplate::create([
            'name' => $contractTemplate->name,
            'category_scope' => $contractTemplate->category_scope,
            'classified_category_id' => $contractTemplate->classified_category_id,
            'product_category_id' => $contractTemplate->product_category_id,
            'content_en' => $validated['content_en'],
            'content_ar' => $validated['content_ar'],
            'version' => $nextVersion,
            'is_active' => true,
            'is_published' => false,
            'variables_schema' => ClassifiedContractTemplate::variableKeys(),
            'created_by_admin_id' => auth('admin')->id(),
        ]);

        return $this->backToIndex($draft, __('admin.contracts.new_draft_version', ['version' => $nextVersion]));
    }

    /**
     * Makes this version the one vendors sign, retires the previous published version, and notifies vendors who must re-sign.
     */
    public function publish(ClassifiedContractTemplate $contractTemplate): RedirectResponse
    {
        if ($contractTemplate->is_published) {
            return $this->backToIndex($contractTemplate, __('admin.contracts.already_published'));
        }

        $vendorsToResign = $this->contracts->publish($contractTemplate);

        $vendorsToResign->each(function (Vendor $vendor) use ($contractTemplate) {
            $vendor->vendorAdmins->each(fn (VendorAdmin $vendorAdmin) => $vendorAdmin->notify(
                new ContractTemplateUpdated($contractTemplate, $vendorAdmin->id),
            ));
        });

        return $this->backToIndex($contractTemplate, __('admin.contracts.published', [
            'version' => $contractTemplate->version,
            'vendors' => $vendorsToResign->count(),
        ]));
    }

    public function destroy(ClassifiedContractTemplate $contractTemplate): RedirectResponse
    {
        $assigned = ClassifiedCategory::where('contract_template_id', $contractTemplate->id)->exists()
            || Category::where('contract_template_id', $contractTemplate->id)->exists();

        if ($contractTemplate->is_published || $assigned || VendorContract::where('contract_template_id', $contractTemplate->id)->exists()) {
            return back()->with('error', __('admin.contracts.cannot_delete'));
        }

        $contractTemplate->delete();

        return redirect()
            ->route('admin.contracts.templates.index', ['scope' => $contractTemplate->category_scope])
            ->with('success', __('admin.contracts.deleted'));
    }

    /**
     * Vendors with live listings in this template's categories, and whether they signed the current version.
     */
    public function signatures(Request $request, ClassifiedContractTemplate $contractTemplate): View
    {
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
     * @return Collection<int, array{vendor: Vendor, listings: int, signature: ?VendorContract}>
     */
    private function signatureRows(ClassifiedContractTemplate $template): Collection
    {
        $isProduct = $template->category_scope === CategoryContractService::SCOPE_PRODUCT;

        if ($isProduct) {
            $categoryIds = Category::where('contract_template_id', $template->id)->pluck('id');

            $listingCounts = VendorListing::query()
                ->join('product_variants as variant', 'variant.id', '=', 'vendor_listings.product_variant_id')
                ->join('products as product', 'product.id', '=', 'variant.product_id')
                ->whereIn('product.category_id', $categoryIds)
                ->selectRaw('vendor_listings.vendor_id as vendor_id, COUNT(*) as total')
                ->groupBy('vendor_listings.vendor_id')
                ->pluck('total', 'vendor_id');
        } else {
            $categoryIds = ClassifiedCategory::where('contract_template_id', $template->id)->pluck('id');

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
     * @return array<string, int> assigned category count keyed by template id
     */
    private function assignedCounts(): array
    {
        $counts = [];

        foreach ([ClassifiedCategory::class, Category::class] as $model) {
            $model::query()
                ->whereNotNull('contract_template_id')
                ->selectRaw('contract_template_id, COUNT(*) as total')
                ->groupBy('contract_template_id')
                ->get()
                ->each(function ($row) use (&$counts) {
                    $counts[$row->contract_template_id] = ($counts[$row->contract_template_id] ?? 0) + (int) $row->total;
                });
        }

        return $counts;
    }

    private function scopeFilter(Request $request): ?string
    {
        return in_array($request->query('scope'), [CategoryContractService::SCOPE_CLASSIFIED, CategoryContractService::SCOPE_PRODUCT], true)
            ? $request->query('scope')
            : null;
    }

    private function validated(Request $request, ?ClassifiedContractTemplate $existing = null): array
    {
        $rules = [
            'content_en' => 'required|string',
            'content_ar' => 'required|string',
        ];

        if (! $existing) {
            $rules['name'] = 'required|string|max:200';
            $rules['category_scope'] = ['required', Rule::in([CategoryContractService::SCOPE_CLASSIFIED, CategoryContractService::SCOPE_PRODUCT])];
        } else {
            $rules['name'] = 'required|string|max:200';
        }

        $validated = $request->validate($rules);

        if (! $existing) {
            $validated['classified_category_id'] = null;
            $validated['product_category_id'] = null;
        } else {
            $validated['category_scope'] = $existing->category_scope;
            $validated['name'] = $existing->name === $validated['name'] || $existing->is_published
                ? $existing->name
                : $validated['name'];
        }

        return $validated;
    }

    private function backToIndex(ClassifiedContractTemplate $template, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.contracts.templates.index', ['scope' => $template->category_scope])
            ->with('success', $message);
    }
}
