<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyVendorsOfAgreementUpdateJob;
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
use App\Services\Vendor\ContractFileService;
use App\Services\Vendor\SellerAgreementService;
use Illuminate\Database\Eloquent\Builder;
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
    ) {}

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
            'maxMb' => ContractFileService::MAX_KB / 1024,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $template = DB::transaction(function () use ($request, $validated) {
            $template = ClassifiedContractTemplate::create($this->attributes($validated) + [
                'version' => $this->nextVersion($validated['name'], $validated['category_scope']),
                'is_active' => true,
                'is_published' => false,
                'variables_schema' => ClassifiedContractTemplate::variableKeys(),
                'created_by_admin_id' => auth('admin')->id(),
            ]);

            $this->applyFiles($template, $request);

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
            'variables' => ClassifiedContractTemplate::availableVariables(),
            'maxMb' => ContractFileService::MAX_KB / 1024,
        ]);
    }

    /**
     * Drafts are edited in place. Published content is never edited: changed content, mode or files become a new draft version.
     */
    public function update(Request $request, ClassifiedContractTemplate $contractTemplate): RedirectResponse
    {
        $validated = $this->validated($request, $contractTemplate);

        if (! $this->documentChanged($request, $validated, $contractTemplate)) {
            $contractTemplate->update(['name' => $validated['name']]);

            return $this->backToIndex($contractTemplate, __('admin.contracts.updated'));
        }

        if (! $contractTemplate->is_published) {
            DB::transaction(function () use ($request, $validated, $contractTemplate) {
                $contractTemplate->update($this->attributes($validated));
                $this->applyFiles($contractTemplate, $request);
            });

            return $this->backToIndex($contractTemplate, __('admin.contracts.updated'));
        }

        $nextVersion = $this->nextVersion($contractTemplate->name, $contractTemplate->category_scope);

        $draft = DB::transaction(function () use ($request, $validated, $contractTemplate, $nextVersion) {
            $draft = ClassifiedContractTemplate::create($this->attributes($validated) + [
                'name' => $contractTemplate->name,
                'category_scope' => $contractTemplate->category_scope,
                'classified_category_id' => $contractTemplate->classified_category_id,
                'product_category_id' => $contractTemplate->product_category_id,
                'version' => $nextVersion,
                'is_active' => true,
                'is_published' => false,
                'variables_schema' => ClassifiedContractTemplate::variableKeys(),
                'created_by_admin_id' => auth('admin')->id(),
            ]);

            $draft->update(['files' => $this->files->copyFiles($contractTemplate->files, $draft->id) ?: null]);
            $this->applyFiles($draft, $request);

            return $draft;
        });

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

        if ($contractTemplate->isFileMode() && empty($contractTemplate->files)) {
            return back()->with('error', __('admin.contracts.file_required_to_publish'));
        }

        if ($contractTemplate->isPlatformScope()) {
            $this->agreements->publish($contractTemplate);
            $contractTemplate->refresh();

            NotifyVendorsOfAgreementUpdateJob::dispatch($contractTemplate->id);

            return $this->backToIndex($contractTemplate, $contractTemplate->isScheduled()
                ? __('admin.contracts.published_scheduled', ['version' => $contractTemplate->version, 'date' => $contractTemplate->effective_at->format('d/m/Y')])
                : __('admin.contracts.published_platform', ['version' => $contractTemplate->version]));
        }

        $vendorsToResign = $this->contracts->publish($contractTemplate);
        $contractTemplate->update(['published_at' => now(), 'effective_at' => now()]);

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

        $this->files->deleteTemplateFiles($contractTemplate->id);
        $contractTemplate->delete();

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
        if ($template->isPlatformScope()) {
            return $this->platformSignatureRows($template);
        }

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

        return Vendor::whereIn('global_status', ['active', 'under_review'])
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
        return in_array($request->query('scope'), [CategoryContractService::SCOPE_CLASSIFIED, CategoryContractService::SCOPE_PRODUCT, CategoryContractService::SCOPE_PLATFORM], true)
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
        ];

        if (! $existing) {
            $rules['category_scope'] = ['required', Rule::in([
                CategoryContractService::SCOPE_CLASSIFIED,
                CategoryContractService::SCOPE_PRODUCT,
                CategoryContractService::SCOPE_PLATFORM,
            ])];
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
            'content_en' => $validated['content_mode'] === ClassifiedContractTemplate::MODE_FILE ? '' : $validated['content_en'],
            'content_ar' => $validated['content_mode'] === ClassifiedContractTemplate::MODE_FILE ? '' : $validated['content_ar'],
        ];

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

    private function nextVersion(string $name, string $scope): int
    {
        return (int) ClassifiedContractTemplate::where('category_scope', $scope)
            ->when($scope !== CategoryContractService::SCOPE_PLATFORM, fn (Builder $query) => $query->where('name', $name))
            ->max('version') + 1;
    }

    private function backToIndex(ClassifiedContractTemplate $template, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.contracts.templates.index', ['scope' => $template->category_scope])
            ->with('success', $message);
    }
}
